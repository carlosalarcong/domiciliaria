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
- **NelmioSecurityBundle v3.9** para Content Security Policy (CSP)
- **Driver.js v1.3.1** (CDN) para tours guiados contextuales
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
| Mailpit (bandeja de emails dev) | http://localhost:8025 |

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
- CRUD de pacientes con ficha clínica completa (5 pestañas): Datos, Domicilio, Bitácora, Comunicaciones, Turnos
- Condición de domicilio (acceso, mascotas, barreras arquitectónicas)
- **Notificación automática por cambio de estado**: email a admins/coordinadores cuando un paciente cambia a `Activo`, `Suspendido` o `Dado de baja` (Messenger)

### Bitácora operativa por paciente

Log de novedades libre integrado en la ficha del paciente (pestaña **Bitácora**), sin necesidad de recargar la página gracias a **Turbo Frames**.

**Tipos de entrada:**

| Tipo | Badge | Uso típico |
|------|-------|-----------|
| `NOVEDAD` | azul | Observación general del turno, cambio en el estado del paciente |
| `INCIDENCIA` | rojo | Caída, accidente, reacción adversa, situación fuera de lo normal |
| `COMUNICACION` | azul primario | Llamado a familia, médico o mandante registrado en la bitácora |

Cada entrada registra: fecha/hora automática (Gedmo Timestampable), tipo, descripción y el usuario que la creó. Las entradas se muestran ordenadas por fecha descendente.

### Historial de comunicaciones con mandante y familia

Registro de todos los contactos con externos (familia, médico, mandante) desde la pestaña **Comunicaciones** de la ficha del paciente, también con **Turbo Frames**.

**Campos por comunicación:**
- **Tipo**: Familia, Médico, Mandante, Otro
- **Persona contactada**: nombre libre del interlocutor
- **Descripción**: resumen del contenido de la comunicación
- **Fecha/hora** y **usuario** registrados automáticamente

Permite mantener trazabilidad de todos los contactos externos sin salir de la ficha del paciente.

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
- **Asignación de responsable**: campo `responsable` (admin/coordinador) en el evento; al asignar o cambiar responsable se envía notificación in-app y email automáticamente
- **Timeline de seguimiento** con notas cronológicas por autor
- **Notificación automática** por email a admins/coordinadores en eventos Grave o Crítico (Messenger)
- Index con contadores de estado y filtros por gravedad/estado
- Formulario de cierre con observación obligatoria

### Flujo formal de estados en Eventos Adversos

El ciclo de vida pasó de informal a un flujo de tres pasos con trazabilidad completa:

| Estado | Etiqueta visual | Transición |
|--------|----------------|------------|
| `ABIERTO` | Registrado (rojo) | → Poner en revisión |
| `EN_PROCESO` | En revisión (amarillo) | → Cerrar |
| `CERRADO` | Cerrado (verde) | — (solo lectura) |

**Trazabilidad por transición:**
- **Revisión**: se registra quién puso el evento en revisión (`revisadoPor`) y en qué momento (`revisadoEn`)
- **Cierre**: se registra quién cerró el evento (`cerradoPor`) con fecha de cierre y observación obligatoria
- Cada transición genera automáticamente una nota en el **timeline de seguimiento**
- La vista `show` adapta su panel lateral según el estado actual: botón de revisión, formulario de cierre o resumen de cierre

**Alertas in-app inmediatas para eventos graves/críticos:**
- Al registrar un evento con gravedad `GRAVE` o `CRÍTICO`, se despacha inmediatamente una notificación in-app a todos los admins y coordinadores activos
- El mensaje aparece en el badge del bell en el próximo ciclo de polling (60 s)
- Icono: alerta triangular amarilla (`bi-exclamation-triangle-fill`)

