# Sistema de Atención Domiciliaria

Sistema web de gestión de atención domiciliaria para empresas de salud en Chile, construido con Symfony 7.4 y PHP 8.3.

## Arquitectura multi-tenant

Cada clínica opera en una base de datos PostgreSQL completamente aislada. El tenant se resuelve por subdominio.

| Clínica | URL de ingreso | Base de datos |
|---------|---------------|---------------|
| Clínica Demo (RM) | http://demo.localhost:8090/login | `clinica_demo` |
| Clínica Norte (Antofagasta) | http://norte.localhost:8090/login | `clinica_norte` |

> **Requisito:** Agregar las siguientes líneas en `C:\Windows\System32\drivers\etc\hosts`:
> ```
> 127.0.0.1  demo.localhost
> 127.0.0.1  norte.localhost
> ```

### Usuarios por clínica

**Clínica Demo** (`clinica_demo`)

| Email | Contraseña | Rol |
|-------|-----------|-----|
| admin@clinica-demo.cl | admin1234 | Administrador |
| coordinador@clinica-demo.cl | coord1234 | Coordinador |
| enfermera@clinica-demo.cl | enf1234! | Enfermera |
| tens@clinica-demo.cl | tens1234 | TENS |
| visualizador@clinica-demo.cl | vis12345 | Visualizador |

**Clínica Norte** (`clinica_norte`)

| Email | Contraseña | Rol |
|-------|-----------|-----|
| admin@clinica-norte.cl | admin1234 | Administrador |
| coordinador@clinica-norte.cl | coord1234 | Coordinador |
| enfermera@clinica-norte.cl | enf1234! | Enfermera |
| tens@clinica-norte.cl | tens1234 | TENS |
| visualizador@clinica-norte.cl | vis12345 | Visualizador |

---

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

### 4. Migraciones de la BD central

```bash
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console doctrine:migrations:migrate --no-interaction"
```

### 5. Crear tenants y cargar datos de prueba

```bash
# Crear las dos clínicas de demo (crea BD + ejecuta migraciones del tenant)
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console app:tenant:crear 'Clínica Demo' demo"
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console app:tenant:crear 'Clínica Norte' norte"

# Cargar datos de prueba en cada tenant (purga y recarga)
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console tenant:fixtures:load 1 --no-interaction"
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console tenant:fixtures:load 2 --no-interaction"
```

### 6. Acceder al sistema

- **Clínica Demo:** http://demo.localhost:8090/login
- **Clínica Norte:** http://norte.localhost:8090/login

## Módulos implementados

### Fase 1 — Auth y estructura base
- Login con Symfony Security
- Gestión de usuarios con roles (`ROLE_ADMIN`, `ROLE_COORDINADOR`, `ROLE_ENFERMERA`, `ROLE_TENS`, `ROLE_VISUALIZADOR`)
- Voters para permisos granulares
- Audit log automático (Gedmo Loggable)
- Dashboard con datos reales: 4 KPIs en tiempo real (pacientes activos, turnos del día con badge de descubiertos, eventos abiertos, trabajadores activos), tabla de turnos de hoy ordenada por urgencia, y panel de alertas activas (turnos descubiertos próximos 7 días, eventos graves/críticos sin cerrar, facturas vencidas)

### Fase 2 — Pacientes
- CRUD de mandantes (empresas/entidades contratantes)
- CRUD de pacientes con ficha clínica completa (5 pestañas)
- Condición de domicilio (acceso, mascotas, barreras arquitectónicas)
- Bitácora operativa con Turbo Frames
- Historial de comunicaciones con Turbo Frames
- **Notificación automática por cambio de estado**: email a admins/coordinadores cuando un paciente cambia a `Activo`, `Suspendido` o `Dado de baja` (Messenger)

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

### Fase 5 — Eventos Adversos
- Registro de incidentes clínicos con 8 tipos y 4 niveles de gravedad (leve/moderado/grave/crítico)
- Ciclo de vida: `Abierto` → `En proceso` → `Cerrado`
- **Asignación de responsable**: campo `responsable` (admin/coordinador) en el evento; al asignar o cambiar responsable se envía notificación in-app y email automáticamente
- **Timeline de seguimiento** con notas cronológicas por autor
- **Notificación automática** por email a admins/coordinadores en eventos Grave o Crítico (Messenger)
- Index con contadores de estado y filtros por gravedad/estado
- Formulario de cierre con observación obligatoria

