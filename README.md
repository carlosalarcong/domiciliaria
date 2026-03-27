# Sistema de Atención Domiciliaria

Sistema web de gestión de atención domiciliaria para empresas de salud en Chile, construido con Symfony 7.4 y PHP 8.3.

## Stack tecnológico

- **PHP 8.3** + **Symfony 7.4** (LTS)
- **PostgreSQL 16** con Doctrine ORM y migraciones
- **Redis** para sesiones y mensajería asíncrona
- **Twig** + **Bootstrap 5** (CDN) para el frontend
- **FullCalendar 6** (CDN) para el calendario de turnos
- **Gedmo DoctrineExtensions** para audit log automático y timestamps
- **Symfony Messenger** para notificaciones asíncronas
- **Symfony Scheduler** para tareas programadas (cron)
- **KnpPaginator** para paginación
- **Docker** para el entorno local

## Requisitos

- Docker Desktop
- Git

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/carlosalarcong/domiciliaria.git
cd domiciliaria
```

### 2. Levantar los contenedores

```bash
docker compose up -d --build
```

Servicios disponibles:

| Servicio | Puerto |
|----------|--------|
| Nginx (app web) | http://localhost:8090 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |

### 3. Instalar dependencias PHP

```bash
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && composer install --no-plugins && composer dump-autoload"
```

### 4. Ejecutar migraciones y cargar datos de prueba

```bash
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console doctrine:migrations:migrate --no-interaction"
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console doctrine:fixtures:load --no-interaction"
```

### 5. Acceder al sistema

Abre http://localhost:8090 en tu navegador.

## Usuarios de prueba

| Email | Contraseña | Rol |
|-------|-----------|-----|
| admin@domiciliaria.cl | admin1234 | Administrador |
| coordinador@domiciliaria.cl | coord1234 | Coordinador |
| enfermera@domiciliaria.cl | enf1234! | Enfermera |
| tens@domiciliaria.cl | tens1234 | TENS |
| visualizador@domiciliaria.cl | vis12345 | Visualizador |

## Módulos implementados

### Fase 1 — Auth y estructura base
- Login con Symfony Security
- Gestión de usuarios con roles (`ROLE_ADMIN`, `ROLE_COORDINADOR`, `ROLE_ENFERMERA`, `ROLE_TENS`, `ROLE_VISUALIZADOR`)
- Voters para permisos granulares
- Audit log automático (Gedmo Loggable)
- Dashboard principal

### Fase 2 — Pacientes
- CRUD de mandantes (empresas/entidades contratantes)
- CRUD de pacientes con ficha clínica completa (5 pestañas)
- Condición de domicilio (acceso, mascotas, barreras arquitectónicas)
- Bitácora operativa con Turbo Frames
- Historial de comunicaciones con Turbo Frames

### Fase 3 — Turnos y Calendario
- Calendario visual con **FullCalendar** (vistas mensual, semanal y lista, en español)
- Colores por estado: verde (cubierto), amarillo (parcial), rojo (descubierto), azul (completado)
- Click en evento abre modal con detalle del turno
- Formulario de creación con validación de disponibilidad **en tiempo real** (API JS)
- Gestión de reemplazos con registro de motivo
- Botones de asistencia **Iniciar / Finalizar turno** (diseño móvil)
- CRUD de personal (trabajadores) con perfiles TENS, Enfermera, Cuidador
- Notificaciones por email a admins/coordinadores cuando hay turno descubierto (Messenger)
- Revisión automática diaria a las 20:00 (Symfony Scheduler, cron `0 20 * * *`)

### Fase 4 — Personal (documentos, disponibilidad, horas)
- **Documentos del trabajador**: subida de archivos (PDF, Word, JPG/PNG hasta 10 MB), descarga y eliminación
- **Disponibilidad**: registro de bloques horarios por fecha (disponible / no disponible)
- **Horas trabajadas**: cálculo basado en turnos completados, con filtro por rango de fechas
- **Exportación CSV** de horas trabajadas compatible con Excel (UTF-8 BOM)
- Vista `trabajador/show` con pestañas: Turnos / Documentos / Disponibilidad
- Formularios con tema Bootstrap 5 aplicado globalmente

## Estructura del proyecto

```
domiciliaria/
├── docker/
│   ├── nginx/default.conf          # Configuración Nginx
│   └── php/Dockerfile              # PHP 8.3 + extensiones + Composer
├── app/
│   ├── config/
│   │   └── packages/               # messenger.yaml, scheduler.yaml, etc.
│   ├── migrations/                 # Migraciones de base de datos
│   ├── src/
│   │   ├── Controller/             # SecurityController, DashboardController,
│   │   │                           # UserController, MandanteController,
│   │   │                           # PacienteController, TurnoController,
│   │   │                           # TrabajadorController
│   │   ├── Entity/                 # User, Paciente, Mandante, Trabajador,
│   │   │                           # Turno, DisponibilidadTrabajador, ...
│   │   ├── Enum/                   # TipoTurno, EstadoTurno, TipoServicio, ...
│   │   ├── Form/                   # TurnoType, TrabajadorType, ReemplazoType, ...
│   │   ├── Message/                # TurnoDescubiertoMessage, ...
│   │   ├── MessageHandler/         # TurnoDescubiertoHandler, ...
│   │   ├── Repository/             # TurnoRepository (+ findEventosCalendario), ...
│   │   ├── Scheduler/              # TurnosDescubiertosSchedule
│   │   ├── Security/Voter/         # TurnoVoter, PacienteVoter, FinanzasVoter
│   │   └── Service/                # TurnoService, PacienteService, UserService, ...
│   ├── templates/                  # Vistas Twig por módulo
│   └── tests/                      # Tests PHPUnit (Service/)
└── docker-compose.yml
```

## Roles y permisos

| Rol | Turnos | Personal | Pacientes | Finanzas | Usuarios |
|-----|--------|----------|-----------|----------|----------|
| ROLE_ADMIN | ✅ CRUD | ✅ CRUD | ✅ | ✅ | ✅ |
| ROLE_COORDINADOR | ✅ Crear/Editar | 👁 Ver | ✅ | ✅ | ❌ |
| ROLE_ENFERMERA | 👁 Ver | 👁 Ver | ✅ | ❌ | ❌ |
| ROLE_TENS | 👁 Ver | 👁 Ver | ❌ | ❌ | ❌ |
| ROLE_VISUALIZADOR | 👁 Ver | 👁 Ver | ❌ | ❌ | ❌ |

## Comandos útiles

```bash
# Consola Symfony
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console <comando>"

