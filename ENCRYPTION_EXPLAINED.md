# Cómo funciona el cifrado de datos sensibles

## La idea simple

Antes, los datos sensibles se guardaban en texto plano en la base de datos:

```
pacientes.rut = "8.234.567-K"
```

Cualquier persona con acceso a la BD (administrador de servidor, backup filtrado, SQL injection) podía leer ese dato directamente.

Ahora se guardan así:

```
pacientes.rut      = "enc:def50200cd03a6334d942595de..."   ← ilegible sin la clave
pacientes.rut_hash = "23265a9b935bab343ef56a5e8f76..."     ← huella digital del RUT
```

La aplicación descifra el valor automáticamente al leerlo. Para el sistema y el usuario, todo se ve exactamente igual que antes.

---

## Las dos columnas: cifrado vs hash

### Columna `rut` — el dato cifrado

Usa **AES-256-GCM** (el mismo estándar que usan los bancos).

```
RUT original:   8.234.567-K
                    ↓  (cifrar con clave secreta)
En la BD:       enc:def50200cd03a6334d942595de4f8a...
                    ↓  (descifrar con clave secreta)
En la pantalla: 8.234.567-K   ← el usuario ve esto
```

El prefijo `enc:` le indica a la app que ese valor está cifrado y hay que descifrarlo antes de mostrarlo.

**Problema:** el cifrado usa un número aleatorio en cada operación. Entonces el mismo RUT `8.234.567-K` cifrado dos veces produce dos resultados diferentes:

```
Primera vez:  enc:def50200cd03a6334d942595...
Segunda vez:  enc:def50200ab91f3cc8e712047...
```

Esto es bueno para la seguridad, pero malo para la base de datos: ¿cómo sabe que ese RUT ya está registrado si cada vez se ve distinto?

Ahí entra el hash.

---

### Columna `rut_hash` — la huella digital

Usa **HMAC-SHA256**. A diferencia del cifrado, el hash:
- Siempre produce el **mismo resultado** para el mismo RUT
- Es **irreversible** — no se puede recuperar el RUT original a partir del hash
- Actúa como "firma única" del dato

```
RUT original:   8.234.567-K
                    ↓  (HMAC-SHA256 con clave secreta)
rut_hash:       c73c312ea54efb0944fde3fad7cf1081...   ← siempre igual para ese RUT
```

La base de datos pone un índice UNIQUE en esta columna. Así puede responder a la pregunta "¿este RUT ya existe?" sin necesidad de descifrar nada.

---

## Las tablas y columnas afectadas

### Tabla `pacientes`

| Columna | Qué guarda | Ejemplo en BD |
|---------|-----------|---------------|
| `rut` | RUT cifrado con AES-256 | `enc:def50200cd03a6...` |
| `rut_hash` | HMAC del RUT (para unicidad) | `c73c312ea54efb09...` |
| `diagnosticos` | Diagnósticos cifrados | `enc:def50200f8a1...` |
| `medicamentos` | Medicamentos cifrados | `enc:def50200b3c2...` |
| `observaciones` | Observaciones clínicas cifradas | `enc:def502009d4e...` |

### Tabla `trabajadores`

| Columna | Qué guarda | Ejemplo en BD |
|---------|-----------|---------------|
| `rut` | RUT cifrado con AES-256 | `enc:def50200a1b2...` |
| `rut_hash` | HMAC del RUT (para unicidad) | `23265a9b935bab34...` |
| `cuenta_bancaria` | Datos bancarios cifrados | `enc:def50200e5f6...` |
| `datos_previsionales` | AFP, isapre cifrados | `enc:def50200c7d8...` |

### Tabla `eventos_adversos`

| Columna | Qué guarda | Ejemplo en BD |
|---------|-----------|---------------|
| `descripcion` | Descripción del evento cifrada | `enc:def502001a2b...` |
| `acciones_tomadas` | Acciones clínicas tomadas cifradas | `enc:def502003c4d...` |

---

## El flujo completo: guardar y leer

### Al guardar un paciente nuevo

```
Usuario escribe:  RUT = "12.345.678-9"
                        ↓
EncryptionListener (prePersist):
  rut      ← encrypt("12.345.678-9")  →  "enc:def50200..."
  rut_hash ← hmac("12.345.678-9")     →  "a3f8c2e1..."
                        ↓
Se guarda en BD con los valores cifrados.
```

### Al leer un paciente

```
BD devuelve:  rut = "enc:def50200..."
                        ↓
EncryptionListener (postLoad):
  rut ← decrypt("enc:def50200...")  →  "12.345.678-9"
                        ↓
La app muestra:  "12.345.678-9"  ← el usuario ve el RUT normal
```

### Al verificar si un RUT ya existe

```
¿Existe el RUT "12.345.678-9"?
                        ↓
hmac("12.345.678-9") → "a3f8c2e1..."
                        ↓
SELECT * FROM pacientes WHERE rut_hash = "a3f8c2e1..."
                        ↓
Resultado sin necesidad de descifrar nada.
```

---

## La clave secreta (APP_ENCRYPTION_KEY)

Todo el sistema depende de esta clave. Sin ella, los datos cifrados son basura ilegible.

- Se genera con: `php bin/console app:security:generate-key`
- Se configura como variable de entorno: `APP_ENCRYPTION_KEY=def000...`
- **Nunca** se guarda en el repositorio git
- En producción (Render) se configura como variable de entorno secreta
- Si se pierde la clave, los datos cifrados **no se pueden recuperar**

### ¿Qué pasa si alguien roba la base de datos?

Sin la `APP_ENCRYPTION_KEY`, solo verá esto:

```sql
SELECT rut, diagnosticos FROM pacientes;

         rut                  |            diagnosticos
------------------------------+------------------------------------------
 enc:def50200cd03a6334d9...   |  enc:def50200f8a1b2c3d4e5f6a7b8c9d0...
 enc:def50200ab91f3cc8e7...   |  NULL
 enc:def50200966bb59fc87...   |  enc:def50200e1f2a3b4c5d6e7f8a9b0c1...
```

Los datos sensibles están completamente protegidos.

---

## Rotar la clave (cambiarla periódicamente)

Se recomienda cambiar la clave al menos una vez al año o si se sospecha que fue comprometida:

```bash
# 1. Generar una nueva clave
php bin/console app:security:generate-key
# → guarda la nueva clave

# 2. Dry-run para verificar que todo funciona
php bin/console app:security:rotate-key --old-key="clave_vieja" --dry-run

# 3. Ejecutar la rotación real
php bin/console app:security:rotate-key --old-key="clave_vieja"

# 4. Actualizar APP_ENCRYPTION_KEY con la nueva clave en el servidor
```

El comando descifra cada registro con la clave vieja y lo re-cifra con la nueva, sin downtime.