### Fase 4 — Personal (documentos, disponibilidad, horas)
- **Documentos del trabajador**: subida de archivos (PDF, Word, JPG/PNG hasta 10 MB), descarga y eliminación
- **Disponibilidad**: registro de bloques horarios por fecha (disponible / no disponible)
- **Horas trabajadas**: cálculo basado en turnos completados, con filtro por rango de fechas
- **Exportación CSV** de horas trabajadas compatible con Excel (UTF-8 BOM)
- Vista `trabajador/show` con pestañas: Turnos / Documentos / Disponibilidad
- Formularios con tema Bootstrap 5 aplicado globalmente

### Fase 6 — Finanzas y Facturación
- **Liquidaciones mensuales**: generación automática a partir de turnos completados, con tarifas configurables por tipo de concepto (día, noche, 24h, visita, reemplazo)
- Ciclo de vida: `Borrador` → `Aprobada` → `Pagada`
- **Exportación CSV** de liquidación por trabajador (UTF-8 BOM, compatible Excel)
- **Facturas a mandantes**: creación con monto neto, IVA configurable y número de factura
- **Descuento automático por turnos descubiertos**: al crear la factura se puede ingresar un monto por turno descubierto y el sistema cuenta los turnos en estado `DESCUBIERTO` del período y aplica el descuento antes del IVA
- Ciclo de vida: `Borrador` → `Emitida` → `Pagada` (con fecha de vencimiento automática)
- Dashboard de finanzas con resumen de montos por estado (liquidaciones y facturas)
- Listas paginadas con filtros por año y estado
- **Reportes financieros** (`/finanzas/reportes`): tres vistas con gráficos (Chart.js):
  - **Por mandante**: tabla neto/total/cobrado/pendiente por mandante + gráfico doughnut de distribución
  - **Por trabajador**: tabla liquidaciones/total/pagado/pendiente + gráfico de barras horizontal (top 8)
  - **Flujo mensual**: gráfico de líneas ingresos vs egresos + tabla mes a mes con saldo acumulado
- Permisos granulares: `FINANZAS_VER` y `FINANZAS_EDITAR` (FinanzasVoter)

#### Cómo se llena el módulo de Finanzas

**Liquidaciones (pago a trabajadores)**

1. Ir a **Finanzas → Nueva liquidación**
2. Seleccionar trabajador, año y mes
3. Ingresar tarifas por tipo de turno (ej: $5.500/h turno día, $7.000/h turno noche)
4. El sistema busca automáticamente todos los turnos con estado `COMPLETADO` del trabajador en ese período y genera los ítems con sus subtotales

> Si el trabajador no tiene turnos completados ese mes, la liquidación queda en $0.

Después se puede **Aprobar** y luego **Marcar como pagada** ingresando la fecha de pago.

**Facturas (cobro a mandantes)**

1. Ir a **Finanzas → Nueva factura**
2. Seleccionar mandante, año y mes
3. Ingresar el monto neto manualmente (lo que se le cobra al mandante)
4. Opcionalmente ingresar el **descuento por turno descubierto** — el sistema cuenta los turnos en estado `DESCUBIERTO` del período y calcula el descuento total automáticamente (`N turnos × monto`). El IVA se aplica sobre el monto ya descontado
5. El IVA (19% por defecto, configurable) se calcula automáticamente
6. Opcionalmente ingresar número de factura y observaciones

Después se puede **Emitir** (genera fecha de vencimiento a 30 días) y **Marcar como pagada**.

**Para que los turnos aparezcan en la liquidación**, deben estar en estado `COMPLETADO`:
1. Crear un turno desde **Turnos → Nuevo turno** con un trabajador asignado
2. Entrar al turno → **Iniciar turno** → **Finalizar turno**
3. Ese turno ya aparecerá en el cálculo al generar la liquidación del período