### Fase 4 — Personal (documentos, disponibilidad, horas)
- **Documentos del trabajador**: subida de archivos (PDF, Word, JPG/PNG hasta 10 MB), descarga y eliminación
- **Disponibilidad**: registro de bloques horarios por fecha (disponible / no disponible)
- **Horas trabajadas**: cálculo basado en turnos completados, con filtro por rango de fechas
- **Exportación CSV** de horas trabajadas compatible con Excel (UTF-8 BOM)
- Vista `trabajador/show` con pestañas: Turnos / Documentos / Disponibilidad
- Formularios con tema Bootstrap 5 aplicado globalmente

### Historial de turnos y horas por trabajador

La pestaña **Turnos** de la ficha del trabajador fue reemplazada por un historial completo:

- **4 KPIs en tiempo real** (filtrados por el año seleccionado): total horas del año, total turnos del año, promedio de horas por mes y turnos del filtro activo
- **Filtros de año y mes** con recarga automática al cambiar la selección
- **Tabla de resumen mensual**: muestra total de turnos y horas por mes del año seleccionado (solo visible cuando no hay filtro de mes activo)
- **Tabla detalle de turnos**: todos los turnos del período con fecha, tipo, paciente, estado, horas y registro de inicio/fin de asistencia
- **Total de horas al pie** de la tabla de detalle
- Los turnos en estado `DESCUBIERTO` no se cuentan en el cálculo de horas (no generan horas trabajadas)

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

| Evento | Destinatarios | Icono |
|--------|--------------|-------|
| Turno descubierto | Admins y coordinadores | `bi-calendar-x` rojo |
| Evento adverso grave/crítico | Admins y coordinadores | `bi-exclamation-triangle-fill` amarillo |
| Cambio de estado de paciente | Admins y coordinadores | `bi-person-fill` azul |
| Responsable asignado a evento | El responsable asignado | `bi-person-check-fill` primario |
| Documento próximo a vencer | Admins y coordinadores | `bi-file-earmark-x-fill` rojo |

- Panel completo en `/notificaciones` con historial reciente y botón "Marcar todas como leídas"
- Notificaciones con color e ícono según tipo, fondo resaltado mientras no se lean
- Endpoint JSON `/notificaciones/count` y `/notificaciones/recientes` para el header

### Notificaciones por email y alertas de vencimiento de documentos

#### Emails automáticos

Todos los handlers de Messenger envían además un email HTML cuando se cumplen las condiciones. En desarrollo los emails se capturan en **Mailpit** (`http://localhost:8025`) sin salir al exterior.

| Disparador | Asunto del email | Destinatarios |
|------------|-----------------|---------------|
| Turno descubierto (cron 20:00) | `⚠️ Turno descubierto — {paciente}` | Admins y coordinadores |
| Evento adverso grave/crítico | `🚨 Evento adverso {gravedad} — {paciente}` | Admins y coordinadores |
| Responsable asignado a evento | `📋 Se te asignó un evento adverso` | El responsable asignado |
| Cambio de estado de paciente | `🟢/🟡/🔴 Cambio de estado — {paciente}` | Admins y coordinadores |
| Documento próximo a vencer | `🔴/🟡 Documento por vencer — {trabajador} ({N} días)` | Admins y coordinadores |

Para producción, configurar `MAILER_DSN` en `.env.local`:
```env
MAILER_DSN=smtp://usuario:contraseña@smtp.servidor.cl:587
```

#### Vencimiento de documentos de trabajadores

- Los documentos ahora tienen un campo opcional **Fecha de vencimiento**
- La tabla de documentos en la ficha del trabajador muestra un **semáforo visual**:
  - Fila roja + ícono ✗ → documento ya vencido
  - Fila amarilla + ícono ⚠ → vence en 7 días o menos
  - Texto azul + ícono 🕐 → vence en 8–30 días
  - Texto gris → vence en más de 30 días

#### Comando de revisión diaria

```bash
php bin/console app:revisar-documentos-vencimiento
```

Revisa todos los documentos con `fechaVencimiento` en los próximos 30 días y despacha notificaciones **solo en los hitos** 30, 15, 7, 3 y 1 día(s) antes del vencimiento. Esto evita spam diario y asegura que los avisos lleguen en momentos significativos.

