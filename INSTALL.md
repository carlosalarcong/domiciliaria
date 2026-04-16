# Guía de Instalación

Sistema de Atención Domiciliaria — Symfony 7.4 + PostgreSQL 16 + Multi-tenant

---

## Requisitos previos

| Herramienta | Versión mínima | Notas |
|-------------|----------------|-------|
| Docker Desktop | 4.x | Incluye Docker Compose v2 |
| Git | 2.x | — |

No se requiere PHP, Composer ni PostgreSQL instalados localmente. Todo corre dentro de los contenedores.

---

## Instalación rápida (recomendado)

Los pasos 3 a 6 están automatizados en `setup.sh`. Es seguro re-ejecutarlo — detecta qué ya está hecho y lo omite.

```bash
# 1. Clonar
git clone https://github.com/carlosalarcong/domiciliaria.git
cd domiciliaria

# 2. Editar /etc/hosts (ver sección más abajo)

# 3–6. Automatizado
./setup.sh
```

Al terminar, el script indica los comandos exactos para crear clínicas y cargar datos (pasos 7 y 8).

> **Windows:** ejecutar desde Git Bash o WSL2. En PowerShell usar `bash setup.sh`.

---

## Instalación paso a paso

---

## 1. Clonar el repositorio

```bash
git clone https://github.com/carlosalarcong/domiciliaria.git
cd domiciliaria
```

---

## 2. Configurar el archivo de hosts

Cada clínica se identifica por subdominio. Es necesario mapear los subdominios de prueba a `127.0.0.1`.

### Windows

Abrir el archivo `C:\Windows\System32\drivers\etc\hosts` como **Administrador** y agregar al final:

```
127.0.0.1  demo.localhost
127.0.0.1  norte.localhost
```

> Para editar el archivo: clic derecho en el Bloc de Notas → "Ejecutar como administrador" → Archivo → Abrir → navegar a `C:\Windows\System32\drivers\etc\hosts` (seleccionar "Todos los archivos").

### Linux / macOS

```bash
sudo sh -c 'echo "127.0.0.1  demo.localhost" >> /etc/hosts'
sudo sh -c 'echo "127.0.0.1  norte.localhost" >> /etc/hosts'
```

---

## 3. Levantar los contenedores

```bash
docker compose up -d --build
```

Esto construye la imagen PHP y levanta los 7 servicios:

| Servicio | Imagen | Puerto expuesto | Rol |
|----------|--------|-----------------|-----|
| `php` | PHP 8.3-FPM + extensiones | interno | Aplicación principal |
| `nginx` | nginx:alpine | `8090 → 80` | Reverse proxy |
| `postgres` | postgres:16 | `5432` | Base de datos |
| `redis` | redis:alpine | `6379` | Cola de mensajes |
| `php-worker` | PHP 8.3-FPM | interno | Consumidor de mensajes async |
| `php-scheduler` | PHP 8.3-FPM | interno | Tareas programadas (cron interno) |
| `mailpit` | axllent/mailpit:latest | `8025` (UI) | Captura de emails en desarrollo |

Verificar que todos los contenedores estén en estado `Up`:

```bash
docker compose ps
```

---

## 4. Instalar dependencias PHP

```bash
docker exec domiciliaria-php-1 bash -c "cd /var/www/html/app && composer install --no-plugins"
```

> El flag `--no-plugins` evita problemas si el entorno tiene inspección SSL corporativa. En entornos sin proxy SSL se puede omitir.

---

## 5. Ejecutar migraciones de la base de datos central

La BD central (`domiciliaria`) almacena el registro de clínicas (tabla `tenant_db`).

```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console doctrine:migrations:migrate --no-interaction"
```

---

## 6. Configurar la clave de cifrado

El sistema cifra datos sensibles (RUTs, diagnósticos, medicamentos, observaciones, datos bancarios) usando AES-256-GCM. **La clave debe generarse antes de crear tenants o cargar datos.**

### Generar la clave

```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:security:generate-key"
```

La salida muestra una clave con formato `def0000...` (88 caracteres).

### Configurar la clave localmente

Crear el archivo `app/.env.local` (no se sube al repositorio):

```bash
# app/.env.local
APP_ENCRYPTION_KEY=def0000...  ← pegar la clave generada
```

> **Importante:** Sin esta clave la app no arranca. Guárdala en un lugar seguro — si se pierde, los datos cifrados no se pueden recuperar.

---

## 7. Crear las clínicas de demo

