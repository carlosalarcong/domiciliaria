# Prompt Codex — Correcciones de lógica de negocio (12 puntos)

## Contexto del sistema

Sistema SaaS multi-tenant de gestión de atención domiciliaria de salud, desarrollado en **Symfony 7.4 / PHP 8.3**.

### Entidades principales
- **Paciente** — estados: `ACTIVO | SUSPENDIDO | DADO_DE_BAJA` (`App\Enum\EstadoPaciente`)
- **Trabajador** — estados: `ACTIVO | INACTIVO | SUSPENDIDO` (`App\Enum\EstadoTrabajador`)
- **Turno** — estados: `CUBIERTO | PARCIAL | DESCUBIERTO | COMPLETADO` (`App\Enum\EstadoTurno`)
- **LiquidacionMensual** — estados: `BORRADOR | APROBADA | PAGADA | ANULADA` (`App\Enum\EstadoLiquidacion`)
- **Factura** — estados: `BORRADOR | EMITIDA | PAGADA | ANULADA | VENCIDA` (`App\Enum\EstadoFactura`)
- **EventoAdverso** — estados: `ABIERTO | EN_PROCESO | CERRADO` (`App\Enum\EstadoEvento`)

### Servicios clave
- `App\Service\TurnoService` — `app/src/Service/TurnoService.php`
- `App\Service\PacienteService` — `app/src/Service/PacienteService.php`
- `App\Service\TrabajadorService` — `app/src/Service/TrabajadorService.php`
- `App\Service\FinanzasService` — `app/src/Service/FinanzasService.php`
- `App\Service\EventoAdversoService` — `app/src/Service/EventoAdversoService.php`

### Repositorios relevantes
- `App\Repository\LiquidacionMensualRepository` — tiene `findByTrabajador(Trabajador $t): array` y `findOneByTrabajadorYPeriodo()`
- `App\Repository\TurnoRepository` — tiene `findParcialesVencidos(int $horas = 26): array` y `findFuturosCubiertosDesTrabajador()`
- `App\Repository\TurnoRepository::findByTrabajadorYRango()` — usada en liquidaciones

### Controladores relevantes
- `App\Controller\TurnoController` — rutas `/turnos`, gestiona edición, asistencia y forzar cierre
- `App\Controller\TrabajadorController` — ruta `/{id}/toggle-estado` cambia estado del trabajador
- `App\Controller\FinanzasController` — rutas `/finanzas/liquidaciones` y `/finanzas/facturas`
- `App\Controller\EventoAdversoController` — ruta `/eventos-adversos`

---

## Trabajo a realizar

Crea un branch `fix/logica-negocio-v2` desde `develop` e implementa los 12 puntos siguientes. Cada punto indica exactamente qué archivo modificar, qué lógica agregar y qué excepción lanzar.

---

## PUNTO 1 — Bloquear modificación de turnos COMPLETADOS

**Archivo:** `app/src/Service/TurnoService.php`  
**Método:** `actualizarEstadoSegunTrabajador(Turno $turno)`

**Problema:** El método no protege turnos ya completados. Se puede editar un turno COMPLETADO (que ya pudo haber sido incluido en una liquidación pagada) y dejarlo DESCUBIERTO.

**Implementar:** Al inicio del método, antes de cualquier lógica, agregar:

```php
if ($turno->getEstado() === EstadoTurno::COMPLETADO) {
    throw new \DomainException(
        'No se puede modificar un turno completado. Si necesitas corregirlo, contacta al administrador.'
    );
}
```

**También en `TurnoController`:** El método `edit()` llama a `actualizarEstadoSegunTrabajador()` y captura `\DomainException`. No requiere cambio en el controller, ya está manejado.

---

## PUNTO 2 — Validar estado del paciente al registrar eventos adversos

**Archivo:** `app/src/Service/EventoAdversoService.php`  
**Método:** `registrar(EventoAdverso $evento, User $creadoPor)`

**Problema:** No verifica si el paciente del evento está activo. Se pueden registrar eventos para pacientes dados de baja.

**Implementar:** Al inicio del método `registrar()`, antes de `setCreadoPor()`:

```php
$paciente = $evento->getPaciente();
if ($paciente !== null && $paciente->getEstado() !== \App\Enum\EstadoPaciente::ACTIVO) {
    throw new \DomainException(sprintf(
        'No se puede registrar un evento adverso: el paciente %s no está activo (estado: %s).',
        $paciente->getNombreCompleto(),
        $paciente->getEstado()->etiqueta(),
    ));
}
```

Agregar el `use App\Enum\EstadoPaciente;` al bloque de imports si no existe.

---

## PUNTO 3 — Implementar anulación de liquidaciones

**Problema:** `EstadoLiquidacion::ANULADA` existe en el enum pero no hay método para anular. Una liquidación PAGADA con error no puede corregirse.

### 3a. Agregar método en `FinanzasService`

**Archivo:** `app/src/Service/FinanzasService.php`

Agregar el siguiente método público después de `marcarPagadaLiquidacion()`:

```php
public function anularLiquidacion(LiquidacionMensual $liquidacion, string $motivo, User $anuladoPor): LiquidacionMensual
{
    if ($liquidacion->getEstado() === EstadoLiquidacion::ANULADA) {
        throw new \LogicException('La liquidación ya está anulada.');
    }

    $observacionActual = $liquidacion->getObservaciones() ?? '';
    $registro = sprintf(
        '[Anulada por %s el %s. Motivo: %s]',
        $anuladoPor->getNombreCompleto(),
        (new \DateTime())->format('d/m/Y H:i'),
        $motivo,
    );

    $liquidacion->setEstado(EstadoLiquidacion::ANULADA)
                ->setObservaciones(trim($observacionActual . ' ' . $registro));

    $this->em->flush();

    return $liquidacion;
}
```

Agregar `use App\Entity\Tenant\User;` al bloque de imports si no existe.

### 3b. Agregar ruta y acción en `FinanzasController`

**Archivo:** `app/src/Controller/FinanzasController.php`

Agregar después de `liquidacionPagar()`:

```php
#[Route('/liquidaciones/{id}/anular', name: 'liquidacion_anular', methods: ['POST'])]
#[IsGranted('FINANZAS_EDITAR')]
public function liquidacionAnular(Request $request, LiquidacionMensual $liquidacion): Response
{
    if (!$this->isCsrfTokenValid('liq_' . $liquidacion->getId(), $request->request->get('_token'))) {
        throw $this->createAccessDeniedException();
    }

    $motivo = trim($request->request->get('motivo', ''));
    if ($motivo === '') {
        $this->addFlash('danger', 'Debe ingresar un motivo para anular la liquidación.');
        return $this->redirectToRoute('app_finanzas_liquidacion_show', ['id' => $liquidacion->getId()]);
    }

    try {
        $this->finanzasService->anularLiquidacion($liquidacion, $motivo, $this->getUser());
        $this->addFlash('warning', 'Liquidación anulada correctamente.');
    } catch (\LogicException $e) {
        $this->addFlash('danger', $e->getMessage());
    }

    return $this->redirectToRoute('app_finanzas_liquidacion_show', ['id' => $liquidacion->getId()]);
}
```

### 3c. Agregar botón en la vista

**Archivo:** `app/templates/finanzas/liquidacion_show.html.twig`

Buscar la sección donde están los botones de "Aprobar" y "Marcar como pagada". Agregar un formulario de anulación que sea visible para cualquier estado que no sea ANULADA:

```twig
{% if liquidacion.estado.value != 'ANULADA' %}
<form method="post" action="{{ path('app_finanzas_liquidacion_anular', {id: liquidacion.id}) }}"
      onsubmit="return confirm('¿Seguro que deseas anular esta liquidación? Esta acción no se puede revertir.')">
    <input type="hidden" name="_token" value="{{ csrf_token('liq_' ~ liquidacion.id) }}">
    <div class="mb-2">
        <input type="text" name="motivo" class="form-control form-control-sm"
               placeholder="Motivo de anulación (obligatorio)" required>
    </div>
    <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-x-circle me-1"></i> Anular liquidación
    </button>
</form>
{% endif %}
```

