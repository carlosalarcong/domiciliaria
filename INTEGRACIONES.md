# Integraciones externas — Webhooks outbound

Guía completa para entender cómo funciona el sistema de webhooks y cómo conectar un sistema externo (ERP, RRHH, remuneraciones, etc.) en minutos.

---

## ¿Qué es un webhook?

Un webhook es un **aviso automático** que el sistema envía a una URL de tu elección cada vez que ocurre algo relevante. En lugar de que el sistema externo pregunte "¿pasó algo?" cada cierto tiempo (*polling*), el sistema le avisa directamente (*push*).

```
Sistema Domiciliaria              Sistema externo (ERP, RRHH…)
        │                                    │
        │  POST /tu-endpoint                 │
        │  { "evento": "liquidacion.pagada"  │
        │    "datos": { ... } }              │
        │ ─────────────────────────────────► │
        │                                    │
        │  ← HTTP 200 OK                     │
```

---

## Flujo completo de un evento

```
1. Algo ocurre en el sistema
   (ej: se paga una liquidación)

2. El servicio correspondiente llama a WebhookDispatcher::dispatch()

3. WebhookDispatcher busca las suscripciones activas que escuchan ese evento

4. Por cada suscripción:
   a. Crea un registro WebhookDelivery (estado: pendiente)
   b. Encola un WebhookDeliveryMessage en Symfony Messenger

5. El worker de Messenger procesa el mensaje en background:
   a. Hace POST al endpoint configurado
   b. Firma el payload con HMAC-SHA256
   c. Si responde 2xx → estado: entregado ✓
   d. Si falla → reintenta hasta 3 veces con backoff automático
   e. Tras 3 fallos → estado: fallido ✗ (reintentable manualmente desde la UI)
```

---

## Formato del payload

Cada POST enviado al endpoint tiene este body JSON:

```json
{
  "evento": "liquidacion.pagada",
  "timestamp": "2026-03-30T15:42:00Z",
  "tenant": "clinica_demo",
  "version": "1",
  "datos": {
    "id": "019526ab-...",
    "...": "campos específicos del evento"
  }
}
```

| Campo       | Descripción                                              |
|-------------|----------------------------------------------------------|
| `evento`    | Identificador del evento (ver tabla más abajo)           |
| `timestamp` | Fecha/hora ISO 8601 en UTC                               |
| `tenant`    | Identificador de la clínica que originó el evento        |
| `version`   | Versión del formato del payload (actualmente `"1"`)      |
| `datos`     | Datos específicos del evento                             |

---

## Verificación de firma (seguridad)

Cada request incluye el header:

```
X-Signature: sha256=<hmac>
X-Webhook-Event: liquidacion.pagada
X-Webhook-Delivery: <uuid del delivery>
```

Para verificar que el webhook viene genuinamente del sistema y no fue alterado:

**PHP:**
```php
$secret    = 'tu-clave-secreta';
$body      = file_get_contents('php://input');
$signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

if (!hash_equals($signature, $_SERVER['HTTP_X_SIGNATURE'])) {
    http_response_code(401);
    exit('Firma inválida');
}
```

**Node.js:**
```js
const crypto = require('crypto');
const body   = req.body; // string raw, no parsed
const sig    = 'sha256=' + crypto.createHmac('sha256', SECRET).update(body).digest('hex');

if (!crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(req.headers['x-signature']))) {
    return res.status(401).send('Firma inválida');
}
```

**Python:**
```python
import hmac, hashlib
sig = 'sha256=' + hmac.new(SECRET.encode(), body, hashlib.sha256).hexdigest()
if not hmac.compare_digest(sig, request.headers.get('X-Signature', '')):
    return Response('Firma inválida', status=401)
```

> **Importante:** siempre usa una función de comparación en tiempo constante (`hash_equals`, `timingSafeEqual`, `compare_digest`) para evitar timing attacks.

---

## Eventos disponibles

| Evento                        | Cuándo se dispara                           | Grupo          |
|-------------------------------|---------------------------------------------|----------------|
| `turno.creado`                | Se registra un nuevo turno                  | Turnos         |
| `turno.completado`            | Un turno es marcado como completado         | Turnos         |
| `turno.descubierto`           | Un turno queda sin trabajador asignado      | Turnos         |
| `paciente.creado`             | Se da de alta un nuevo paciente             | Pacientes      |
| `paciente.estado_cambiado`    | Cambia el estado de un paciente             | Pacientes      |
| `liquidacion.generada`        | Se genera una nueva liquidación             | Liquidaciones  |
| `liquidacion.pagada`          | Una liquidación es marcada como pagada      | Liquidaciones  |
| `factura.emitida`             | Se emite una factura                        | Facturas       |
| `factura.pagada`              | Una factura es marcada como pagada          | Facturas       |
| `evento_adverso.creado`       | Se registra un evento adverso               | Eventos adv.   |

---

## Cómo agregar una integración desde la UI

1. Ir a **Administración → Integraciones** en el menú lateral (requiere `ROLE_ADMIN`).
2. Clic en **Nueva integración**.
3. Completar el formulario:
   - **Nombre**: identificador amigable (ej: "ERP RRHH Producción").
   - **URL del endpoint**: la URL donde tu sistema recibirá los webhooks.
   - **Clave secreta**: se genera automáticamente. Cópiala y guárdala en tu sistema externo para verificar la firma. *No se puede recuperar luego, pero sí regenerar.*
   - **Eventos**: marca solo los eventos que le interesan a ese sistema.
   - **Activa**: toggle para habilitar/deshabilitar sin borrar la configuración.
