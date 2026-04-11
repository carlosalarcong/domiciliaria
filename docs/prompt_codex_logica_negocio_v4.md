# Prompt Codex — Lógica de Negocio v4

Proyecto: sistema multi-tenant Symfony 7.4 / PHP 8.3 para gestión domiciliaria de salud.
Rama de trabajo: `fix/logica-negocio-v5` (crear desde `develop`).

Aplica los 5 cambios siguientes **en commits separados**, uno por problema.
No toques nada fuera del alcance descrito en cada punto.

---

## Problema 1 — `forzarCierreParcial()` liquida horas planificadas en lugar de horas reales

**Archivo:** `app/src/Service/TurnoService.php`  
**Método:** `forzarCierreParcial()` (líneas ~303–323)

**Situación actual:**  
El cierre forzado construye el `registroTermino` con la hora planificada del turno:

```php
$terminoDatetime = \DateTime::createFromFormat(
    'Y-m-d H:i:s',
    $turno->getFecha()->format('Y-m-d') . ' ' . $turno->getHoraTermino()->format('H:i:s'),
);
$turno->setRegistroTermino($terminoDatetime);
```

Luego, `calcularHorasEfectivas()` en `FinanzasService` usa `registroTermino - registroInicio` para determinar las horas a pagar. Si el trabajador entró a las 08:00 y el admin forzó el cierre a las 10:00 con turno planificado hasta las 16:00, el sistema liquida 8 horas reales en lugar de 2. Sobrepago directo.

**Corrección:**  
Usar la hora **actual** como `registroTermino` en el cierre forzado, no la hora planificada:

```php
$turno->setRegistroTermino(new \DateTime())
    ->setEstado(EstadoTurno::COMPLETADO)
    ->setObservaciones(
        trim(($turno->getObservaciones() ?? '') .
            ' [Cierre forzado por ' . $autor->getNombreCompleto() .
            ' el ' . (new \DateTime())->format('d/m/Y H:i') . ']')
    );
```

Si `registroInicio` es `null` (nadie registró la entrada pero el turno está en PARCIAL por algún motivo), **no** usar hora actual como registroTermino. En ese caso, dejar `registroTermino = null` y confiar en el fallback de `calcularHorasEfectivas()` que usa `duracionHoras()` del tipo de turno, pero agregar una nota en observaciones que diga `[Sin registro de inicio — se usó duración planificada]`.

Commit: `fix(turnos): usar hora real en cierre forzado de turno parcial`

---

## Problema 2 — Dar de baja o suspender un paciente cancela turnos PARCIAL en curso

**Archivos:**  
- `app/src/Repository/TurnoRepository.php` → `findFuturosAsignadosDePaciente()` (líneas ~120–131)  
- `app/src/Service/PacienteService.php` → `cancelarTurnosFuturos()` (líneas ~142–165)

**Situación actual:**  
`findFuturosAsignadosDePaciente()` filtra `fecha >= hoy` e incluye estado `PARCIAL`. Un turno PARCIAL con `fecha = hoy` y `registroInicio` ya marcado (trabajador en plena jornada) queda incluido y se cancela inmediatamente, dejando al trabajador trabajando un turno que el sistema ya marcó como DESCUBIERTO. Ese turno no se liquidará.

**Corrección:**  
Excluir de la cancelación los turnos PARCIAL que ya tienen `registroInicio` no nulo (turno en ejecución activa). Modificar la query para que los turnos PARCIAL solo se incluyan si `registroInicio IS NULL`:

```php
// En TurnoRepository::findFuturosAsignadosDePaciente()
return $this->createQueryBuilder('t')
    ->where('t.paciente = :paciente')
    ->andWhere('t.fecha >= :hoy')
    ->andWhere(
        '(t.estado = :cubierto) OR (t.estado = :parcial AND t.registroInicio IS NULL)'
    )
    ->setParameter('paciente', $paciente)
    ->setParameter('hoy', new \DateTime('today'))
    ->setParameter('cubierto', EstadoTurno::CUBIERTO)
    ->setParameter('parcial', EstadoTurno::PARCIAL)
    ->getQuery()
    ->getResult();
```

No hay cambios en `PacienteService::cancelarTurnosFuturos()`.

Commit: `fix(pacientes): no cancelar turnos parciales ya iniciados al dar de baja`

---

## Problema 3 — `asignarReemplazo()` no registra el trabajador original

**Archivos:**  
- `app/src/Service/TurnoService.php` → `asignarReemplazo()` (líneas ~213–257)  
- `app/src/Entity/Tenant/Turno.php` → campo `turnoOriginal` existe pero nunca se usa (línea ~68)

**Situación actual:**  
Cuando se asigna un reemplazo, `setTrabajador($reemplazo)` sobreescribe al trabajador original sin guardar referencia. La entidad `Turno` tiene el campo `turnoOriginal` (self-referencing) pero está vacío. No hay forma de saber quién tenía el turno antes de ser reemplazado.

**Corrección:**  
El campo `turnoOriginal` es una auto-referencia a otro turno, lo que no aplica aquí. El problema real es que se necesita guardar el **trabajador original**, no el turno original. Agrega un campo `trabajadorOriginal` en la entidad `Turno`:

```php
// En Turno.php — agregar campo junto a los otros campos de trabajador
#[ORM\ManyToOne(targetEntity: Trabajador::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
#[Gedmo\Versioned]
private ?Trabajador $trabajadorOriginal = null;

public function getTrabajadorOriginal(): ?Trabajador { return $this->trabajadorOriginal; }
public function setTrabajadorOriginal(?Trabajador $t): static { $this->trabajadorOriginal = $t; return $this; }
```

