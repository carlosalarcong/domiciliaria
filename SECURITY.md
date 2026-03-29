# Seguridad — Atención Domiciliaria

Implementación de seguridad de nivel clínico en 6 capas.

---

## Checklist de seguridad implementada

| # | Capa | Descripción | Estado |
|---|------|-------------|--------|
| 1 | **Cifrado AES-256-GCM** | Campos sensibles cifrados en BD con `defuse/php-encryption` | ✅ |
| 2 | **2FA TOTP** | Autenticación de doble factor con Google Authenticator / Authy | ✅ |
| 3 | **Hardening de sesiones** | Inactividad, bloqueo por IP, audit log | ✅ |
| 4 | **Security headers HTTP** | CSP, HSTS, X-Frame-Options, etc. en nginx | ✅ |
| 5 | **Backups cifrados** | pg_dump + AES-256 + S3, diario a las 3am | ✅ |
| 6 | **Rate limiting** | Protección contra fuerza bruta y abuso de API con Redis | ✅ |

---

## CAPA 1 — Cifrado de campos sensibles

### Campos cifrados en base de datos

| Entidad | Campo | Tipo |
|---------|-------|------|
| `Paciente` | `rut` | Cifrado AES-256-GCM + HMAC para unicidad |
| `Paciente` | `diagnosticos` | Cifrado AES-256-GCM |
| `Paciente` | `medicamentos` | Cifrado AES-256-GCM |
| `Paciente` | `observaciones` | Cifrado AES-256-GCM |
| `Trabajador` | `rut` | Cifrado AES-256-GCM + HMAC para unicidad |
| `Trabajador` | `cuenta_bancaria` | Cifrado AES-256-GCM |
| `Trabajador` | `datos_previsionales` | Cifrado AES-256-GCM |
| `EventoAdverso` | `descripcion` | Cifrado AES-256-GCM |
| `EventoAdverso` | `acciones_tomadas` | Cifrado AES-256-GCM |

El cifrado/descifrado es transparente para el resto del sistema — ocurre automáticamente via Doctrine Lifecycle Callbacks (`prePersist`, `preUpdate`, `postLoad`).

Los valores cifrados tienen el prefijo `enc:` en la base de datos. Los campos `rut_hash` almacenan un HMAC-SHA256 determinístico para mantener la constraint `UNIQUE` sin exponer el valor real.

### Generar la clave de cifrado

```bash
docker compose exec php sh -c "cd /var/www/html/app && php bin/console app:security:generate-key"
```

La clave generada tiene este formato: `def0000...` (88 caracteres). Configúrala como variable de entorno `APP_ENCRYPTION_KEY` — **nunca en el repositorio**.

### Rotar la clave de cifrado (sin downtime)

```bash
# Primero: dry-run para verificar
php bin/console app:security:rotate-key --old-key="clave_anterior" --dry-run

# Luego: ejecutar la rotación real
php bin/console app:security:rotate-key --old-key="clave_anterior"
```

El comando descifra todos los registros con la clave vieja y los re-cifra con la nueva (`APP_ENCRYPTION_KEY`).

### Verificar que el cifrado funciona

```bash
# El valor en BD debe mostrar "enc:def..."
docker compose exec postgres psql -U app -d clinica_demo \
  -c "SELECT rut, rut_hash FROM pacientes LIMIT 3;"

# La app debe mostrar el RUT en claro (descifrado automático)
# → Navegar a /pacientes y verificar que el RUT se muestra correctamente
```

---

## CAPA 2 — Autenticación de doble factor (2FA)

### Configuración

| Rol | 2FA |
|-----|-----|
| `ROLE_ADMIN` | **Obligatorio** |
| `ROLE_COORDINADOR` | **Obligatorio** |
| `ROLE_ENFERMERA` | Opcional |
| `ROLE_TENS` | Opcional |
| `ROLE_VISUALIZADOR` | Opcional |

### Apps compatibles

- Google Authenticator (iOS / Android)
- Authy
- Microsoft Authenticator
- Cualquier app compatible con TOTP (RFC 6238)

### Flujo de configuración

1. Primer login con rol ADMIN/COORDINADOR → redirige automáticamente a `/2fa/setup`
2. Escanear el código QR con la app autenticadora
3. Ingresar el código de 6 dígitos para confirmar
4. Se generan **10 códigos de respaldo** de un solo uso — guardarlos en lugar seguro

### Rutas

| Ruta | Descripción |
|------|-------------|
| `/2fa/check` | Formulario de verificación del código TOTP |
| `/2fa/setup` | Configurar 2FA por primera vez (QR) |
| `/2fa/enable` | Activar 2FA tras confirmar código |
| `/2fa/disable` | Desactivar 2FA (solo roles no forzados) |
| `/2fa/backup-codes/regenerar` | Regenerar códigos de respaldo |

### Códigos de respaldo

Cada usuario tiene 10 códigos de respaldo de 8 caracteres (ej: `A1B2C3D4`). Cada código solo puede usarse **una vez**. Se usan cuando no tienes acceso a la app autenticadora.

---