Los datos de prueba (`doctrine:fixtures:load`) incluyen liquidaciones y facturas de ejemplo en distintos estados.

### Exportación CSV masiva

- **Exportar pacientes** (`/pacientes/exportar`): descarga el listado completo de pacientes en formato CSV compatible con Excel (UTF-8 BOM, separador `;`), respetando los mismos filtros activos de la vista de listado (estado, mandante, tipo de servicio). Incluye 17 columnas: datos personales, servicio, mandante, fechas, dirección, y datos del tutor.
- **Exportar turnos** (`/turnos/exportar`): descarga turnos filtrados por rango de fechas (`desde`/`hasta`, por defecto el mes actual). Incluye 12 columnas: fecha, horario, paciente, trabajador, tipo, estado, asistencia e incidencias.
- **Exportar facturas** (`/finanzas/facturas/exportar`): descarga facturas filtradas por año y estado. Incluye 12 columnas: número, mandante, período, montos (neto, IVA, total), estado y fechas de emisión/vencimiento/pago.

### API REST pública

Endpoints disponibles bajo `/api/v1/`, autenticados mediante el header `X-API-KEY`. Cada token tiene permisos granulares por recurso.

| Endpoint | Permiso requerido | Descripción |
|----------|------------------|-------------|
| `GET /api/v1/pacientes` | `pacientes` | Listado paginado con filtros por estado, mandante y tipo de servicio |
| `GET /api/v1/pacientes/{id}` | `pacientes` | Detalle de un paciente con ficha clínica completa |
| `GET /api/v1/turnos` | `turnos` | Turnos filtrados por rango de fechas (`?desde=&hasta=`) |
| `GET /api/v1/trabajadores` | `trabajadores` | Listado de personal con perfil, estado, email y teléfono |
| `GET /api/v1/trabajadores/{id}` | `trabajadores` | Detalle de un trabajador |
| `GET /api/v1/liquidaciones` | `liquidaciones` | Liquidaciones filtradas por año y mes |
| `GET /api/v1/facturas` | `facturas` | Facturas filtradas por año y estado |

Todas las respuestas son JSON. Los errores de autenticación devuelven `401`, los de autorización `403`. Los datos sensibles como RUT son retornados ya descifrados.

**Generar un token de API:**
```bash
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console app:api:generar-token demo 'Sistema ERP' --permisos=pacientes,turnos"
```
El token se muestra una sola vez — almacenarlo de forma segura. El sistema guarda únicamente su hash SHA-256.

### Notificaciones in-app

Bell con badge en el header que se actualiza cada 60 segundos. Al hacer clic despliega las últimas 5 notificaciones no leídas con botón de acción directo.

**Eventos que generan notificación in-app** (además del email existente):

| Evento | Destinatarios |
|--------|--------------|
| Turno descubierto | Admins y coordinadores |
| Evento adverso grave/crítico | Admins y coordinadores |
| Cambio de estado de paciente | Admins y coordinadores |
| Responsable asignado a evento | El responsable asignado |

- Panel completo en `/notificaciones` con historial reciente y botón "Marcar todas como leídas"
- Notificaciones con color e ícono según tipo, fondo resaltado mientras no se lean
- Endpoint JSON `/notificaciones/count` y `/notificaciones/recientes` para el header

### Importación masiva desde CSV

Permite cargar pacientes y trabajadores en lote desde un archivo CSV (separador `;`, codificación UTF-8). Accesible desde `/import` exclusivamente para `ROLE_ADMIN`.

**Características:**
- Detección de duplicados por RUT (sin importar el formato: con/sin puntos, con/sin guión)
- Validación fila por fila: campos obligatorios, tipo de servicio, perfil y mandante
- Reporte detallado al finalizar: cantidad importada, omitida (ya existía) y filas con error con número de línea y motivo
- Las filas válidas se importan aunque otras filas del mismo archivo tengan errores
- Plantillas CSV descargables con columnas y ejemplo incluido

**Columnas para pacientes:** `nombres`, `apellidos`, `rut`, `fecha_nacimiento`, `tipo_servicio`, `estado`, `mandante`, `fecha_ingreso`, `fecha_termino`, `direccion`, `comuna`, `region`, `telefono`, `tutor_nombre`, `tutor_telefono`, `tutor_relacion`