El comando `app:tenant:crear` realiza tres acciones en una sola operación:
1. Registra la clínica en la BD central
2. Crea la base de datos PostgreSQL de la clínica
3. Aplica el schema completo via migración

```bash
# Clínica Demo — Región Metropolitana
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:crear 'Clínica Demo' demo"

# Clínica Norte — Antofagasta
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:crear 'Clínica Norte' norte"
```

Resultado esperado para cada clínica:

```
 [OK] Tenant creado correctamente.

 Campo        Valor
 ID           1
 Nombre       Clínica Demo
 Subdominio   demo
 BD           clinica_demo
 URL local    http://demo.localhost:8090
```

---

## 8. Cargar datos de prueba

Carga usuarios, pacientes, mandantes, trabajadores, turnos, liquidaciones y facturas de ejemplo en cada clínica. **Purga los datos existentes antes de insertar.**

```bash
# Clínica Demo (ID=1)
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load 1 --no-interaction"

# Clínica Norte (ID=2)
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load 2 --no-interaction"
```

---

## 9. Acceder al sistema

| Clínica | URL de ingreso |
|---------|---------------|
| Clínica Demo | http://demo.localhost:8090/login |
| Clínica Norte | http://norte.localhost:8090/login |
| Bandeja de emails (Mailpit) | http://localhost:8025 |

---

## 10. Usuarios de prueba

Cada clínica tiene sus propios usuarios, completamente aislados.

### Clínica Demo (`clinica_demo`)

| Email | Contraseña | Rol |
|-------|-----------|-----|
| `admin@clinica-demo.cl` | `admin1234` | Administrador |
| `coordinador@clinica-demo.cl` | `coord1234` | Coordinador |
| `enfermera@clinica-demo.cl` | `enf1234!` | Enfermera |
| `tens@clinica-demo.cl` | `tens1234` | TENS |
| `visualizador@clinica-demo.cl` | `vis12345` | Visualizador |

### Clínica Norte (`clinica_norte`)

| Email | Contraseña | Rol |
|-------|-----------|-----|
| `admin@clinica-norte.cl` | `admin1234` | Administrador |
| `coordinador@clinica-norte.cl` | `coord1234` | Coordinador |
| `enfermera@clinica-norte.cl` | `enf1234!` | Enfermera |
| `tens@clinica-norte.cl` | `tens1234` | TENS |
| `visualizador@clinica-norte.cl` | `vis12345` | Visualizador |

### Permisos por rol

| Módulo | ADMIN | COORDINADOR | ENFERMERA | TENS | VISUALIZADOR |
|--------|-------|-------------|-----------|------|--------------|
| Pacientes | CRUD | CRUD | Ver/Bitácora | — | — |
| Turnos | CRUD | Crear/Editar | Ver | Ver | Ver |
| Personal | CRUD | Ver | Ver | Ver | Ver |
| Eventos adversos | CRUD | CRUD | Ver | Ver | — |
| Finanzas | CRUD | CRUD | — | — | — |
| Usuarios | CRUD | — | — | — | — |

---

## 11. Datos de prueba incluidos

Después de cargar los fixtures cada clínica contiene:

| Entidad | Cantidad | Detalle |
|---------|----------|---------|
| Usuarios | 5 | Admin, Coordinador, Enfermera, TENS, Visualizador |
| Mandantes | 3 | Distintos por clínica (FONASA RM / ISAPRE Norte) |
| Pacientes | 3 | 2 activos + 1 suspendido, con condición de domicilio |
| Trabajadores | 4 | Enfermera, 2× TENS, Cuidador |
| Turnos | ~25 | Semana actual + siguiente: 24h, 12h día/noche, visitas |
| Liquidaciones | 3 | Estados: Borrador, Aprobada, Pagada |
| Facturas | 3 | Estados: Borrador, Emitida, Pagada |

---

## 12. Agregar una nueva clínica

### Paso 1 — Registrar en el archivo hosts

#### Windows (Administrador)
Agregar en `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1  misubdominio.localhost
```

#### Linux / macOS
```bash
sudo sh -c 'echo "127.0.0.1  misubdominio.localhost" >> /etc/hosts'
```

### Paso 2 — Crear el tenant

```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:crear 'Nombre de la Clínica' misubdominio"
```

El comando crea la BD `clinica_misubdominio` y aplica el schema automáticamente.

### Paso 3 — Cargar datos de prueba (opcional)

Obtener el ID asignado (se muestra en la salida del paso anterior) y ejecutar:

```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load <ID> --no-interaction"
```

### Paso 4 — Acceder

```
http://misubdominio.localhost:8090/login
```

---

