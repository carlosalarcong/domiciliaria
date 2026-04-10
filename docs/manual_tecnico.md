# Manual Técnico — Domiciliaria SaaS

> Sistema de gestión de atención domiciliaria de salud, multi-tenant, desarrollado en Symfony 7.4 / PHP 8.3.
> Versión del documento: abril 2026

---

## Tabla de contenidos

1. [Arquitectura general](#1-arquitectura-general)
2. [Infraestructura y servicios](#2-infraestructura-y-servicios)
3. [Modelo de datos](#3-modelo-de-datos)
4. [Flujos de estado](#4-flujos-de-estado)
5. [API REST](#5-api-rest)
6. [Mensajería asíncrona](#6-mensajería-asíncrona)
7. [Webhooks](#7-webhooks)
8. [Seguridad y autenticación](#8-seguridad-y-autenticación)
9. [Multi-tenancy](#9-multi-tenancy)
10. [Exportaciones](#10-exportaciones)

---

## 1. Arquitectura general

```mermaid
graph TD
    Browser["Navegador / PWA"]
    Nginx["Nginx :8090"]
    PHP["PHP-FPM 8.3\n(Symfony 7.4)"]
    Worker["php-worker\n(messenger:consume)"]
    Postgres["PostgreSQL 16"]
    Redis["Redis 7"]
    Mailer["Mailpit (dev)\n/ SMTP (prod)"]

    Browser -->|HTTP/HTTPS| Nginx
    Nginx -->|FastCGI :9000| PHP
    PHP -->|Doctrine ORM| Postgres
    PHP -->|Symfony Messenger| Redis
    Redis --> Worker
    Worker -->|Doctrine ORM| Postgres
    PHP -->|Mailer| Mailer
    Worker -->|Mailer| Mailer
```

### Capas de la aplicación

```mermaid
graph LR
    subgraph Presentación
        Ctrl["Controllers\n(HTTP layer)"]
        Twig["Twig Templates\n(Bootstrap 5.3)"]
    end
    subgraph Aplicación
        Svc["Services\n(TurnoService, ExportService…)"]
        Form["Symfony Forms"]
        Msg["Messages + Handlers\n(Messenger)"]
    end
    subgraph Dominio
        Entity["Entities\n(Tenant / Main)"]
        Enum["Enums (PHP 8.1+)"]
        Repo["Repositories"]
    end
    subgraph Infraestructura
        ORM["Doctrine ORM\n(2 Entity Managers)"]
        PG["PostgreSQL 16"]
        RD["Redis (transport)"]
    end

    Ctrl --> Svc
    Ctrl --> Form
    Svc --> Repo
    Svc --> Msg
    Msg --> RD
    Repo --> ORM
    ORM --> PG
    Ctrl --> Twig
```

---

## 2. Infraestructura y servicios

### Docker Compose (desarrollo)

| Servicio | Imagen | Puerto expuesto | Rol |
|----------|--------|-----------------|-----|
| `php` | Custom Dockerfile | — | PHP-FPM, aplicación principal |
| `nginx` | nginx:alpine | **8090 → 80** | Reverse proxy / servidor web |
| `postgres` | postgres:16 | 5432 | Base de datos principal + tenants |
| `redis` | redis:alpine | 6379 | Transport de mensajes + cache |
| `php-worker` | Custom Dockerfile | — | Consumidor de cola asíncrona |
| `mailpit` | axllent/mailpit:latest | **8025** (UI) | Captura de emails en desarrollo |

### Variables de entorno clave

| Variable | Ejemplo (dev) | Descripción |
|----------|--------------|-------------|
| `DATABASE_URL` | `postgresql://app:secret@postgres:5432/domiciliaria` | BD principal (tenants + gestión) |
| `REDIS_URL` | `redis://redis:6379` | Cola de mensajes |
| `MAILER_DSN` | `smtp://mailpit:1025` | Transporte SMTP |
| `TENANT_DB_HOST` | `postgres` | Host BD de tenants |
| `TENANT_DB_USER` | `app` | Usuario BD de tenants |
| `TENANT_DB_PASSWORD` | `secret` | Contraseña BD de tenants |
| `APP_SECRET` | (aleatorio 32 bytes) | Llave de sesión / CSRF |
| `TOTP_ENCRYPTION_KEY` | (base64 32 bytes) | Cifrado secretos 2FA |

---

## 3. Modelo de datos

### Diagrama entidad-relación completo

```mermaid
erDiagram
    TenantDb {
        int id PK
        string databaseName
        string slug UK
        string name
        bool isActive
    }

    User {
        uuid id PK
        string email UK
        string nombre
        string apellido
        json roles
        bool twoFactorEnabled
        json toursCompletados
    }

    Mandante {
        uuid id PK
        string nombre
        string rut UK
        string contacto
        string email
        bool activo
    }

    Paciente {
        uuid id PK
        string nombres
        string apellidos
        string rut
        enum tipoServicio
        enum estado
        date fechaIngreso
        date fechaTermino
        uuid mandante_id FK
    }

    Trabajador {
        uuid id PK
        string nombres
        string apellidos
        string rut
        enum perfil
        enum estado
        date fechaIngreso
        uuid user_id FK
    }

    Turno {
        uuid id PK
        date fecha
        time horaInicio
        time horaTermino
        enum tipoTurno
        enum estado
        bool esReemplazo
        enum motivoReemplazo
        datetime registroInicio
        datetime registroTermino
        uuid paciente_id FK
        uuid trabajador_id FK
        uuid turnoOriginal_id FK
        uuid creadoPor_id FK
    }

    EventoAdverso {
        uuid id PK
        date fechaEvento
        enum tipo
        enum gravedad
        enum estado
        text descripcion
        bool notificadoFamilia
        bool notificadoMedico
        uuid paciente_id FK
        uuid trabajador_id FK
        uuid responsable_id FK
    }

    SeguimientoEvento {
        uuid id PK
        text observacion
        datetime creadoEn
        uuid evento_id FK
        uuid autor_id FK
    }

    LiquidacionMensual {
        uuid id PK
        smallint anio
        smallint mes
        decimal totalHoras
        decimal montoTotal
        enum estado
        uuid trabajador_id FK
    }

    ItemLiquidacion {
        int id PK
        enum concepto
        text descripcion
        decimal cantidad
        decimal valorUnitario
        decimal subtotal
        uuid liquidacion_id FK
        uuid turno_id FK
    }

    Factura {
        uuid id PK
        string numeroFactura
        smallint anio
        smallint mes
        decimal montoNeto
        decimal montoIva
        decimal montoTotal
        enum estado
        date fechaEmision
        uuid mandante_id FK
    }

    Tarifa {
        uuid id PK
        enum tipoConcepto
        decimal valorUnitario
        date vigenciaDesde
        date vigenciaHasta
        bool activa
        uuid mandante_id FK
    }

    Notificacion {
        uuid id PK
        string tipo
        string titulo
        text cuerpo
        datetime leidaEn
        uuid destinatario_id FK
    }

    WebhookSuscripcion {
        uuid id PK
        string nombre
        string url
        string secret
        json eventos
        bool activo
    }

    WebhookDelivery {
        uuid id PK
        string evento
        json payload
        string estado
        int intentos
        uuid suscripcion_id FK
    }

    Mandante ||--o{ Paciente : "atiende"
    Mandante ||--o{ Factura : "facturado a"
    Mandante ||--o{ Tarifa : "tarifas de"
    Paciente ||--o{ Turno : "turnos de"
    Trabajador ||--o{ Turno : "cubre"
    Turno ||--o{ Turno : "reemplaza"
    Trabajador ||--o{ LiquidacionMensual : "liquidado en"
    LiquidacionMensual ||--o{ ItemLiquidacion : "contiene"
    Turno ||--o{ ItemLiquidacion : "referenciado"
    Paciente ||--o{ EventoAdverso : "involucra"
    Trabajador ||--o{ EventoAdverso : "reportado por"
    EventoAdverso ||--o{ SeguimientoEvento : "seguido en"
    User ||--o{ Notificacion : "recibe"
    WebhookSuscripcion ||--o{ WebhookDelivery : "entregado en"
```

### Descripción de entidades principales

#### Paciente

Representa al beneficiario del servicio domiciliario. Almacena datos personales, diagnósticos, medicamentos, condición del domicilio (relación 1:1 con `CondicionDomicilio`), bitácora operativa e historial de comunicaciones.

**Tipo de servicio** determina el modelo de atención:

| Valor | Descripción |
|-------|-------------|
| `TURNO_12H` | Turno de 12 horas (día o noche) |
| `TURNO_24H` | Turno continuo de 24 horas |
| `VISITA` | Visita puntual (aprox. 2 horas) |

#### Trabajador

Personal sanitario. Perfil: `TENS`, `CUIDADOR`, `ENFERMERA`, `OTRO`. Puede tener un `User` asociado para acceso al sistema. Gestiona disponibilidades, documentos y horas trabajadas.

#### Turno

Unidad central del sistema. Un turno vincula un `Paciente` con un `Trabajador` en una fecha y rango horario. Puede ser reemplazo (`esReemplazo = true`), con referencia al turno original.

#### LiquidacionMensual

Consolida todos los turnos de un trabajador en un mes. Restricción única: `(trabajador_id, anio, mes)`. Los ítems desglosan conceptos (horas ordinarias, extras, bonificaciones, descuentos).

#### Factura

Documento de cobro emitido a un `Mandante` por los servicios del período. Incluye IVA configurable (default 19%).

---

## 4. Flujos de estado

### Turno

```mermaid
stateDiagram-v2
    [*] --> DESCUBIERTO : crear sin trabajador
    [*] --> CUBIERTO : crear con trabajador asignado
    CUBIERTO --> PARCIAL : registrar inicio de turno
    PARCIAL --> COMPLETADO : registrar término de turno
    DESCUBIERTO --> CUBIERTO : asignar reemplazo
    CUBIERTO --> DESCUBIERTO : (reemplazo cancelado)
```

| Estado | Descripción |
|--------|-------------|
| `DESCUBIERTO` | Sin trabajador asignado |
| `CUBIERTO` | Trabajador asignado, turno pendiente |
| `PARCIAL` | Trabajador registró inicio; turno en curso |
| `COMPLETADO` | Trabajador registró término; turno finalizado |

### Liquidación mensual

```mermaid
stateDiagram-v2
    [*] --> BORRADOR : crear nueva liquidación
    BORRADOR --> APROBADA : aprobar (FINANZAS_EDITAR)
    APROBADA --> PAGADA : marcar como pagada
    BORRADOR --> ANULADA : anular
    APROBADA --> ANULADA : anular
```

### Factura

```mermaid
stateDiagram-v2
    [*] --> BORRADOR : crear borrador
    BORRADOR --> EMITIDA : emitir (asigna fecha emisión)
    EMITIDA --> PAGADA : registrar pago
    EMITIDA --> VENCIDA : fecha vencimiento superada
    BORRADOR --> ANULADA : anular
    EMITIDA --> ANULADA : anular
```

### Evento adverso

```mermaid
stateDiagram-v2
    [*] --> ABIERTO : registrar evento
    ABIERTO --> EN_PROCESO : mover a revisión
    EN_PROCESO --> CERRADO : cerrar con observación
    ABIERTO --> CERRADO : cerrar directamente
```

---

## 5. API REST

Todos los endpoints requieren autenticación (sesión o `ApiToken`). Los endpoints `/api/*` dentro del controlador de turnos aceptan opcionalmente API tokens via header `Authorization: Bearer <token>`.

### Turnos

| Método | Ruta | Autenticación | Descripción |
|--------|------|---------------|-------------|
| `GET` | `/turnos` | ROLE_COORDINADOR | Vista del calendario (index) |
| `GET` | `/turnos/exportar` | ROLE_COORDINADOR | Exportar turnos a CSV |
| `GET,POST` | `/turnos/nuevo` | ROLE_COORDINADOR | Crear turno |
| `GET` | `/turnos/{id}` | ROLE_COORDINADOR | Detalle del turno |
| `GET,POST` | `/turnos/{id}/editar` | ROLE_COORDINADOR | Editar turno |
| `GET,POST` | `/turnos/{id}/reemplazo` | ROLE_COORDINADOR | Asignar reemplazo |
| `POST` | `/turnos/{id}/asistencia/{tipo}` | ROLE_COORDINADOR | Registrar inicio/término |
| `GET` | `/turnos/api/eventos` | ROLE_COORDINADOR | Eventos para FullCalendar |
| `GET` | `/turnos/api/disponibilidad` | ROLE_COORDINADOR | Verificar disponibilidad |
| `GET` | `/turnos/api/kpis` | ROLE_COORDINADOR | KPIs del dashboard |

**GET `/turnos/api/eventos`** — parámetros:

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `start` | string (fecha) | Inicio del rango (default: hoy) |
| `end` | string (fecha) | Fin del rango (default: +30 días) |
| `trabajadorId` | uuid | Filtrar por trabajador |
| `estado` | string | Filtrar por estado del turno |
| `tipo` | string | Filtrar por tipo de turno |

**Respuesta eventos:**
```json
[
  {
    "id": "uuid",
    "title": "Apellido P. · Paciente",
    "start": "2026-04-09T08:00:00",
    "end": "2026-04-09T20:00:00",
    "color": "#198754",
    "extendedProps": {
      "estado": "CUBIERTO",
      "tipo": "T12H_DIA",
      "iniciales": "AP",
      "trabajadorId": "uuid"
    }
  }
]
```

**GET `/turnos/api/kpis`** — respuesta:
```json
{
  "hoy_total": 12,
  "hoy_descubiertos": 2,
  "hoy_completados": 5,
  "descubiertos_7d": 8,
  "completados_mes": 147
}
```

**GET `/turnos/api/disponibilidad`** — parámetros:

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `trabajador` | uuid | ID del trabajador |
| `fecha` | string (fecha) | Fecha del turno |
| `horaInicio` | string (H:i) | Hora de inicio |
| `horaTermino` | string (H:i) | Hora de término |
| `exclude` | uuid | ID de turno a excluir (para edición) |

**Respuesta:**
```json
{ "disponible": true }
// o
{ "disponible": false, "mensaje": "El trabajador ya tiene turno entre 08:00 y 20:00." }
```

### Pacientes

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/pacientes` | Listar con filtros (estado, mandante, tipo) |
| `GET` | `/pacientes/exportar` | CSV con filtros aplicados |
| `GET,POST` | `/pacientes/nuevo` | Crear paciente |
| `GET` | `/pacientes/{id}` | Ficha completa con tabs |
| `GET,POST` | `/pacientes/{id}/editar` | Editar datos |
| `POST` | `/pacientes/{id}/bitacora` | Agregar entrada de bitácora |
| `POST` | `/pacientes/{id}/dar-de-baja` | Dar de baja al paciente |

### Finanzas

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/finanzas` | Dashboard financiero con KPIs |
| `GET` | `/finanzas/liquidaciones` | Listar liquidaciones |
| `GET,POST` | `/finanzas/liquidaciones/nueva` | Crear liquidación |
| `POST` | `/finanzas/liquidaciones/{id}/aprobar` | Aprobar liquidación |
| `POST` | `/finanzas/liquidaciones/{id}/pagar` | Marcar como pagada |
| `GET` | `/finanzas/liquidaciones/{id}/exportar` | CSV individual |
| `GET` | `/finanzas/liquidaciones/exportar-buk` | CSV formato Buk por mes |
| `GET` | `/finanzas/facturas` | Listar facturas |
| `GET,POST` | `/finanzas/facturas/nueva` | Crear factura |
| `POST` | `/finanzas/facturas/{id}/emitir` | Emitir factura |
| `POST` | `/finanzas/facturas/{id}/pagar` | Registrar pago |

---

## 6. Mensajería asíncrona

El sistema usa **Symfony Messenger** con transport Redis para procesamiento asíncrono.

### Diagrama de secuencia — Notificación de turno descubierto

```mermaid
sequenceDiagram
    participant C as Controller
    participant S as TurnoService
    participant B as MessageBus
    participant R as Redis
    participant W as php-worker
    participant H as TurnoDescubiertoHandler
    participant DB as PostgreSQL
    participant M as Mailer

    C->>S: crear(turno, user)
    S->>S: verificar disponibilidad
    S->>DB: persist + flush
    S->>B: dispatch(TurnoDescubiertoMessage)
    B->>R: enqueue mensaje
    Note over C: responde HTTP inmediatamente

    W->>R: consume async
    R-->>W: TurnoDescubiertoMessage
    W->>H: handle(message)
    H->>DB: cargar turno + coordinadores
    H->>DB: crear Notificacion[] para coordinadores
    H->>M: enviar email a coordinadores
    DB-->>H: ok
    M-->>H: ok
```

### Mensajes definidos

| Mensaje | Handler | Acción |
|---------|---------|--------|
| `TurnoDescubiertoMessage` | `TurnoDescubiertoHandler` | Notifica coordinadores de turno sin cubrir |
| `TurnoProximoRecordatorioMessage` | `TurnoProximoRecordatorioHandler` | Recordatorio 24h antes del turno |
| `EventoAdversoNuevoMessage` | `EventoAdversoNuevoHandler` | Alerta a coordinadores de evento adverso |
| `LiquidacionAprobadaMessage` | `LiquidacionAprobadaHandler` | Notifica al trabajador su liquidación aprobada |
| `FacturaEmitidaMessage` | `FacturaEmitidaHandler` | Envía factura al contacto del mandante |
| `WebhookDispatchMessage` | `WebhookDispatchHandler` | Entrega payload a suscriptores externos |
| `BackupDatabaseMessage` | `BackupDatabaseHandler` | Backup nocturno de BD del tenant |
| `VerificarTurnosDescubiertosMessage` | `VerificarTurnosDescubiertosHandler` | Escaneo diario de turnos sin cubrir |

### Tareas programadas (Scheduler)

| Hora | Tarea | Descripción |
|------|-------|-------------|
| `20:00` diario | `VerificarTurnosDescubiertosMessage` | Revisa turnos del día siguiente sin trabajador asignado |
| `03:00` diario | `BackupDatabaseMessage` | Copia de seguridad de la BD del tenant activo |

---

## 7. Webhooks

El sistema permite a clientes externos suscribirse a eventos mediante webhooks.

### Diagrama de secuencia — Entrega de webhook

```mermaid
sequenceDiagram
    participant App as Aplicación
    participant Bus as MessageBus
    participant Redis as Redis
    participant WH as WebhookDispatchHandler
    participant DB as PostgreSQL
    participant Ext as Servicio externo

    App->>Bus: dispatch(WebhookDispatchMessage, evento, payload)
    Bus->>Redis: enqueue

    WH->>DB: cargar suscripciones activas para el evento
    loop por cada suscripción
        WH->>Ext: POST url con payload firmado (HMAC-SHA256)
        alt respuesta 2xx
            WH->>DB: WebhookDelivery estado=entregado
        else error / timeout
            WH->>DB: WebhookDelivery estado=fallido, intentos++
            Note over WH: retry con backoff exponencial
        end
    end
```

### Firma del payload

Cada request incluye el header `X-Webhook-Signature: sha256=<hmac>` calculado con el `secret` de la suscripción. Los receptores deben verificar esta firma.

### Eventos disponibles

| Evento (`WebhookEvento`) | Cuándo se dispara |
|--------------------------|-------------------|
| `turno.creado` | Al crear un turno |
| `turno.descubierto` | Turno sin trabajador asignado |
| `turno.completado` | Turno finalizado |
| `evento_adverso.nuevo` | Nuevo evento adverso registrado |
| `liquidacion.aprobada` | Liquidación aprobada |
| `factura.emitida` | Factura emitida al mandante |

---

## 8. Seguridad y autenticación

### Flujo de autenticación

```mermaid
sequenceDiagram
    participant U as Usuario
    participant Sym as Symfony Security
    participant DB as PostgreSQL (tenant)
    participant TOTP as TOTP (Google Auth)

    U->>Sym: POST /login (email + password)
    Sym->>DB: cargar User por email
    DB-->>Sym: User entity
    Sym->>Sym: verificar password hash (bcrypt)
    alt 2FA habilitado
        Sym-->>U: redirect a /2fa
        U->>Sym: POST /2fa (código TOTP)
        Sym->>TOTP: verificar código (ventana ±30s)
        TOTP-->>Sym: válido
    end
    Sym-->>U: sesión iniciada (redirect a /)
```

### Roles y permisos

| Rol / Permiso | Descripción |
|---------------|-------------|
| `ROLE_USER` | Acceso básico (ver dashboard, registrar bitácoras) |
| `ROLE_COORDINADOR` | Gestión operativa completa (turnos, pacientes, trabajadores) |
| `ROLE_ADMIN` | Administración de usuarios, tarifas, configuración del tenant |
| `FINANZAS_VER` | Ver reportes, liquidaciones y facturas |
| `FINANZAS_EDITAR` | Crear, aprobar y exportar documentos financieros |
| `PACIENTE_CREAR` | Crear nuevos pacientes |
| `PACIENTE_EDITAR` | Modificar datos de pacientes |
| `PACIENTE_VER_OPERATIVO` | Ver ficha completa del paciente |

Los roles jerárquicos no se aplican automáticamente; cada acción se protege con `#[IsGranted]` o `voters` explícitos.

### Seguridad HTTP (NelmioSecurityBundle)

- **Content-Security-Policy (CSP)**: restringe scripts a `self` + CDNs whitelisted (Bootstrap, FullCalendar, Driver.js)
- **X-Frame-Options**: DENY
- **X-Content-Type-Options**: nosniff
- **Referrer-Policy**: strict-origin-when-cross-origin
- **HSTS**: configurado en Nginx en producción
- **CSRF**: tokens Symfony en todos los formularios POST
- **TOTP 2FA**: secreto cifrado con `TOTP_ENCRYPTION_KEY` (AES-256)
- **Códigos de respaldo**: 10 códigos de un solo uso para recuperación 2FA

---

## 9. Multi-tenancy

El sistema implementa multi-tenancy a nivel de base de datos: cada clínica (tenant) tiene su propia base de datos PostgreSQL. La BD principal (`domiciliaria`) contiene solo la tabla `tenant_db`.

### Resolución del tenant

```mermaid
sequenceDiagram
    participant Req as Request HTTP
    participant Mid as TenantMiddleware
    participant Main as BD Principal
    participant Conn as TenantConnection
    participant TDB as BD Tenant

    Req->>Mid: subdominio: clinica-abc.domiciliaria.cl
    Mid->>Main: SELECT * FROM tenant_db WHERE slug='clinica-abc'
    Main-->>Mid: TenantDb (databaseName='db_clinica_abc')
    Mid->>Conn: switchDatabase('db_clinica_abc')
    Conn->>TDB: conectar a PostgreSQL db_clinica_abc
    Note over Conn: todas las queries siguientes\nusan este EntityManager
```

### Entity Managers

| Nombre | Alias en DI | Base de datos | Entidades |
|--------|-------------|---------------|-----------|
| `default` | `EntityManagerInterface` | `domiciliaria` | `TenantDb` |
| `doctrine.orm.tenant_entity_manager` | `#[Autowire(...)]` | BD del tenant activo | Todas las demás |

Para inyectar el EM del tenant en un controlador o servicio:

```php
public function __construct(
    #[Autowire(service: 'doctrine.orm.tenant_entity_manager')]
    private readonly EntityManagerInterface $em,
) {}
```

### Crear un nuevo tenant

Ver `docs/NUEVO_TENANT.md` para el proceso completo. Resumen:

1. Crear registro en `tenant_db` (tabla principal)
2. Crear base de datos PostgreSQL con el nombre especificado
3. Ejecutar migraciones: `php bin/console doctrine:migrations:migrate --em=tenant --db=<nombre>`
4. Crear usuario admin inicial

---

## 10. Exportaciones

### Formatos disponibles

| Módulo | Formato | Endpoint |
|--------|---------|----------|
| Turnos | CSV | `GET /turnos/exportar?desde=&hasta=` |
| Pacientes | CSV | `GET /pacientes/exportar?estado=&mandante=&tipo=` |
| Trabajador — horas | CSV | `GET /trabajadores/{id}/horas/exportar` |
| Liquidación individual | CSV | `GET /finanzas/liquidaciones/{id}/exportar` |
| Liquidaciones — Buk | CSV formato Buk | `GET /finanzas/liquidaciones/exportar-buk?anio=&mes=` |
| Facturas | CSV | `GET /finanzas/facturas/exportar?anio=&estado=` |

### Formato CSV Buk

El export Buk (`ExportService::exportarLiquidacionesBuk()`) genera el CSV compatible con el sistema de remuneraciones Buk. Reglas especiales:

- **Turnos 24H**: se generan dos filas (día + noche)
- **Descuentos** (`TipoConcepto::DESCUENTO`): no se exportan a Buk
- **RUT**: normalizado (sin puntos, con guión) para el formato chileno
- **Apellidos**: separados en `apellidoPaterno` y `apellidoMaterno`

Columnas del CSV: `rut`, `apellidoPaterno`, `apellidoMaterno`, `nombres`, `concepto`, `cantidad`, `valorUnitario`, `total`

### Normalización de nombres de archivo

Los nombres de archivo CSV se normalizan con `iconv()` para eliminar tildes y caracteres especiales, garantizando compatibilidad en todos los sistemas operativos.

---

## Apéndice A — Convenciones de código

- **Namespaces de entidades tenant**: `App\Entity\Tenant\*`
- **Namespaces de entidades main**: `App\Entity\Main\*`
- **Controladores**: un controlador por módulo, rutas con prefijo `app_{modulo}_`
- **Formularios**: `App\Form\{Entidad}Type`
- **Servicios de dominio**: `App\Service\{Nombre}Service`
- **Mensajes**: `App\Message\{Nombre}Message`
- **Handlers**: `App\MessageHandler\{Nombre}Handler`
- **Tests**: `PHPUnit 12`, usar `createStub()` para dependencias sin expectativas

## Apéndice B — Comandos útiles

```bash
# Lanzar entorno de desarrollo
docker compose up -d

# Ejecutar migraciones (BD principal)
docker compose exec php php bin/console doctrine:migrations:migrate

# Ejecutar migraciones (BD tenant)
docker compose exec php php bin/console doctrine:migrations:migrate --em=tenant

# Consumir mensajes manualmente
docker compose exec php php bin/console messenger:consume async -vv

# Crear un nuevo tenant
docker compose exec php php bin/console app:tenant:create <slug> <nombre>

# Limpiar caché
docker compose exec php php bin/console cache:clear

# Ejecutar tests
docker compose exec php php bin/phpunit

# Ver logs en tiempo real
docker compose logs -f php
docker compose logs -f php-worker
```