**Columnas para trabajadores:** `nombres`, `apellidos`, `rut`, `perfil`, `email`, `telefono`, `direccion`, `estado`, `fecha_ingreso`

---

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
│   │   │                           # TrabajadorController, EventoAdversoController,
│   │   │                           # FinanzasController, ImportController
│   │   ├── Controller/Api/         # ApiController (7 endpoints REST v1)
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

| Rol | Turnos | Personal | Pacientes | Finanzas | Usuarios | Exportar | Importar |
|-----|--------|----------|-----------|----------|----------|----------|----------|
| ROLE_ADMIN | ✅ CRUD | ✅ CRUD | ✅ | ✅ | ✅ | ✅ | ✅ |
| ROLE_COORDINADOR | ✅ Crear/Editar | 👁 Ver | ✅ | ✅ | ❌ | ✅ | ❌ |
| ROLE_ENFERMERA | 👁 Ver | 👁 Ver | ✅ | ❌ | ❌ | ❌ | ❌ |
| ROLE_TENS | 👁 Ver | 👁 Ver | ❌ | ❌ | ❌ | ❌ | ❌ |
| ROLE_VISUALIZADOR | 👁 Ver | 👁 Ver | ❌ | ❌ | ❌ | ❌ | ❌ |
| ROLE_API | — | — | — | — | — | — | — |

> Los tokens de API tienen roles propios: `ROLE_API_PACIENTES`, `ROLE_API_TURNOS`, `ROLE_API_TRABAJADORES`, `ROLE_API_LIQUIDACIONES`, `ROLE_API_FACTURAS`. Se asignan al generar el token.

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

# Multi-tenant: crear nueva clínica
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console app:tenant:crear 'Nombre Clínica' subdominio"

# Multi-tenant: aplicar migraciones pendientes a todos los tenants
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console app:tenant:migrate-all"

# Multi-tenant: recargar fixtures de un tenant (ID del tenant)
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console tenant:fixtures:load 1 --no-interaction"

# API: generar token para un tenant (subdominio, nombre descriptivo, permisos opcionales)
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console app:api:generar-token demo 'Sistema ERP' --permisos=pacientes,turnos,trabajadores"

# API: generar token con fecha de expiración
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console app:api:generar-token demo 'Token temporal' --permisos=pacientes --expires=2026-12-31"
```

## Fases del proyecto

- [x] **Fase 1** — Auth, roles y estructura base
- [x] **Fase 2** — Módulo de Pacientes y Mandantes
- [x] **Fase 3** — Módulo de Turnos y Calendario
- [x] **Fase 4** — Módulo de Personal (documentos, disponibilidad, exportación de horas)
- [x] **Fase 5** — Módulo de Eventos Adversos
- [x] **Fase 6** — Módulo de Finanzas y Facturación
- [x] **Fase 7** — Arquitectura multi-tenant (hakam/multi-tenancy-bundle, BD aislada por clínica)
- [x] **Fase 8** — Exportación CSV masiva de pacientes, turnos y facturas
- [x] **Fase 9** — API REST pública con autenticación por token y permisos granulares
- [x] **Fase 10** — Importación masiva de pacientes y trabajadores desde CSV
- [x] **Fase 11** — Notificación por cambio de estado de paciente y descuento automático en factura por turnos descubiertos
- [x] **Fase 12** — Dashboard con datos reales (KPIs, turnos del día, alertas activas)
- [x] **Fase 13** — Asignación de responsable en evento adverso con notificación automática
- [x] **Fase 14** — Reportes financieros con gráficos (por mandante, por trabajador, flujo mensual)
- [x] **Fase 15** — Notificaciones in-app con badge/bell en el header

## Notas técnicas

### Content Security Policy (CSP)

El CSP está configurado en `docker/nginx/default.conf`. Permite cargar scripts, estilos y fuentes desde `cdn.jsdelivr.net` (Bootstrap, Bootstrap Icons, FullCalendar). El `connect-src` también incluye `cdn.jsdelivr.net` para permitir la carga de source maps en DevTools sin warnings.

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