---

## PUNTO 4 — Proteger turnos COMPLETADOS al dar de baja un paciente

**Archivo:** `app/src/Service/PacienteService.php`  
**Método:** `cancelarTurnosFuturos(Paciente $paciente)`

**Problema:** El método marca como DESCUBIERTO todos los turnos futuros sin filtrar los que ya están COMPLETADOS (lo cual en teoría no debería ocurrir con fechas futuras, pero sí puede ocurrir con turnos del día actual iniciados).

**Verificar en `TurnoRepository`:** El método `findFuturosCubiertosDesTrabajador()` y el equivalente para paciente `findFuturosAsignadosDePaciente()` deben excluir explícitamente los turnos en estado COMPLETADO.

**Archivo:** `app/src/Repository/TurnoRepository.php`

Buscar el método `findFuturosAsignadosDePaciente()`. Asegurarse de que filtre solo estados CUBIERTO y PARCIAL, excluyendo COMPLETADO:

```php
public function findFuturosAsignadosDePaciente(Paciente $paciente): array
{
    return $this->createQueryBuilder('t')
        ->where('t.paciente = :paciente')
        ->andWhere('t.fecha >= :hoy')
        ->andWhere('t.estado IN (:estados)')
        ->setParameter('paciente', $paciente)
        ->setParameter('hoy', new \DateTime('today'))
        ->setParameter('estados', [EstadoTurno::CUBIERTO, EstadoTurno::PARCIAL])
        ->getQuery()
        ->getResult();
}
```

Si el método ya existe con esta lógica, verificar que `EstadoTurno::COMPLETADO` no esté incluido. Si falta el filtro de estados, agregarlo.

---

## PUNTO 5 — Validar liquidaciones pendientes antes de inactivar un trabajador

**Archivo:** `app/src/Service/TrabajadorService.php`  
**Método:** `descubrirTurnosFuturos(Trabajador $trabajador)`

**Problema:** Al inactivar/suspender un trabajador se descoloca sus turnos futuros, pero si tiene liquidaciones en BORRADOR o APROBADA, quedan huérfanas sin aviso.

**Implementar:** Agregar `LiquidacionMensualRepository` como dependencia del servicio e inyectarla en el constructor:

```php
// Agregar al constructor:
private readonly \App\Repository\LiquidacionMensualRepository $liquidacionRepository,
```

Luego, al inicio del método `descubrirTurnosFuturos()`, antes de cualquier lógica:

```php
$liquidacionesPendientes = array_filter(
    $this->liquidacionRepository->findByTrabajador($trabajador),
    fn(\App\Entity\Tenant\LiquidacionMensual $l) => in_array(
        $l->getEstado(),
        [\App\Enum\EstadoLiquidacion::BORRADOR, \App\Enum\EstadoLiquidacion::APROBADA],
        true,
    ),
);

if (count($liquidacionesPendientes) > 0) {
    // No bloquear la operación, pero sí retornar la advertencia como parte del resultado.
    // Agregar un flash warning en el controller. Para eso, retornamos un array enriquecido
    // o lanzamos una excepción específica que el controller captura como advertencia.
    // Usamos una excepción de tipo \RuntimeException con un mensaje descriptivo:
    throw new \RuntimeException(sprintf(
        'El trabajador tiene %d liquidación(es) en estado Borrador o Aprobada. ' .
        'Ciérralas o anulalas antes de inactivar al trabajador para evitar liquidaciones huérfanas.',
        count($liquidacionesPendientes),
    ));
}
```

**Archivo:** `app/src/Controller/TrabajadorController.php`  
**Método:** `toggleEstado()`

Envolver la llamada a `descubrirTurnosFuturos()` capturando `\RuntimeException` como advertencia que NO bloquea la operación pero informa al usuario:

```php
try {
    $descubiertos = $this->trabajadorService->descubrirTurnosFuturos($trabajador);
    if ($descubiertos > 0) {
        $this->addFlash('warning', sprintf(
            'Se marcaron %d turno(s) futuro(s) como descubiertos porque el trabajador ya no está activo.',
            $descubiertos,
        ));
    }
} catch (\RuntimeException $e) {
    $this->addFlash('danger', $e->getMessage());
    // No se bloquea el cambio de estado, solo se advierte
}
```