## CAPA 3 — Hardening de sesiones

### Inactividad

Las sesiones expiran tras **30 minutos de inactividad**. Al expirar, el usuario es redirigido al login con un mensaje informativo.

### Bloqueo por intentos fallidos

- **5 intentos fallidos** desde la misma IP → bloqueo de **15 minutos**
- El bloqueo se gestiona en Redis (sin consultar la BD)
- Los intentos se registran en la tabla `audit_logs`

### Audit log

Cada intento de login (exitoso o fallido) queda registrado con:
- Evento (`LOGIN_SUCCESS` / `LOGIN_FAILED`)
- IP del cliente
- Email intentado
- User agent
- Tenant (subdominio)
- Número de intentos acumulados

```sql
-- Ver últimos intentos fallidos
SELECT evento, email_intentado, ip_address, created_at, detalles
FROM audit_logs
WHERE evento = 'LOGIN_FAILED'
ORDER BY created_at DESC
LIMIT 20;
```

### Configuración de cookies de sesión

```
cookie_httponly: true     # Inaccesible desde JavaScript
cookie_samesite: strict   # Protección CSRF
cookie_secure: auto       # HTTPS en producción
gc_maxlifetime: 1800      # 30 minutos
```

Las sesiones se almacenan en **Redis** (no en archivos del servidor).

---

## CAPA 4 — Security headers HTTP

Configurados en nginx para todas las respuestas:

| Header | Valor |
|--------|-------|
| `X-Frame-Options` | `DENY` — evita clickjacking |
| `X-Content-Type-Options` | `nosniff` — evita MIME sniffing |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | Deshabilita geolocation, micrófono, cámara |
| `Content-Security-Policy` | Restringe fuentes de scripts, estilos, imágenes |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` (HSTS) |

> **Nota:** HSTS solo es efectivo cuando hay SSL/TLS configurado. En producción con HTTPS en Render, aplica automáticamente.

---

## CAPA 5 — Backups cifrados automáticos

### Programación

Backup automático diario a las **3:00 AM** para cada base de datos tenant.

### Flujo

1. `BackupSchedule` dispara `BackupDatabaseMessage` por cada tenant
2. `BackupDatabaseHandler` ejecuta `pg_dump`
3. El dump se cifra con `EncryptionService` (AES-256-GCM)
4. El archivo cifrado se sube a S3 como `backups/{tenant}/{fecha}.sql.enc`
5. El dump en texto plano se elimina **inmediatamente**
6. Se registra en log: fecha, tenant, tamaño, SHA-256 de verificación

### Variables de entorno requeridas para S3

```bash
AWS_S3_BUCKET=nombre-del-bucket
AWS_DEFAULT_REGION=us-east-1
AWS_ACCESS_KEY_ID=AKIAxxx
AWS_SECRET_ACCESS_KEY=xxx
```

> Si `AWS_S3_BUCKET` está vacío, el backup se genera y cifra localmente pero no se sube.

### Restaurar un backup

```bash
# 1. Descargar el .sql.enc desde S3
# 2. Descifrar con la clave de cifrado
php bin/console app:security:decrypt-file backup.sql.enc backup.sql

# 3. Restaurar en PostgreSQL
psql -U app -d clinica_demo < backup.sql
```

---

## CAPA 6 — Rate limiting

Implementado con `symfony/rate-limiter` y Redis como backend (sliding window).

| Endpoint | Límite | Por |
|----------|--------|-----|
| `/login` (POST) | 10 requests / minuto | IP |
| `/api/*` | 100 requests / minuto | Usuario autenticado |
| Endpoints de exportación | 5 requests / hora | Usuario autenticado |

Al superar el límite:
- `/login` → redirige a `/login?rate_limited=1`
- `/api/*` → `HTTP 429 Too Many Requests` con JSON
- Exportaciones → `HTTP 429 Too Many Requests` con JSON

---

## Variables de entorno nuevas para configurar en Render

```bash
# OBLIGATORIA — cifrado de datos clínicos sensibles
APP_ENCRYPTION_KEY=<generar con php bin/console app:security:generate-key>

# OPCIONALES — para backups cifrados automáticos en S3
AWS_S3_BUCKET=nombre-del-bucket
AWS_DEFAULT_REGION=us-east-1
AWS_ACCESS_KEY_ID=AKIAxxx
AWS_SECRET_ACCESS_KEY=xxx
```

> `APP_ENCRYPTION_KEY` es la variable más crítica del sistema. Sin ella la app no arranca. Genera una clave única por entorno (dev, staging, prod) y **nunca** la compartas ni la commitees en git.

---

## Consideraciones adicionales para producción

- Configurar SSL/TLS en Render para que HSTS y `cookie_secure` sean efectivos
- Habilitar `APP_ENV=prod` para deshabilitar el profiler y los mensajes de error detallados
- Configurar `MAILER_DSN` real para que el reset de contraseña funcione
- Revisar los `audit_logs` periódicamente para detectar patrones de ataque
- Rotar `APP_ENCRYPTION_KEY` y `APP_SECRET` al menos una vez al año