Configuración recomendada de cron en producción:
```
0 7 * * * php /ruta/app/bin/console app:revisar-documentos-vencimiento
```

### Exportación de liquidaciones a Buk

Permite exportar las liquidaciones aprobadas de un período al formato CSV compatible con la plataforma **Buk** (HRMS). Accesible desde Finanzas → Liquidaciones → "Exportar Buk" (requiere `FINANZAS_EDITAR`).

**Características:**
- Filtra solo liquidaciones en estado `APROBADA` del año y mes seleccionados
- Genera un CSV con BOM UTF-8, separador `;`, compatible con Excel y con el importador de Buk
- **Turnos de 24 horas** se dividen en 2 filas (jornada diurna + nocturna) distribuyendo cantidad y monto por igual
- **Descuentos** son omitidos del CSV (Buk los gestiona por su propia lógica)
- **RUT** normalizado automáticamente al formato chileno `12345678-9`
- Nombre del archivo: `buk_YYYY_MM.csv`
- Si no hay liquidaciones aprobadas para el período, muestra un mensaje de advertencia y redirige

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

### Configuración por tenant

Cada clínica puede personalizar los parámetros globales del sistema desde **Administración → Configuración** (solo `ROLE_ADMIN`). Los valores se guardan en la tabla `configuracion_clinica` de cada base de datos tenant.

**Parámetros configurables:**

| Grupo | Parámetros |
|-------|-----------|
| Identidad | Nombre de la clínica, razón social, RUT empresa, giro, dirección fiscal, teléfono, email |
| Facturación | % IVA, días de vencimiento de factura, prefijo de número de factura |
| Operaciones | Días de anticipación para alertas de cobertura, hora de revisión diaria, límite de archivos (MB) |
| Notificaciones | Activar/desactivar alertas por turno descubierto y evento grave, email externo para alertas |
| Módulos | Activar/desactivar módulo Finanzas y módulo Eventos Adversos |

**Impacto en el sistema:**
- El **nombre de la clínica** aparece en el menú lateral para todos los usuarios
- El **% de IVA** y **días de vencimiento** se usan automáticamente al emitir facturas
- Los **días de anticipación** controlan el cron de detección de turnos descubiertos
- Los **módulos desactivados** se ocultan del menú lateral para todos los usuarios del tenant

Arquitectura híbrida: columnas tipadas para parámetros consumidos por código (type-safety, validación) + campo `extras JSONB` para extensibilidad futura sin nuevas migraciones.

### Sistema de ayuda contextual (tours Driver.js)

Tours guiados interactivos que se activan automáticamente la primera vez que un usuario visita cada módulo. Implementado con **Driver.js v1.3.1**.

**Módulos con tour:**

| Módulo | Pasos | Elementos destacados |
|--------|-------|---------------------|
| Dashboard | 5 | KPIs, turnos del día, notificaciones |
| Pacientes | 3 | Botón nuevo, filtros, tabla |
| Turnos | 3 | Botón nuevo, calendario, leyenda |
| Personal | 2 | Botón nuevo, tabla |
| Finanzas | 4 | Cards de liquidaciones/facturas, tarifas |
| Configuración | 6 | Las 5 secciones de configuración + botón guardar |

**Comportamiento:**
- El tour se inicia automáticamente (delay 600ms) si el usuario no lo ha completado antes
- El estado de tours completados se guarda por usuario en la columna `tours_completados JSON` de la tabla `users`
- El botón `?` (esquina inferior derecha) permite reiniciar el tour del módulo actual manualmente
- Desde el mismo menú `?` se pueden reiniciar todos los tours a la vez

### Centro de ayuda (`/ayuda`)

Página dedicada accesible desde "Centro de ayuda" al pie del menú lateral (visible para todos los roles).

**Tres pestañas:**