En `TurnoService::asignarReemplazo()`, guardar el trabajador original **antes** de sobreescribir:

```php
// Solo guardar si aún no tiene trabajadorOriginal (no sobreescribir en reemplazos sucesivos)
if ($turno->getTrabajadorOriginal() === null && $turno->getTrabajador() !== null) {
    $turno->setTrabajadorOriginal($turno->getTrabajador());
}
$turno->setTrabajador($reemplazo);
$turno->setEsReemplazo(true);
$turno->setMotivoReemplazo($motivo);
$turno->setEstado(EstadoTurno::CUBIERTO);
```

Genera la migración Doctrine correspondiente con:
```
php bin/console doctrine:migrations:diff
```

Commit: `feat(turnos): registrar trabajador original al asignar reemplazo`

---

## Problema 4 — Liquidación PAGADA puede borrarse regenerándola tras anularla

**Archivo:** `app/src/Service/FinanzasService.php`  
**Métodos:** `generarLiquidacion()` (líneas ~42–80) y `anularLiquidacion()` (líneas ~179–199)

**Situación actual:**  
Flujo posible:
1. Liquidación en estado `PAGADA`
2. `anularLiquidacion()` la pasa a `ANULADA` (no valida si fue PAGADA)
3. `generarLiquidacion()` la encuentra en `ANULADA` → la resetea a `BORRADOR`, borra todos los ítems, limpia el motivo de anulación

Una liquidación que ya fue pagada queda con todos sus ítems borrados y vuelve a estado BORRADOR. El historial financiero se pierde.

**Corrección:**  
Bloquear la regeneración de liquidaciones que alguna vez fueron `PAGADA`. Como la entidad no tiene campo de historial de estado, la solución es agregar un flag `fuePagada` o, más simple, verificar el estado en `generarLiquidacion()` bloqueando explícitamente la regeneración desde `ANULADA` si la liquidación tiene `observaciones` con el patrón `[Anulada]` Y hubo un estado PAGADA anterior.

La solución más robusta es agregar un campo `boolean $fuePagada` en `LiquidacionMensual`:

```php
// En LiquidacionMensual.php
#[ORM\Column(type: 'boolean', options: ['default' => false])]
private bool $fuePagada = false;

public function isFuePagada(): bool { return $this->fuePagada; }
public function setFuePagada(bool $v): static { $this->fuePagada = $v; return $this; }
```

En `FinanzasService`, cuando se aprueba el pago de la liquidación (busca el método que transita a PAGADA), marcar `setFuePagada(true)`.

En `generarLiquidacion()`, al intentar regenerar desde ANULADA:

```php
if ($liquidacion->getEstado() === EstadoLiquidacion::ANULADA) {
    if ($liquidacion->isFuePagada()) {
        throw new \LogicException(
            'No se puede regenerar esta liquidación: fue pagada previamente. ' .
            'Crea una nueva liquidación de ajuste si es necesario.'
        );
    }
    $liquidacion->setEstado(EstadoLiquidacion::BORRADOR)
        ->setMotivoAnulacion(null);
}
```

Genera migración Doctrine.

Commit: `fix(finanzas): bloquear regeneración de liquidación previamente pagada`

---

## Problema 5 — Carrera temporal: liquidación generada antes de que el scheduler cierre turnos PARCIAL vencidos

**Archivos:**  
- `app/src/Service/FinanzasService.php` → `generarLiquidacion()` (líneas ~83–95)

**Situación actual:**  
`generarLiquidacion()` solo incluye turnos en estado `COMPLETADO`. Si existen turnos en estado `PARCIAL` con fecha dentro del período (vencidos pero aún no procesados por el scheduler), esos turnos quedan silenciosamente excluidos. El trabajador pierde esas horas sin ningún aviso.

**Corrección:**  
Antes de calcular los ítems, detectar si hay turnos PARCIAL dentro del período y lanzar una advertencia que bloquee la generación:

```php
// Antes del foreach de turnos en generarLiquidacion()
$turnosParciales = array_filter(
    $turnos,
    fn(Turno $t) => $t->getEstado() === EstadoTurno::PARCIAL,
);

if (count($turnosParciales) > 0) {
    throw new \LogicException(sprintf(
        'No se puede generar la liquidación: hay %d turno(s) en estado Parcial dentro del período ' .
        '(%s/%s). Espera a que el sistema los cierre automáticamente o fuerza el cierre manualmente antes de liquidar.',
        count($turnosParciales),
        str_pad((string) $mes, 2, '0', STR_PAD_LEFT),
        $anio,
    ));
}
```

Esto convierte el problema silencioso en un error explícito que el usuario puede resolver antes de generar la liquidación.

Commit: `fix(finanzas): bloquear liquidación si hay turnos parciales sin cerrar en el período`

---

## Notas generales

- Stack: PHP 8.3, Symfony 7.4, Doctrine ORM, PostgreSQL.
- El EntityManager por defecto en los servicios ya es el tenant (`doctrine.orm.tenant_entity_manager`).
- Los enums PHP son backed enums con métodos `etiqueta()` y `color()`.
- No modifiques tests ni archivos de configuración salvo `services.yaml` si el nuevo campo de entidad requiere un repositorio adicional.
- Para las migraciones: ejecuta `php bin/console doctrine:migrations:diff` dentro del contenedor Docker del proyecto y deja el archivo generado en `app/migrations/`.