---

## PUNTO 6 — Cierre automático de turnos PARCIALES vencidos (comando + scheduler)

**Problema:** `TurnoRepository::findParcialesVencidos()` existe pero no hay automatización que los cierre. Los turnos pueden quedar PARCIAL indefinidamente.

### 6a. Crear un comando Symfony

**Archivo nuevo:** `app/src/Command/CerrarTurnosparcialVencidosCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Tenant\Turno;
use App\Enum\EstadoTurno;
use App\Repository\TurnoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:turnos:cerrar-parciales-vencidos',
    description: 'Cierra forzosamente turnos que llevan más de 26 horas en estado PARCIAL.',
)]
class CerrarTurnosParcialVencidosCommand extends Command
{
    public function __construct(
        private readonly TurnoRepository $turnoRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $turnos = $this->turnoRepository->findParcialesVencidos(26);

        if (empty($turnos)) {
            $io->success('No hay turnos parciales vencidos.');
            return Command::SUCCESS;
        }

        $cerrados = 0;
        foreach ($turnos as $turno) {
            // Usar hora planificada de término como cierre
            $terminoDatetime = \DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $turno->getFecha()->format('Y-m-d') . ' ' . $turno->getHoraTermino()->format('H:i:s'),
            );

            $turno->setRegistroTermino($terminoDatetime)
                  ->setEstado(EstadoTurno::COMPLETADO)
                  ->setObservaciones(
                      trim(($turno->getObservaciones() ?? '') .
                          ' [Cierre automático por sistema el ' . (new \DateTime())->format('d/m/Y H:i') . ']')
                  );

            $cerrados++;
        }

        $this->em->flush();

        $io->success(sprintf('Se cerraron %d turno(s) parcial(es) vencidos.', $cerrados));

        return Command::SUCCESS;
    }
}
```

**Nota importante:** Este comando opera sobre la conexión de base de datos del tenant activo. Revisar si el proyecto tiene un mecanismo de iteración sobre todos los tenants (ver `TenantMigrateAllCommand` como referencia) y aplicar el mismo patrón para ejecutar este comando por cada tenant.

---

## PUNTO 7 — Definir comportamiento de paciente SUSPENDIDO para turnos nuevos

**Problema:** `EstadoPaciente::SUSPENDIDO` existe pero `TurnoService::crear()` solo bloquea `DADO_DE_BAJA`. Un paciente SUSPENDIDO puede recibir turnos nuevos, lo que puede no ser el comportamiento deseado.

**Decisión de negocio a implementar:** Un paciente SUSPENDIDO **NO puede recibir turnos nuevos**, pero sus turnos existentes se mantienen. Esto es coherente con que SUSPENDIDO = pausa temporal del servicio.

**Archivo:** `app/src/Service/TurnoService.php`  
**Método:** `crear(Turno $turno, User $creadoPor)`

Cambiar la condición de validación del paciente de:

```php
// ANTES:
if ($paciente !== null && $paciente->getEstado() !== EstadoPaciente::ACTIVO) {
```

A (ya está así, PERO agregar el mensaje más descriptivo que distinga SUSPENDIDO de DADO_DE_BAJA):

```php
// La condición ya bloquea ambos estados, solo mejorar el mensaje:
if ($paciente !== null && $paciente->getEstado() !== EstadoPaciente::ACTIVO) {
    $razon = $paciente->getEstado() === EstadoPaciente::SUSPENDIDO
        ? 'El paciente está suspendido temporalmente y no puede recibir nuevos turnos.'
        : 'El paciente está dado de baja.';
    throw new \DomainException(sprintf(
        'No se puede crear el turno: %s (Paciente: %s)',
        $razon,
        $paciente->getNombreCompleto(),
    ));
}
```

**También en `actualizarEstadoSegunTrabajador()`** aplicar el mismo mensaje diferenciado.

---

## PUNTO 8 — Validar turnos existentes al registrar no-disponibilidad

