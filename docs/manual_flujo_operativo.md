# Manual de Flujo Operativo — Domiciliaria SaaS

> Guía paso a paso para operar el sistema: desde la configuración inicial hasta la gestión diaria de turnos y finanzas.
> Versión: abril 2026

---

## Tabla de contenidos

1. [Roles del sistema](#1-roles-del-sistema)
2. [Paso 1 — Configuración inicial (Admin)](#2-paso-1--configuración-inicial-admin)
3. [Paso 2 — Crear usuarios del sistema](#3-paso-2--crear-usuarios-del-sistema)
4. [Paso 3 — Registrar mandantes (clientes)](#4-paso-3--registrar-mandantes-clientes)
5. [Paso 4 — Registrar trabajadores (profesionales)](#5-paso-4--registrar-trabajadores-profesionales)
6. [Paso 5 — Registrar pacientes](#6-paso-5--registrar-pacientes)
7. [Paso 6 — Crear y gestionar turnos](#7-paso-6--crear-y-gestionar-turnos)
8. [Paso 7 — Registrar asistencia de un turno](#8-paso-7--registrar-asistencia-de-un-turno)
9. [Paso 8 — Finanzas: liquidaciones y facturas](#9-paso-8--finanzas-liquidaciones-y-facturas)
10. [Módulo de eventos adversos](#10-módulo-de-eventos-adversos)
11. [Auditoría y trazabilidad](#11-auditoría-y-trazabilidad)
12. [Resumen de estados](#12-resumen-de-estados)

---

## 1. Roles del sistema

| Rol | Etiqueta | Qué puede hacer |
|-----|----------|-----------------|
| `ROLE_ADMIN` | Administrador | Acceso total: usuarios, trabajadores, pacientes, mandantes, configuración, auditoría, finanzas |
| `ROLE_COORDINADOR` | Coordinador | Turnos, trabajadores (ver/editar disponibilidad/documentos), pacientes, eventos adversos, finanzas (ver) |
| `ROLE_ENFERMERA` | Enfermera | Ver pacientes, agregar bitácora y comunicaciones |
| `ROLE_TENS` | TENS | Igual que Enfermera |
| `ROLE_VISUALIZADOR` | Visualizador | Solo lectura de pacientes |

> **Nota de seguridad:** Los roles `ROLE_ADMIN` y `ROLE_COORDINADOR` tienen **2FA obligatorio** (Google Authenticator / Authy). Al primer login deben configurar su aplicación de autenticación.

---

## 2. Paso 1 — Configuración inicial (Admin)

**Ruta:** `/configuracion`  
**Acceso:** Solo Administrador

Antes de operar el sistema configura los parámetros de la clínica:

- Nombre de la organización
- Datos de contacto
- Parámetros generales de operación

### También en configuración: Tarifas

**Ruta:** `/configuracion/tarifas`  
**Acceso:** Roles con permiso `FINANZAS_VER`

Define las tarifas base que se usarán al calcular liquidaciones:

- Crear tarifa: tipo de concepto (hora normal, hora extra, bono, descuento) y monto
- Editar tarifa existente
- Eliminar tarifa

---

## 3. Paso 2 — Crear usuarios del sistema

**Ruta:** `/usuarios`  
**Acceso:** Solo Administrador

Los usuarios son las personas que acceden al sistema (coordinadores, enfermeras, TENS, etc.). No confundir con los trabajadores/profesionales, que son una entidad separada.

### Qué puedes hacer en este módulo

| Acción | Descripción |
|--------|-------------|
| **Listar usuarios** | Ver todos los usuarios paginados, ordenados por apellido |
| **Crear usuario** | Nombre, apellido, email, contraseña y rol. Email debe ser único |
| **Ver detalle** | Información del usuario y su estado |
| **Editar usuario** | Modificar nombre, apellido, email y rol |
| **Cambiar contraseña** | Formulario separado para actualizar la contraseña de un usuario |
| **Activar / Desactivar** | Habilitar o bloquear el acceso al sistema sin eliminar el usuario |

### Flujo recomendado

1. Crear usuario con email y contraseña temporal
2. Asignar el rol adecuado según la función de la persona
3. Comunicar credenciales al usuario
4. El usuario entra al sistema y, si tiene rol Admin o Coordinador, configura su 2FA

---

## 4. Paso 3 — Registrar mandantes (clientes)

**Ruta:** `/mandantes`  
**Acceso:** Coordinador y Admin (solo Admin puede crear/editar/activar)

Los mandantes son las instituciones o empresas que contratan los servicios (isapres, municipios, empresas, etc.). Son necesarios antes de registrar pacientes porque cada paciente se asocia a un mandante.

### Qué puedes hacer en este módulo

| Acción | Descripción |
|--------|-------------|
| **Listar mandantes** | Ver todos los mandantes paginados |
| **Crear mandante** | Nombre, RUT, contacto y datos de facturación |
| **Ver detalle** | Información del mandante |
| **Editar mandante** | Modificar datos del mandante |
| **Activar / Desactivar** | Suspender relación con un mandante sin eliminarlo |

---

## 5. Paso 4 — Registrar trabajadores (profesionales)

**Ruta:** `/trabajadores`  
**Acceso:** Coordinador y Admin (crear/editar/cambiar estado solo Admin)

Los trabajadores son los profesionales de salud que ejecutan los turnos (TENS, cuidadores, enfermeras/os).

### Qué puedes hacer en este módulo

| Acción | Descripción |
|--------|-------------|
| **Listar trabajadores** | Ver todos los profesionales paginados |
| **Crear trabajador** | Nombre, RUT, perfil (TENS / Cuidador / Enfermera / Otro), contacto |
| **Ver detalle** | Ficha completa con pestañas: turnos, documentos, disponibilidad, horas |
| **Editar trabajador** | Modificar datos personales y perfil |
| **Cambiar estado** | Activar, desactivar o suspender. Si se desactiva/suspende, los turnos futuros quedan automáticamente en estado **Descubierto** |

### Pestaña: Documentos

Desde el detalle del trabajador puedes gestionar su carpeta documental:

| Acción | Descripción |
|--------|-------------|
| **Subir documento** | Adjuntar archivo con tipo (Contrato, CV, Certificado, Título, Licencia médica, Otro), descripción y fecha de vencimiento |
| **Descargar documento** | Bajar el archivo adjunto |
| **Eliminar documento** | Borrar un documento (solo Admin) |

> El sistema monitorea fechas de vencimiento y genera alertas cuando un documento está próximo a vencer.

### Pestaña: Disponibilidad

Define los bloques horarios en que el trabajador está disponible para ser asignado:

| Acción | Descripción |
|--------|-------------|
| **Registrar disponibilidad** | Día de la semana, hora inicio y hora término |
| **Eliminar bloque** | Quitar un horario de disponibilidad |

> Al crear un turno, el sistema verifica en tiempo real si el trabajador tiene disponibilidad en ese horario.

### Pestaña: Horas trabajadas

| Acción | Descripción |
|--------|-------------|
| **Ver resumen** | Horas y turnos por mes para un año seleccionado |
| **Exportar CSV** | Descargar el detalle de horas para un rango de fechas (útil para liquidaciones) |

---

## 6. Paso 5 — Registrar pacientes

**Ruta:** `/pacientes`  
**Acceso:** Todos los roles (creación requiere permiso `PACIENTE_CREAR`)

### Qué puedes hacer en este módulo

| Acción | Descripción |
|--------|-------------|
| **Listar pacientes** | Filtrar por estado, mandante y tipo de servicio |
| **Exportar CSV** | Descargar listado completo (Coordinador y Admin) |
| **Crear paciente** | Datos personales, diagnóstico, domicilio, mandante y tipo de servicio |
| **Ver detalle** | Ficha clínica con pestañas: información, bitácora, comunicaciones, condición del domicilio, turnos |
| **Editar paciente** | Modificar datos (requiere permiso `PACIENTE_EDITAR`) |
| **Dar de baja** | Cierre del caso con motivo. Los turnos futuros del paciente quedan automáticamente en estado **Descubierto** |

### Pestaña: Bitácora operativa

Registro cronológico de eventos clínicos y operativos del paciente:

- Agregar entrada con tipo (observación clínica, incidente, comunicación, etc.) y descripción
- Ver historial completo

### Pestaña: Comunicaciones

Registro de contactos con el paciente o su familia:

- Agregar comunicación: tipo (llamada, email, visita, WhatsApp, etc.), contacto y descripción
- Ver historial

### Pestaña: Condición del domicilio

Datos sobre las condiciones del hogar del paciente relevantes para la atención:

- Editar condición: acceso, equipamiento disponible, observaciones de seguridad

### Estados del paciente

```
ACTIVO → SUSPENDIDO → ACTIVO     (suspensión temporal)
ACTIVO → DADO DE BAJA            (cierre definitivo)
```

---

## 7. Paso 6 — Crear y gestionar turnos

**Ruta:** `/turnos`  
**Acceso:** Coordinador y Admin

Este es el módulo central de la operación. Un turno representa la asignación de un trabajador para atender a un paciente en un horario determinado.

### Vista principal: Calendario

La pantalla de turnos muestra un **calendario interactivo** (FullCalendar) con código de colores:

| Color | Estado |
|-------|--------|
| Verde | Cubierto |
| Amarillo | Parcial |
| Rojo | Descubierto |
| Azul | Completado |

Puedes filtrar por trabajador, estado y tipo de turno.

### Qué puedes hacer en este módulo

| Acción | Descripción |
|--------|-------------|
| **Crear turno** | Seleccionar paciente, trabajador, fecha, hora inicio/término y tipo de turno |
| **Ver detalle** | Información completa del turno: paciente, trabajador, horario, estado, historial |
| **Editar turno** | Modificar datos del turno (si el paciente o trabajador no están activos, no se puede) |
| **Asignar reemplazo** | Cuando el trabajador original no puede asistir, asignar otro trabajador con motivo del reemplazo |
| **Registrar asistencia** | Marcar inicio y término del turno en tiempo real |
| **Forzar cierre** | Cerrar un turno parcial usando la hora planificada de término (para turnos que iniciaron pero no se cerraron correctamente) |
| **Exportar CSV** | Descargar turnos de un rango de fechas |

### Tipos de turno

| Tipo | Duración |
|------|----------|
| Turno 12h Día | 12 horas |
| Turno 12h Noche | 12 horas |
| Turno 24h | 24 horas |
| Visita | 2 horas |

### Validaciones importantes al crear un turno

- El paciente debe estar en estado **Activo**
- El trabajador debe estar en estado **Activo**
- El trabajador no puede tener otro turno superpuesto en el mismo horario
- El sistema verifica la disponibilidad del trabajador en tiempo real al seleccionarlo

### Flujo de estados de un turno

```
DESCUBIERTO ──► (asignar trabajador) ──► CUBIERTO
                                              │
                                    (registrar inicio)
                                              │
                                           PARCIAL
                                              │
                                   (registrar término)
                                              │
                                          COMPLETADO
```

Un turno queda en **DESCUBIERTO** automáticamente si:
- Se crea sin trabajador asignado
- El trabajador asignado es desactivado o suspendido
- El paciente es dado de baja

---

## 8. Paso 7 — Registrar asistencia de un turno

Desde el detalle de un turno en estado **CUBIERTO**:

1. Pulsar **"Iniciar turno"** → el estado cambia a **PARCIAL** y se registra la hora real de inicio
2. Al terminar la atención, pulsar **"Finalizar turno"** → el estado cambia a **COMPLETADO** y se registra la hora real de término

Si el turno queda en **PARCIAL** y no se registró el término (ej. fallo del sistema):
- Un Coordinador o Admin puede usar **"Forzar cierre"** para completarlo usando la hora planificada de término

---

## 9. Paso 8 — Finanzas: liquidaciones y facturas

**Ruta:** `/finanzas`  
**Acceso:** Roles con permiso `FINANZAS_VER` (edición requiere `FINANZAS_EDITAR`)

### Dashboard

Resumen con montos de liquidaciones y facturas agrupados por estado.

### Liquidaciones (pago a trabajadores)

Las liquidaciones calculan lo que se le debe pagar a cada trabajador por sus turnos completados en un período.

| Acción | Descripción |
|--------|-------------|
| **Listar liquidaciones** | Filtrar por año y estado |
| **Generar liquidación** | Seleccionar trabajador, año y mes. El sistema calcula automáticamente los ítems según turnos completados y tarifas configuradas |
| **Ver detalle** | Desglose de ítems, montos y estado |
| **Aprobar liquidación** | Cambiar estado a Aprobada para autorizar el pago |
| **Marcar como pagada** | Registrar fecha de pago efectivo |
| **Exportar CSV individual** | Descargar detalle de una liquidación |
| **Exportar para BUK** | Exportar todas las liquidaciones aprobadas de un período en formato compatible con la plataforma BUK |

**Estados de liquidación:** `BORRADOR → APROBADA → PAGADA`

### Facturas (cobro a mandantes)

Las facturas representan el cobro a los mandantes (clientes) por los servicios prestados en un período.

| Acción | Descripción |
|--------|-------------|
| **Listar facturas** | Filtrar por año y estado |
| **Crear factura** | Seleccionar mandante, período, monto neto, número de factura y descuentos por turnos descubiertos |
| **Ver detalle** | Datos completos de la factura |
| **Emitir factura** | Cambiar estado a Emitida (equivale a "enviada al cliente") |
| **Marcar como pagada** | Registrar fecha de pago del mandante |
| **Exportar CSV** | Descargar listado de facturas del año |

**Estados de factura:** `BORRADOR → EMITIDA → PAGADA`

### Reportes financieros

**Ruta:** `/finanzas/reportes`

Reportes con filtro por año y tipo de vista:

| Vista | Descripción |
|-------|-------------|
| **Por mandante** | Resumen de facturación por cliente: total facturas, montos neto/IVA/total, pagado vs pendiente |
| **Por trabajador** | Resumen de liquidaciones por profesional |
| **Por paciente** | Cantidad de turnos por paciente, desglosado por tipo y estado |
| **Flujo ingreso/egreso** | Gráfico de ingresos (facturas) vs egresos (liquidaciones) mes a mes |

---

## 10. Módulo de eventos adversos

**Ruta:** `/eventos-adversos`  
**Acceso:** Coordinador y Admin

Permite registrar y hacer seguimiento de incidentes ocurridos durante la atención domiciliaria.

### Qué puedes hacer en este módulo

| Acción | Descripción |
|--------|-------------|
| **Listar eventos** | Filtrar por estado y gravedad. Ver conteo por estado |
| **Registrar evento** | Tipo de evento, descripción, paciente/trabajador involucrado, gravedad y responsable asignado |
| **Ver detalle** | Información del evento y bitácora de seguimiento |
| **Editar evento** | Modificar datos (solo si no está cerrado) |
| **Agregar seguimiento** | Añadir notas de seguimiento al evento |
| **Poner en revisión** | Cambiar estado a "En revisión" para indicar que se está investigando |
| **Cerrar evento** | Cerrar con observación de resolución (obligatoria) |

**Estados del evento:** `ABIERTO → EN REVISIÓN → CERRADO`

**Gravedades disponibles:** Leve, Moderado, Grave, Crítico

---

## 11. Auditoría y trazabilidad

**Ruta:** `/auditoria`  
**Acceso:** Solo Administrador

El sistema registra automáticamente todos los cambios realizados sobre entidades críticas.

### Historial de cambios

Filtros disponibles:
- **Entidad:** Paciente, Trabajador, Turno, Mandante, Evento Adverso, Usuario
- **Usuario:** quién realizó el cambio
- **Acción:** creación, actualización, eliminación
- **Rango de fechas**

### Historial de accesos

**Ruta:** `/auditoria/accesos`

Registro de todos los logins y eventos de seguridad:
- Filtrar por tipo de evento, IP o email del usuario
- Permite detectar intentos de acceso no autorizados

---

## 12. Resumen de estados

### Trabajador
```
ACTIVO ⇄ INACTIVO
ACTIVO ⇄ SUSPENDIDO
```
Al desactivar/suspender un trabajador, sus turnos futuros quedan automáticamente en **DESCUBIERTO**.

### Paciente
```
ACTIVO → SUSPENDIDO → ACTIVO
ACTIVO → DADO DE BAJA
```
Al dar de baja a un paciente, sus turnos futuros quedan automáticamente en **DESCUBIERTO**.

### Turno
```
DESCUBIERTO → CUBIERTO → PARCIAL → COMPLETADO
```

### Liquidación
```
BORRADOR → APROBADA → PAGADA
```

### Factura
```
BORRADOR → EMITIDA → PAGADA
```

### Evento adverso
```
ABIERTO → EN REVISIÓN → CERRADO
```

---

## Flujo operativo completo (resumen)

```
[Admin] Configurar sistema
    └── Definir tarifas
    └── Crear usuarios del sistema

[Admin] Registrar mandantes (clientes/isapres)

[Admin] Registrar trabajadores
    └── Subir documentos (contrato, título, etc.)
    └── Definir disponibilidad horaria

[Coordinador] Registrar pacientes
    └── Asociar a mandante
    └── Registrar condición del domicilio

[Coordinador] Crear turnos
    └── Asignar trabajador + paciente + fecha/hora
    └── El sistema valida disponibilidad y estado de ambos

[Coordinador/Trabajador] Registrar asistencia
    └── Inicio del turno → estado PARCIAL
    └── Término del turno → estado COMPLETADO

[Admin/Coordinador] Fin de mes: Finanzas
    └── Generar liquidaciones para trabajadores
    └── Aprobar y marcar como pagadas
    └── Generar facturas para mandantes
    └── Emitir y marcar como pagadas
    └── Exportar reportes
```
