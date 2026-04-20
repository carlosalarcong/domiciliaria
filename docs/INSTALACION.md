# Instalación y puesta en marcha

Guía completa para levantar Domiciliaria SaaS en un entorno local desde cero.

---

## Prerrequisitos

| Herramienta | Versión mínima | Verificar |
|-------------|---------------|-----------|
| Docker Desktop | 4.x | `docker --version` |
| Git | 2.x | `git --version` |

> **macOS/Windows:** asegúrate de que Docker Desktop esté corriendo antes de continuar.

---

## Pasos

### 1. Clonar el repositorio

```bash
git clone <url-del-repo> domiciliaria
cd domiciliaria
```

### 2. Crear el archivo de entorno local

```bash
cp app/.env app/.env.local
```

Edita `app/.env.local` y ajusta las credenciales si es necesario. Los valores por defecto funcionan para desarrollo local sin cambios.

### 3–6. Setup base con el script

```bash
./setup.sh
```

El script cubre de forma automática e idempotente (es seguro re-ejecutar):

| Paso | Qué hace |
|------|----------|
| **3** | Levanta los contenedores Docker (`php`, `nginx`, `postgres`, `redis`, `php-worker`, `mailpit`) |
| **4** | Ejecuta `composer install` dentro del contenedor PHP |
| **5** | Aplica las migraciones de la base de datos central |
| **6** | Genera `APP_ENCRYPTION_KEY` y la guarda en `app/.env.local` |

> **Importante:** guarda la `APP_ENCRYPTION_KEY` generada en un lugar seguro. Sin ella los datos cifrados (secretos 2FA, etc.) no se pueden recuperar.

Al terminar el script verás:

```
════════════════════════════════════════
  ✓ Setup base completado
════════════════════════════════════════
```

### 7. Crear una clínica (tenant)

```bash
docker compose exec php bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:crear 'Clínica Demo' demo"
```

Esto crea la entrada en la BD central y genera la base de datos aislada del tenant `demo`.

Para agregar más clínicas repite el comando cambiando nombre y slug.

### 8. Cargar datos de prueba

**Opción A — fixtures predefinidos:**

```bash
docker compose exec php bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load 1 --no-interaction"
```

Reemplaza `1` por el ID del tenant que quieres poblar.

**Opción B — datos custom interactivo:**

```bash
docker compose exec php bash -c \
  "cd /var/www/html/app && php bin/console app:seeder:interactivo"
```

El asistente te guía para crear trabajadores, pacientes y turnos con los valores que elijas.

---

## Verificar que todo funciona

Abre en el navegador:

| URL | Qué es |
|-----|--------|
| `http://demo.localhost:8090` | App del tenant `demo` |
| `http://localhost:8025` | Mailpit — captura de emails |

> Si usas un slug distinto a `demo`, cambia el subdominio según corresponda.

---

## Puertos expuestos

| Servicio | Puerto local |
|----------|-------------|
| Nginx (app) | **8090** |
| PostgreSQL | 5432 |
| Redis | 6379 |
| Mailpit (UI) | **8025** |

---

## Resolución de problemas frecuentes

**Docker no responde al levantar:**
```bash
docker compose restart nginx
```
Nginx necesita reiniciarse después del primer `docker compose up` para re-resolver las IPs internas.

**PostgreSQL no está listo:**
```bash
docker compose logs postgres
```

**El subdominio no resuelve (`demo.localhost` da error de DNS):**  
Agrega la entrada al `/etc/hosts`:
```
127.0.0.1  demo.localhost
```

**Re-ejecutar el setup desde cero:**  
`setup.sh` es idempotente — detecta lo que ya está hecho y lo omite. Es seguro ejecutarlo nuevamente en cualquier momento.

---

## Próximos pasos

- Para agregar más tenants: ver [`NUEVO_TENANT.md`](NUEVO_TENANT.md)
- Para referencia técnica de arquitectura: ver [`manual_tecnico.md`](manual_tecnico.md)