# Ejecutar tests
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && ./vendor/bin/phpunit --testdox"

# Generar nueva migración tras cambiar entidades
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate --no-interaction"

# Ver rutas registradas
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console debug:router"

# Limpiar caché
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console cache:clear"

# Procesar cola de mensajes (notificaciones email)
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console messenger:consume async -vv"
```

## Fases del proyecto

- [x] **Fase 1** — Auth, roles y estructura base
- [x] **Fase 2** — Módulo de Pacientes y Mandantes
- [x] **Fase 3** — Módulo de Turnos y Calendario
- [x] **Fase 4** — Módulo de Personal (documentos, disponibilidad, exportación de horas)
- [ ] **Fase 5** — Módulo de Eventos Adversos
- [ ] **Fase 6** — Módulo de Finanzas y Facturación

## Notas técnicas

### SSL corporativo
Si el entorno tiene inspección SSL (certificado autofirmado en la cadena), instalar dependencias con `--no-plugins` para evitar que Symfony Flex intente descargar recetas vía HTTPS:
```bash
composer require vendor/paquete --no-plugins
composer dump-autoload  # regenera autoload_runtime.php
```

### Audit log
Todas las entidades marcadas con `#[Gedmo\Loggable]` registran cambios automáticamente en la tabla `ext_log_entries`. Se usa una entidad `LogEntry` personalizada con campo `data` de tipo `json` para compatibilidad con Doctrine DBAL 4.

### Messenger (notificaciones asíncronas)
Por defecto el transporte está configurado como `sync` (procesamiento inmediato). Para producción, cambiar a Redis en `.env`:
```env
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
```
Y ejecutar el worker: `php bin/console messenger:consume async`