4. Clic en **Test de conexión** → el sistema envía un ping y muestra el HTTP code de respuesta.
5. **Crear integración**.

---

## Cómo agregar un nuevo evento (para desarrolladores)

Para disparar el webhook desde el código cuando ocurre algo nuevo, son **3 pasos**:

### Paso 1 — Declarar el evento en el Enum

Archivo: `app/src/Enum/WebhookEvento.php`

```php
// Agregar el case al enum
case TURNO_CANCELADO = 'turno.cancelado';

// Agregar la etiqueta legible
self::TURNO_CANCELADO => 'Turno cancelado',

// Agregar el grupo
self::TURNO_CANCELADO => 'Turnos',
```

### Paso 2 — Disparar el evento desde el servicio

En cualquier servicio donde ocurra la acción, inyectar `WebhookDispatcher` y llamar a `dispatch()`:

```php
use App\Enum\WebhookEvento;
use App\Service\WebhookDispatcher;

class TurnoService
{
    public function __construct(
        // ... tus otras dependencias
        private readonly WebhookDispatcher $webhookDispatcher,
    ) {}

    public function cancelarTurno(Turno $turno): void
    {
        // ... lógica de cancelación

        // Disparar el webhook
        $this->webhookDispatcher->dispatch(
            WebhookEvento::TURNO_CANCELADO,
            [
                'id'       => (string) $turno->getId(),
                'fecha'    => $turno->getFecha()->format('Y-m-d'),
                'paciente' => $turno->getPaciente()->getNombreCompleto(),
                'motivo'   => $turno->getMotivoCancelacion(),
            ],
            $turno->getTenant() // o el identificador del tenant actual
        );
    }
}
```

### Paso 3 — ¡Listo!

El sistema automáticamente:
- Busca todas las suscripciones activas que escuchan `turno.cancelado`.
- Crea un `WebhookDelivery` por cada una.
- Lo encola en Messenger para envío asíncrono.
- Reintenta hasta 3 veces si el endpoint falla.
- Registra todo en el log visible en **Administración → Integraciones → [nombre] → Log**.

No hay que tocar el handler, el dispatcher, ni las migraciones.

---

## Arquitectura interna

```
app/src/
├── Enum/
│   └── WebhookEvento.php           # Catálogo de eventos disponibles
│
├── Entity/Tenant/
│   ├── WebhookSuscripcion.php      # Una integración configurada (URL + secret + eventos)
│   └── WebhookDelivery.php         # Un intento de entrega (estado, intentos, respuesta)
│
├── Repository/
│   ├── WebhookSuscripcionRepository.php
│   └── WebhookDeliveryRepository.php
│
├── Service/
│   └── WebhookDispatcher.php       # Punto de entrada: dispatch(evento, datos, tenant)
│
├── Message/
│   └── WebhookDeliveryMessage.php  # Mensaje para Symfony Messenger (solo lleva el ID)
│
├── MessageHandler/
│   └── WebhookDeliveryHandler.php  # Worker: hace el HTTP POST, firma HMAC, maneja reintentos
│
├── Form/
│   └── WebhookSuscripcionType.php  # Formulario de la UI
│
└── Controller/
    └── IntegracionController.php   # Rutas de la UI de administración
```

### Tablas en base de datos (por tenant)

```sql
webhook_suscripciones
  id            UUID (PK)
  nombre        VARCHAR
  url           VARCHAR
  secret        VARCHAR
  eventos       JSON          -- array de strings: ["turno.creado", "factura.pagada"]
  activo        BOOLEAN
  creado_en     TIMESTAMP
  actualizado_en TIMESTAMP

webhook_deliveries
  id                UUID (PK)
  suscripcion_id    UUID (FK → webhook_suscripciones, CASCADE DELETE)
  evento            VARCHAR
  payload           JSON
  estado            VARCHAR     -- 'pendiente' | 'entregado' | 'fallido'
  intentos          INTEGER
  codigo_respuesta  INTEGER
  respuesta_body    TEXT
  ultimo_intento    TIMESTAMP
  creado_en         TIMESTAMP
```

---

## Reintentos y política de fallos

| Intento | Comportamiento                                          |
|---------|---------------------------------------------------------|
| 1       | Envío inmediato al encolar                              |
| 2       | Reintento automático por Symfony Messenger (backoff)    |
| 3       | Último reintento automático                             |
| 4+      | Estado `fallido` — reintento manual desde la UI         |

Un delivery en estado `fallido` o `pendiente` puede reintentarse manualmente desde **Log → botón Reintentar**. Esto resetea el contador de intentos y vuelve a encolar el mensaje.

---

## Respuesta esperada del endpoint

El sistema considera un delivery **exitoso** si el endpoint responde con cualquier código HTTP `2xx` (200, 201, 204, etc.).

Cualquier otro código (4xx, 5xx) o timeout (> 10 segundos) se cuenta como fallo y activa el mecanismo de reintentos.

> El cuerpo de la respuesta se guarda (hasta 1000 caracteres) y es visible en el log para debugging.

---

## Checklist para conectar un sistema externo

- [ ] Crear un endpoint en tu sistema que acepte `POST` con `Content-Type: application/json`
- [ ] Guardar la clave secreta en tu sistema (se muestra una sola vez al crearla)
- [ ] Verificar la firma `X-Signature` en cada request recibido
- [ ] Responder `200 OK` lo antes posible (< 10 s); procesar el evento de forma asíncrona si es necesario
- [ ] Configurar la integración en la UI marcando solo los eventos relevantes
- [ ] Usar el botón **Test de conexión** para verificar que el endpoint es alcanzable
- [ ] Monitorear el log de entregas para detectar fallos