**Problema:** `TrabajadorService::registrarDisponibilidad()` no verifica si hay turnos CUBIERTO/PARCIAL en el mismo día en que se registra una no-disponibilidad.

**Archivo:** `app/src/Service/TrabajadorService.php`  
**Método:** `registrarDisponibilidad(Trabajador $trabajador, DisponibilidadTrabajador $disponibilidad)`

Agregar validación antes de persistir. Necesita `TurnoRepository` ya inyectado en el constructor. Agregar al inicio del método:

```php
// Solo validar si es una restricción de no-disponibilidad (isDisponible() === false)
// o si los horarios no cubren el turno existente
if (!$disponibilidad->isDisponible() && $disponibilidad->getFecha() !== null) {
    $turnosEnFecha = $this->turnoRepository->findByTrabajadorYFecha(
        $trabajador,
        $disponibilidad->getFecha(),
    );

    $turnosActivos = array_filter(
        $turnosEnFecha,
        fn(\App\Entity\Tenant\Turno $t) => in_array(
            $t->getEstado(),
            [\App\Enum\EstadoTurno::CUBIERTO, \App\Enum\EstadoTurno::PARCIAL],
            true,
        ),
    );

    if (count($turnosActivos) > 0) {
        throw new \DomainException(sprintf(
            'No se puede registrar no-disponibilidad el %s: el trabajador tiene %d turno(s) activo(s) asignado(s) ese día. ' .
            'Primero reasigna o descubre esos turnos.',
            $disponibilidad->getFecha()->format('d/m/Y'),
            count($turnosActivos),
        ));
    }
}
```

**Nota:** Verificar si `DisponibilidadTrabajador` tiene un campo `fecha` o si trabaja con día de la semana (recurrente). Si es recurrente (día de la semana), la validación debe comparar el día de la semana contra los turnos futuros de ese día de la semana. Ajustar la lógica según la entidad real.

---

## PUNTO 9 — Agregar campo `motivoAnulacion` en LiquidacionMensual y hacer `fechaPago` requerida a nivel dominio

**Problema:** La `fechaPago` es nullable en la entidad y se puede marcar como PAGADA sin fecha. El motivo de anulación no se persiste de forma estructurada.

### 9a. Agregar campo `motivoAnulacion` en la entidad

**Archivo:** `app/src/Entity/Tenant/LiquidacionMensual.php`

Agregar propiedad:

```php
#[ORM\Column(type: 'text', nullable: true)]
private ?string $motivoAnulacion = null;

public function getMotivoAnulacion(): ?string { return $this->motivoAnulacion; }
public function setMotivoAnulacion(?string $motivo): static { $this->motivoAnulacion = $motivo; return $this; }
```

### 9b. Usar `motivoAnulacion` en `FinanzasService::anularLiquidacion()`

En el método creado en el PUNTO 3a, reemplazar el guardado del motivo en `observaciones` por:

```php
$liquidacion->setEstado(EstadoLiquidacion::ANULADA)
            ->setMotivoAnulacion($motivo)
            ->setObservaciones(
                trim(($liquidacion->getObservaciones() ?? '') .
                    sprintf(' [Anulada por %s el %s]',
                        $anuladoPor->getNombreCompleto(),
                        (new \DateTime())->format('d/m/Y H:i'),
                    )
                )
            );
```

### 9c. Crear la migración

Ejecutar:
```bash
php bin/console doctrine:migrations:diff --em=tenant
```

Revisar el archivo generado en `app/migrations/Tenant/` y verificar que solo agrega `motivo_anulacion TEXT NULL`.

---

## PUNTO 10 — Proteger regeneración de liquidación BORRADOR con log

**Problema:** Si se regenera una liquidación ya existente en BORRADOR, los ítems anteriores se borran silenciosamente sin registro.

**Archivo:** `app/src/Service/FinanzasService.php`  
**Método:** `generarLiquidacion()`

En el bloque `else` que limpia ítems anteriores, agregar:

```php
} else {
    if (!in_array($liquidacion->getEstado(), [EstadoLiquidacion::BORRADOR], true)) {
        throw new \LogicException(sprintf(
            'Solo se puede regenerar una liquidación en estado Borrador (estado actual: %s). ' .
            'Anula la liquidación actual antes de crear una nueva.',
            $liquidacion->getEstado()->etiqueta(),
        ));
    }

    // Registrar en observaciones que fue regenerada
    $nota = sprintf(
        '[Regenerada por sistema el %s — ítems anteriores eliminados]',
        (new \DateTime())->format('d/m/Y H:i'),
    );
    $liquidacion->setObservaciones(
        trim(($liquidacion->getObservaciones() ?? '') . ' ' . $nota)
    );

    // Limpiar items anteriores
    foreach ($liquidacion->getItems() as $item) {
        $this->em->remove($item);
    }
}
```

Esto:
1. Impide regenerar una liquidación que no está en BORRADOR.
2. Deja un registro en observaciones de que fue regenerada.

---

## PUNTO 11 — Validar `fechaPago` no nula antes de marcar como PAGADA

**Problema:** La entidad `LiquidacionMensual` tiene `fechaPago` nullable, pero la lógica de negocio requiere que siempre tenga fecha al pagarse. La validación solo existe en el controller.

**Archivo:** `app/src/Service/FinanzasService.php`  
**Método:** `marcarPagadaLiquidacion()`

Agregar validación del parámetro:

```php
public function marcarPagadaLiquidacion(LiquidacionMensual $liquidacion, \DateTimeInterface $fechaPago): LiquidacionMensual
{
    if ($liquidacion->getEstado() !== EstadoLiquidacion::APROBADA) {
        throw new \LogicException(sprintf(
            'Solo se puede marcar como pagada una liquidación aprobada (estado actual: %s).',
            $liquidacion->getEstado()->etiqueta(),
        ));
    }

    // NUEVO: validar que la fecha no sea futura (más de 1 día adelante)
    $manana = new \DateTime('+1 day');
    if ($fechaPago > $manana) {
        throw new \LogicException('La fecha de pago no puede ser una fecha futura.');
    }

    $liquidacion->setEstado(EstadoLiquidacion::PAGADA)->setFechaPago($fechaPago);
    $this->em->flush();

    return $liquidacion;
}
```

Aplicar la misma validación de fecha futura en `marcarPagadaFactura()` de `FinanzasService`.

---

## PUNTO 12 — Mejorar auditoría en acciones críticas de estado

**Problema:** Cambio de estado a INACTIVO de trabajador, cancelación masiva de turnos y regeneración de liquidaciones no generan entradas de auditoría con detalle suficiente.

**El sistema ya tiene Gedmo Loggable** en entidades como `User` (ver `#[Gedmo\Loggable]` y `#[Gedmo\Versioned]`). Extender este patrón a las entidades críticas que aún no lo tienen.

### 12a. Agregar `#[Gedmo\Loggable]` y `#[Gedmo\Versioned]` en `Turno`

**Archivo:** `app/src/Entity/Tenant/Turno.php`

Agregar la anotación en la clase:

```php
use App\Entity\Tenant\Log\LogEntry;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: TurnoRepository::class)]
#[ORM\Table(name: 'turnos')]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]  // AGREGAR ESTA LÍNEA
class Turno
```

Y marcar el campo `estado` como versionado:

```php
#[ORM\Column(type: 'string', enumType: EstadoTurno::class)]
#[Gedmo\Versioned]  // AGREGAR ESTA LÍNEA
private EstadoTurno $estado = EstadoTurno::DESCUBIERTO;
```

### 12b. Agregar `#[Gedmo\Versioned]` en `LiquidacionMensual`

**Archivo:** `app/src/Entity/Tenant/LiquidacionMensual.php`

```php
#[ORM\Column(type: 'string', enumType: EstadoLiquidacion::class)]
#[Gedmo\Versioned]  // AGREGAR ESTA LÍNEA
private EstadoLiquidacion $estado = EstadoLiquidacion::BORRADOR;
```

### 12c. Agregar `#[Gedmo\Versioned]` en `Trabajador`

**Archivo:** `app/src/Entity/Tenant/Trabajador.php`