- **Inicio rápido**: 6 cards de acceso directo a las acciones más frecuentes + botones para iniciar los tours de cada módulo
- **Manual del sistema**: 8 módulos colapsables con descripción y pasos numerados; incluye buscador en tiempo real
- **Preguntas frecuentes**: 15 preguntas agrupadas en 5 categorías (Primeros pasos, Turnos, Personal y liquidaciones, Facturación, Acceso y permisos); incluye buscador en tiempo real

### Seguridad HTTP (headers y CSP)

Headers de seguridad gestionados en dos capas complementarias:

| Header | Capa | Valor |
|--------|------|-------|
| `Content-Security-Policy` | NelmioSecurityBundle | CDNs permitidos: `cdn.jsdelivr.net`, `cdnjs.cloudflare.com`; `unsafe-inline` para Turbo/Stimulus |
| `X-Frame-Options` | Nginx | `DENY` |
| `X-Content-Type-Options` | Nginx | `nosniff` |
| `Referrer-Policy` | Nginx | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | Nginx | Deshabilita cámara, micrófono, geolocalización y pagos |
| `Strict-Transport-Security` | Nginx | Solo en HTTPS (producción) |

**Comando de verificación de headers:**
```bash
docker exec domiciliaria-php-1 bash -c "cd /var/www/html/app && php bin/console app:security:headers http://demo.localhost:8090"
```
Muestra ✅/❌ para cada header esperado.

---

## Estructura del proyecto

```
domiciliaria/
├── docker/
│   ├── nginx/default.conf          # Nginx + headers de seguridad HTTP
│   └── php/Dockerfile              # PHP 8.3 + extensiones + Composer
├── app/
│   ├── config/
│   │   └── packages/               # messenger.yaml, scheduler.yaml,
│   │                               # nelmio_security.yaml, monolog.yaml
│   ├── migrations/                 # Migraciones BD central
│   ├── migrations/Tenant/          # Migraciones BD por tenant
│   ├── public/js/help/             # tours.js (Driver.js)
│   ├── src/
│   │   ├── Command/                # SecurityHeadersCommand
│   │   ├── Controller/             # SecurityController, DashboardController,
│   │   │                           # UserController, MandanteController,
│   │   │                           # PacienteController, TurnoController,
│   │   │                           # TrabajadorController, EventoAdversoController,
│   │   │                           # FinanzasController, ImportController,
│   │   │                           # HelpController, ConfiguracionController
│   │   ├── Controller/Api/         # ApiController (7 endpoints REST v1)
│   │   ├── Entity/Tenant/          # User, Paciente, Mandante, Trabajador,
│   │   │                           # Turno, ConfiguracionClinica, ...
│   │   ├── Enum/                   # TipoTurno, EstadoTurno, TipoServicio, ...
│   │   ├── Form/                   # TurnoType, TrabajadorType, ConfiguracionType, ...
│   │   ├── Message/                # TurnoDescubiertoMessage, ...
│   │   ├── MessageHandler/         # TurnoDescubiertoHandler, ...
│   │   ├── Repository/             # TurnoRepository, ConfiguracionClinicaRepository, ...
│   │   ├── Scheduler/              # TurnosDescubiertosSchedule
│   │   ├── Security/Voter/         # TurnoVoter, PacienteVoter, FinanzasVoter
│   │   ├── Service/                # TurnoService, PacienteService, ExportService,
│   │   │                           # FinanzasService, ConfiguracionService, ...
│   │   └── Twig/                   # ConfiguracionExtension (GlobalsInterface)
│   ├── templates/
│   │   ├── configuracion/          # index.html.twig
│   │   ├── help/                   # ayuda.html.twig
│   │   └── partials/               # sidebar, header, help_button, flash_messages
│   └── tests/                      # Tests PHPUnit (Service/)
└── docker-compose.yml
```

## Roles y permisos

