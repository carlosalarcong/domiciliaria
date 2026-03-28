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

Esto construye la imagen PHP y levanta los 4 servicios:

| Servicio | Imagen | Puerto expuesto |
|----------|--------|-----------------|
| `php` | PHP 8.3-FPM + extensiones | interno |
| `nginx` | nginx:alpine | `8090 → 80` |
| `postgres` | postgres:16 | `5432` |
| `redis` | redis:alpine | `6379` |

Verificar que todos los contenedores estén en estado `Up`:

```bash
docker compose ps
```

---

## 4. Instalar dependencias PHP

```bash
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && composer install --no-plugins"
```

> El flag `--no-plugins` evita problemas si el entorno tiene inspección SSL corporativa. En entornos sin proxy SSL se puede omitir.

---

## 5. Ejecutar migraciones de la base de datos central

La BD central (`domiciliaria`) almacena el registro de clínicas (tabla `tenant_db`).

```bash
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console doctrine:migrations:migrate --no-interaction"
```

---

## 6. Crear las clínicas de demo

El comando `app:tenant:crear` realiza tres acciones en una sola operación:
1. Registra la clínica en la BD central
2. Crea la base de datos PostgreSQL de la clínica
3. Aplica el schema completo (16 tablas) via migración

```bash
# Clínica Demo — Región Metropolitana
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:crear 'Clínica Demo' demo"

# Clínica Norte — Antofagasta
docker exec domicialiaria-php-1 bash -c \
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

## 7. Cargar datos de prueba

Carga usuarios, pacientes, mandantes, trabajadores, turnos, liquidaciones y facturas de ejemplo en cada clínica. **Purga los datos existentes antes de insertar.**

```bash
# Clínica Demo (ID=1)
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load 1 --no-interaction"

# Clínica Norte (ID=2)
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load 2 --no-interaction"
```

---

## 8. Acceder al sistema

| Clínica | URL de ingreso |
|---------|---------------|
| Clínica Demo | http://demo.localhost:8090/login |
| Clínica Norte | http://norte.localhost:8090/login |

---

## 9. Usuarios de prueba

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

## 10. Datos de prueba incluidos

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

## 11. Agregar una nueva clínica

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
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:crear 'Nombre de la Clínica' misubdominio"
```

El comando crea la BD `clinica_misubdominio` y aplica el schema automáticamente.

### Paso 3 — Cargar datos de prueba (opcional)

Obtener el ID asignado (se muestra en la salida del paso anterior) y ejecutar:

```bash
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load <ID> --no-interaction"
```

### Paso 4 — Acceder

```
http://misubdominio.localhost:8090/login
```

> Los datos de prueba del nuevo tenant usarán el set genérico (mismo que Clínica Demo). Para personalizar los datos de fixtures por clínica, agregar un caso en el `match` de `UserFixtures`, `PacienteFixtures` y `TurnoFixtures` usando `str_contains($db, 'misubdominio')`.

---

## Comandos de mantenimiento

```bash
# Aplicar migraciones pendientes a TODOS los tenants activos
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:migrate-all"

# Listar tenants sin ejecutar (dry-run)
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:migrate-all --dry-run"

# Recargar fixtures de un tenant específico (borra y recarga)
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load <ID> --no-interaction"

# Limpiar caché
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console cache:clear"

# Ver todas las rutas registradas
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console debug:router"

# Procesar cola de mensajes (notificaciones email)
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console messenger:consume async -vv"

# Ejecutar tests
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && ./vendor/bin/phpunit --testdox"
```

---

## Solución de problemas frecuentes

### El subdominio redirige a otro sitio

Verificar que el archivo `hosts` esté guardado correctamente y que no haya conflicto con otro servicio en el puerto 8090.

### Error `MappingException` al iniciar

Limpiar la caché del contenedor:
```bash
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && rm -rf var/cache/dev && php bin/console cache:warmup"
```

### `composer install` falla por SSL

Usar el flag `--no-plugins`:
```bash
docker exec domicialiaria-php-1 bash -c \
  "cd /var/www/html/app && composer install --no-plugins && composer dump-autoload"
```

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