```php
#[ORM\Column(type: 'string', enumType: EstadoTrabajador::class)]
#[Gedmo\Versioned]  // AGREGAR ESTA LÍNEA
private EstadoTrabajador $estado = EstadoTrabajador::ACTIVO;
```

### 12d. Verificar que `LogEntry` ya gestiona estas entidades

**Archivo:** `app/src/Controller/AuditoriaController.php`

En la constante `ENTITY_LABELS`, agregar las entidades nuevas si no están:

```php
private const ENTITY_LABELS = [
    'App\\Entity\\Tenant\\Paciente'            => 'Paciente',
    'App\\Entity\\Tenant\\Trabajador'          => 'Trabajador',
    'App\\Entity\\Tenant\\Turno'               => 'Turno',          // ya existe, confirmar
    'App\\Entity\\Tenant\\LiquidacionMensual'  => 'Liquidación',    // AGREGAR si falta
    'App\\Entity\\Tenant\\Mandante'            => 'Mandante',
    'App\\Entity\\Tenant\\EventoAdverso'       => 'Evento Adverso',
    'App\\Entity\\Tenant\\User'                => 'Usuario',
];
```

### 12e. Generar migración para los cambios de entidades

Después de agregar los campos de `Gedmo\Loggable`, ejecutar:

```bash
php bin/console doctrine:migrations:diff --em=tenant
```

Revisar el archivo generado — los logs de Gedmo se guardan en la tabla `log_entries` que ya existe, no debería crear tablas nuevas. Solo la columna `motivo_anulacion` del PUNTO 9 genera DDL nuevo.

---

## Resumen de archivos a crear o modificar

| Archivo | Acción | Puntos |
|---------|--------|--------|
| `app/src/Service/TurnoService.php` | Modificar | 1, 7 |
| `app/src/Service/EventoAdversoService.php` | Modificar | 2 |
| `app/src/Service/FinanzasService.php` | Modificar | 3a, 10, 11 |
| `app/src/Service/TrabajadorService.php` | Modificar | 5, 8 |
| `app/src/Service/PacienteService.php` | Verificar | 4 |
| `app/src/Controller/FinanzasController.php` | Modificar | 3b |
| `app/src/Controller/TrabajadorController.php` | Modificar | 5 |
| `app/src/Controller/AuditoriaController.php` | Modificar | 12d |
| `app/src/Entity/Tenant/LiquidacionMensual.php` | Modificar | 9a, 12b |
| `app/src/Entity/Tenant/Turno.php` | Modificar | 12a |
| `app/src/Entity/Tenant/Trabajador.php` | Modificar | 12c |
| `app/src/Repository/TurnoRepository.php` | Verificar/modificar | 4 |
| `app/templates/finanzas/liquidacion_show.html.twig` | Modificar | 3c |
| `app/src/Command/CerrarTurnosParcialVencidosCommand.php` | Crear | 6 |
| `app/migrations/Tenant/VersionXXX.php` | Crear (auto) | 9c, 12e |

## Instrucciones finales

1. Crear branch: `git checkout -b fix/logica-negocio-v2 develop`
2. Implementar los 12 puntos en el orden listado
3. Para cada punto que toque entidades, ejecutar `php bin/console doctrine:migrations:diff --em=tenant` al final y revisar el diff
4. Ejecutar `php bin/console cache:clear` después de los cambios
5. Commitear con mensaje descriptivo por grupo lógico:
   - `fix(turnos): bloquear edición de turnos completados` (puntos 1, 4, 7)
   - `fix(eventos): validar estado del paciente al registrar eventos adversos` (punto 2)
   - `feat(finanzas): implementar anulación de liquidaciones` (puntos 3, 10, 11)
   - `fix(trabajador): validar liquidaciones pendientes al inactivar` (puntos 5, 8)
   - `feat(command): cerrar automáticamente turnos parciales vencidos` (punto 6)
   - `feat(auditoria): extender loggable a turnos, liquidaciones y trabajadores` (punto 12)
   - `chore(migrations): agregar campo motivo_anulacion en liquidaciones` (punto 9)