## Cargar datos de prueba custom (seeder interactivo)

Además de los fixtures predefinidos del paso 8, existe un comando interactivo que permite agregar datos a medida a cualquier tenant, eligiendo qué entidades crear y con qué parámetros.

```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:seeder:interactivo"
```

El comando guía paso a paso:

1. **Selecciona la clínica** — lista todos los tenants activos
2. **Muestra el estado actual** — cuántos registros hay de cada entidad en esa BD
3. **Multi-select de entidades** — elige qué agregar (separa con coma, ej: `0,2,3`):

   | # | Entidad | Dependencias |
   |---|---------|--------------|
   | 0 | Usuarios | — |
   | 1 | Mandantes | — |
   | 2 | Pacientes | mandantes en la BD |
   | 3 | Trabajadores | — |
   | 4 | Turnos | pacientes activos + trabajadores activos |
   | 5 | Tarifas | — |
   | 6 | Eventos adversos | pacientes + trabajadores |
   | 7 | Liquidaciones mensuales | trabajadores |
   | 8 | Facturas a mandantes | mandantes |

4. **Valida dependencias** antes de ejecutar — si falta alguna, muestra el error y sale sin hacer cambios
5. **Pregunta parámetros** para cada entidad (cantidad, tipo, estado, año/mes, % descubiertos, etc.)
6. **Resumen final** con los registros creados

**Notas:**
- Los turnos pasados se crean como `COMPLETADO` automáticamente; los futuros como `CUBIERTO` o `DESCUBIERTO` según el porcentaje configurado
- Las liquidaciones se generan desde turnos `COMPLETADO` reales del período; si no hay, crea ítems de ejemplo
- Detecta duplicados (por email, nombre, período) y los omite sin fallar
- Se puede ejecutar múltiples veces de forma segura — es aditivo, no borra datos

---

## Comandos de mantenimiento

```bash
# Aplicar migraciones pendientes a TODOS los tenants activos
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:migrate-all"

# Listar tenants sin ejecutar (dry-run)
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:migrate-all --dry-run"

# Recargar fixtures de un tenant específico (borra y recarga)
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load <ID> --no-interaction"

# Agregar datos custom de forma interactiva a un tenant
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:seeder:interactivo"

# Limpiar caché
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console cache:clear"

# Ver todas las rutas registradas
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console debug:router"

# Procesar cola de mensajes manualmente (normalmente lo hace php-worker)
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console messenger:consume async -vv"

# Revisar documentos próximos a vencer y despachar alertas
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:revisar-documentos-vencimiento"

# Generar token de API para un tenant
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:api:generar-token demo 'Sistema ERP' --permisos=pacientes,turnos"

# Ejecutar tests
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && ./vendor/bin/phpunit --testdox"

# Ver logs en tiempo real
docker compose logs -f php
docker compose logs -f php-worker
docker compose logs -f php-scheduler
```

---

## Solución de problemas frecuentes

### El subdominio redirige a otro sitio

Verificar que el archivo `hosts` esté guardado correctamente y que no haya conflicto con otro servicio en el puerto 8090.

### Error `MappingException` al iniciar

Limpiar la caché del contenedor:
```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && rm -rf var/cache/dev && php bin/console cache:warmup"
```

### `composer install` falla por SSL

Usar el flag `--no-plugins`:
```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && composer install --no-plugins && composer dump-autoload"
```

### La app no arranca (error de clave de cifrado)

Verificar que el archivo `app/.env.local` existe y contiene `APP_ENCRYPTION_KEY`. Si no, ejecutar el paso 6 nuevamente.

### El sistema se siente lento en Windows

Docker en Windows accede a los archivos a través de WSL2, lo que añade latencia. Para mejorar el rendimiento:
1. El `docker-compose.yml` ya incluye `:cached` en los bind mounts.
2. Asegurarse de que Docker Desktop esté usando el **WSL 2 backend** (Settings → General → "Use the WSL 2 based engine").
3. Para mayor rendimiento, clonar el repositorio **dentro de WSL2** en lugar de en `C:\`:
   ```bash
   # Dentro de la terminal WSL2
   cd ~/projects
   git clone https://github.com/carlosalarcong/domiciliaria.git
   ```

### El tenant muestra datos del tenant incorrecto

La sesión puede tener un tenant anterior cacheado. Cerrar sesión y volver a ingresar desde el subdominio correcto.

### Los emails no llegan

En desarrollo los emails se capturan en Mailpit: http://localhost:8025. Verificar que el contenedor `mailpit` esté activo con `docker compose ps`.