| Rol | Turnos | Personal | Pacientes | Finanzas | Usuarios | Exportar | Importar | Configuración |
|-----|--------|----------|-----------|----------|----------|----------|----------|---------------|
| ROLE_ADMIN | ✅ CRUD | ✅ CRUD | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| ROLE_COORDINADOR | ✅ Crear/Editar | 👁 Ver | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| ROLE_ENFERMERA | 👁 Ver | 👁 Ver | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| ROLE_TENS | 👁 Ver | 👁 Ver | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| ROLE_VISUALIZADOR | 👁 Ver | 👁 Ver | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| ROLE_API | — | — | — | — | — | — | — | — |

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

# Revisar documentos próximos a vencer y despachar alertas (ejecutar diariamente en producción)
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console app:revisar-documentos-vencimiento"

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
- [x] **Fase 16** — Historial de turnos y horas por trabajador (KPIs, resumen mensual, filtros año/mes)
- [x] **Fase 17** — Flujo formal de eventos adversos: registrado → revisión → cerrado con trazabilidad completa
- [x] **Fase 18** — Alertas in-app inmediatas para eventos graves y críticos
- [x] **Fase 19** — Notificaciones por email con Mailpit en dev, vencimiento de documentos de trabajadores con semáforo visual y comando de revisión diaria
- [x] **Fase 20** — Seguridad HTTP: NelmioSecurityBundle (CSP) + headers Nginx + comando de verificación
- [x] **Fase 21** — Exportación de liquidaciones al formato CSV Buk (división de turnos 24h, normalización RUT)
- [x] **Fase 22** — Sistema de ayuda contextual: tours Driver.js por módulo con estado por usuario
- [x] **Fase 23** — Centro de ayuda (`/ayuda`): manual del sistema, FAQ con buscador y acceso rápido
- [x] **Fase 24** — Configuración por tenant: parámetros globales configurables por clínica (identidad, facturación, operaciones, notificaciones, módulos)

## Notas técnicas

### Content Security Policy (CSP)

El CSP está gestionado por **NelmioSecurityBundle** (`config/packages/nelmio_security.yaml`), no por Nginx. Permite cargar scripts, estilos y fuentes desde `cdn.jsdelivr.net` y `cdnjs.cloudflare.com` (Bootstrap, Bootstrap Icons, FullCalendar, Driver.js). Los headers de transporte y framing (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`) los gestiona Nginx para que apliquen a todas las respuestas incluyendo redirects.

### SSL corporativo
Si el entorno tiene inspección SSL (certificado autofirmado en la cadena), instalar dependencias con `--no-plugins` para evitar que Symfony Flex intente descargar recetas vía HTTPS:
```bash
composer require vendor/paquete --no-plugins
composer dump-autoload  # regenera autoload_runtime.php
```

### Audit log
Todas las entidades marcadas con `#[Gedmo\Loggable]` registran cambios automáticamente en la tabla `ext_log_entries`. Se usa una entidad `LogEntry` personalizada con campo `data` de tipo `json` para compatibilidad con Doctrine DBAL 4.

### Messenger (notificaciones asíncronas)

El routing de mensajes está configurado explícitamente en `config/packages/messenger.yaml`:

| Mensaje | Transporte | Motivo |
|---------|-----------|--------|
| `TurnoDescubiertoMessage` | `async` | Procesado por el worker, disparado por cron |
| `RevisarTurnosDescubiertosMessage` | `async` | Tarea programada diaria |
| `BackupDatabaseMessage` | `async` | Tarea pesada, no bloquea el request |
| `DocumentoVencimientoMessage` | `async` | Disparado por comando diario |
| `EventoAdversoGraveMessage` | sync (sin routing) | Notificación in-app **inmediata** al registrar el evento |
| `EventoResponsableAsignadoMessage` | sync (sin routing) | Notificación in-app inmediata al asignar responsable |
| `PacienteEstadoCambioMessage` | sync (sin routing) | Notificación in-app inmediata al cambiar estado |

Para producción, configurar el transporte Redis en `.env.local`:
```env
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
```
Y ejecutar el worker: `php bin/console messenger:consume async`

En desarrollo, los mensajes `async` también se procesan en el mismo request si `MESSENGER_TRANSPORT_DSN` no está configurado (fallback a `sync://).
