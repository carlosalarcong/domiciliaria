# Prompt Codex — Lógica de Negocio v5

Proyecto: sistema multi-tenant Symfony 7.4 / PHP 8.3 para gestión domiciliaria de salud.
Rama de trabajo: `fix/logica-negocio-v6` (crear desde `develop`).

Aplica los 3 cambios siguientes en **commits separados**, uno por problema.
No toques nada fuera del alcance descrito en cada punto.

---

## Problema 1 — `CerrarTurnosParcialesVencidosHandler` usa hora planificada en lugar de hora real

**Archivo:** `app/src/MessageHandler/CerrarTurnosParcialesVencidosHandler.php`  
**Método:** `cerrarTenantActual()` (líneas ~65–87)

**Situación actual:**  
El handler del scheduler que cierra automáticamente turnos PARCIAL vencidos construye `registroTermino` con la hora planificada del turno, no con la hora real de cierre:

```php
$terminoDatetime = \DateTime::createFromFormat(
    'Y-m-d H:i:s',
    $turno->getFecha()->format('Y-m-d') . ' ' . $turno->getHoraTermino()->format('H:i:s'),
);

if (!$terminoDatetime instanceof \DateTime) {
    $terminoDatetime = new \DateTime();
}

$turno->setRegistroTermino($terminoDatetime)
    ->setEstado(EstadoTurno::COMPLETADO)
    ->setObservaciones(...);
```

Esto es el mismo bug que ya fue corregido en `TurnoService::forzarCierreParcial()`. El cierre manual ya usa `new \DateTime()` correctamente, pero el cierre automático (el más frecuente, ejecutado por el scheduler cada hora) sigue usando la hora planificada. Resultado: `calcularHorasEfectivas()` en `FinanzasService` calcula las horas reales como `registroTermino - registroInicio`, liquidando horas planificadas en lugar de horas efectivamente trabajadas.

**Corrección:**  
Aplicar la misma lógica que `TurnoService::forzarCierreParcial()`:

```php
foreach ($turnos as $turno) {
    if ($turno->getRegistroInicio() !== null) {
        $turno->setRegistroTermino(new \DateTime());
    } else {
        $turno->setRegistroTermino(null)
            ->setObservaciones(
                trim(($turno->getObservaciones() ?? '') .
                    ' [Sin registro de inicio — se usó duración planificada]')
            );
    }

    $turno->setEstado(EstadoTurno::COMPLETADO)
        ->setObservaciones(
            trim(($turno->getObservaciones() ?? '') .
                ' [Cierre automático por sistema el ' . (new \DateTime())->format('d/m/Y H:i') . ']')
        );

    $cerrados++;
}
```

Commit: `fix(scheduler): usar hora real al cerrar turnos parciales automáticamente`

---

## Problema 2 — `descubrirTurnosFuturos()` cancela turnos PARCIAL en curso del trabajador

**Archivos:**
- `app/src/Repository/TurnoRepository.php` → `findFuturosCubiertosDesTrabajador()` (líneas ~106–117)
- `app/src/Service/TrabajadorService.php` → `descubrirTurnosFuturos()` (líneas ~119–158)

**Situación actual:**  
Cuando se inactiva o suspende un trabajador, `descubrirTurnosFuturos()` llama a `findFuturosCubiertosDesTrabajador()`, que incluye turnos en estado PARCIAL con `fecha >= hoy`:

```php
->setParameter('estados', [EstadoTurno::CUBIERTO, EstadoTurno::PARCIAL])
```

Un turno PARCIAL con `registroInicio` ya marcado significa que el trabajador está en plena jornada. Al incluirlo, el sistema:
1. Le quita el trabajador (`setTrabajador(null)`)
2. Lo marca como DESCUBIERTO
3. Despacha `TurnoDescubiertoMessage`

Las horas ya trabajadas quedan sin atribuir a nadie y no se liquidarán. Es el mismo problema que ya fue corregido para pacientes en `findFuturosAsignadosDePaciente()` (que ahora excluye PARCIAL con `registroInicio IS NOT NULL`), pero no se aplicó el fix equivalente para trabajadores.

**Corrección:**  
Modificar `findFuturosCubiertosDesTrabajador()` para excluir turnos PARCIAL que ya tienen `registroInicio` marcado:

```php
public function findFuturosCubiertosDesTrabajador(Trabajador $trabajador): array
{
    return $this->createQueryBuilder('t')
        ->where('t.trabajador = :trabajador')
        ->andWhere('t.fecha >= :hoy')
        ->andWhere('(t.estado = :cubierto) OR (t.estado = :parcial AND t.registroInicio IS NULL)')
        ->setParameter('trabajador', $trabajador)
        ->setParameter('hoy', new \DateTime('today'))
        ->setParameter('cubierto', EstadoTurno::CUBIERTO)
        ->setParameter('parcial', EstadoTurno::PARCIAL)
        ->getQuery()
        ->getResult();
}
```

No hay cambios en `TrabajadorService::descubrirTurnosFuturos()`.

Commit: `fix(trabajadores): no cancelar turnos parciales ya iniciados al inactivar trabajador`

---

## Problema 3 — `resumenHorasPorMes()` siempre usa horas planificadas, nunca horas reales

**Archivo:** `app/src/Repository/TurnoRepository.php`  
**Método:** `resumenHorasPorMes()` (línea ~352)

**Situación actual:**  
El resumen mensual de horas de un trabajador (usado en reportes y la UI del perfil) suma siempre la duración planificada del tipo de turno:

```php
$resumen[$key]['total_horas'] += $turno->getTipoTurno()->duracionHoras();
```

Si un trabajador registró entrada a las 08:00 y salida a las 10:00 en un turno planificado de 8 horas, el resumen reporta 8 horas en lugar de 2. El dato visible en la UI no refleja la realidad.

Nota: esto no afecta la liquidación (que usa `FinanzasService::calcularHorasEfectivas()` correctamente), pero sí afecta los reportes de horas y el monitoreo operativo.

**Corrección:**  
Calcular horas efectivas usando `registroInicio` y `registroTermino` cuando estén disponibles, con fallback a `duracionHoras()` cuando no lo estén:

```php
// Reemplazar la línea actual por esta función helper dentro del método:
$horasEfectivas = $this->calcularHorasEfectivasTurno($turno);
$resumen[$key]['total_horas'] += $horasEfectivas;
```

Agregar método privado en `TurnoRepository`:

```php
private function calcularHorasEfectivasTurno(Turno $turno): float
{
    $inicio  = $turno->getRegistroInicio();
    $termino = $turno->getRegistroTermino();

    if ($inicio !== null && $termino !== null) {
        $minutos = ($termino->getTimestamp() - $inicio->getTimestamp()) / 60;
        if ($minutos < 0) {
            $minutos += 1440; // turno nocturno
        }
        return round($minutos / 60, 2);
    }

    return (float) $turno->getTipoTurno()->duracionHoras();
}
```

El método `resumenHorasPorMes()` ya carga los turnos como objetos Doctrine, por lo que `registroInicio` y `registroTermino` están disponibles sin queries adicionales.

Commit: `fix(reportes): usar horas reales en resumen mensual de trabajador`

---

## Notas generales

- Stack: PHP 8.3, Symfony 7.4, Doctrine ORM, PostgreSQL.
- El EntityManager en servicios es el tenant (`doctrine.orm.tenant_entity_manager`).
- No se requieren migraciones en ninguno de los 3 cambios.
- No modifiques tests ni archivos de configuración.
