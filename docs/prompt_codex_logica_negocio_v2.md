# Prompt Codex — Correcciones de lógica de negocio v2 (7 puntos)

## Contexto del sistema

Sistema SaaS multi-tenant de gestión de atención domiciliaria de salud, desarrollado en **Symfony 7.4 / PHP 8.3**.

### Entidades principales
- **Paciente** — estados: `ACTIVO | SUSPENDIDO | DADO_DE_BAJA` (`App\Enum\EstadoPaciente`)
- **Trabajador** — estados: `ACTIVO | INACTIVO | SUSPENDIDO` (`App\Enum\EstadoTrabajador`)
- **Turno** — estados: `CUBIERTO | PARCIAL | DESCUBIERTO | COMPLETADO` (`App\Enum\EstadoTurno`)
- **Factura** — estados: `BORRADOR | EMITIDA | PAGADA | VENCIDA | ANULADA` (`App\Enum\EstadoFactura`)
- **Tarifa** — campos: `tipoConcepto`, `mandante` (nullable), `vigenciaDesde`, `vigenciaHasta` (nullable), `activa`

### Servicios clave
- `App\Service\PacienteService` — `app/src/Service/PacienteService.php`
- `App\Service\TurnoService` — `app/src/Service/TurnoService.php`
- `App\Service\MandanteService` — `app/src/Service/MandanteService.php`
- `App\Service\FinanzasService` — `app/src/Service/FinanzasService.php`

### Repositorios relevantes
- `App\Repository\FacturaRepository` — tiene `findVencidas(): array` (retorna facturas EMITIDA con `fechaVencimiento < hoy`)
- `App\Repository\PacienteRepository` — tiene `findByMandante(Mandante $m): array`
- `App\Repository\TarifaRepository` — tiene `findTarifaVigente(TipoConcepto, DateTimeInterface, ?Mandante)`
- `App\Repository\TurnoRepository` — tiene `findByTrabajadorYFecha(Trabajador, DateTimeInterface): array`

### Controladores relevantes
- `App\Controller\TurnoController` — `app/src/Controller/TurnoController.php`
- `App\Controller\TarifaController` — `app/src/Controller/TarifaController.php`
- `App\Controller\MandanteController` — `app/src/Controller/MandanteController.php`

### Schedulers/Commands de referencia
- `App\Scheduler\CerrarTurnosParcialesVencidosSchedule` — patrón de scheduler horario multi-tenant
- `App\MessageHandler\CerrarTurnosParcialesVencidosHandler` — patrón de handler multi-tenant con `SwitchDbEvent`

---

## Trabajo a realizar

Crea un branch `fix/logica-negocio-v3` desde `develop` e implementa los 7 puntos siguientes.

---

## PUNTO 1 — `PacienteService::cancelarTurnosFuturos()` no desvincula trabajador ni notifica

**Archivo:** `app/src/Service/PacienteService.php`
**Método:** `cancelarTurnosFuturos(Paciente $paciente)`

**Problema:** Al suspender o dar de baja a un paciente, los turnos pasan a `DESCUBIERTO` pero el campo `trabajador` queda con la referencia anterior. El trabajador sigue "asignado" a un turno descubierto y no recibe ninguna notificación. El patrón correcto está en `TrabajadorService::descubrirTurnosFuturos()`.

**Implementar:** Reemplazar el método completo por:

```php
public function cancelarTurnosFuturos(Paciente $paciente): int
{
    $turnos = $this->turnoRepository->findFuturosAsignadosDePaciente($paciente);

    foreach ($turnos as $turno) {
        $turno->setTrabajador(null);
        $turno->setEstado(EstadoTurno::DESCUBIERTO);
    }

    if (count($turnos) > 0) {
        $this->em->flush();

        foreach ($turnos as $turno) {
            $this->bus->dispatch(new \App\Message\TurnoDescubiertoMessage(
                turnoId:        (string) $turno->getId(),
                pacienteNombre: $paciente->getNombreCompleto(),
                fecha:          $turno->getFecha()?->format('d/m/Y') ?? '—',
                tipoTurno:      $turno->getTipoTurno()->etiqueta(),
            ));
        }
    }

    return count($turnos);
}
```

Verificar que `use App\Message\TurnoDescubiertoMessage;` esté en los imports. Si no existe, agregar.

---

## PUNTO 2 — `TurnoService::verificarDisponibilidad()` genera falso conflicto al editar

**Archivo:** `app/src/Service/TurnoService.php`
**Método:** `verificarDisponibilidad()`

**Problema:** Al editar un turno sin cambiar trabajador/fecha/horario, la consulta `findByTrabajadorYFecha()` devuelve el mismo turno que se está editando. El solapamiento `horaInicio < terminoExistente && horaTermino > inicioExistente` se evalúa como verdadero contra sí mismo, lanzando `DomainException` incorrectamente.

**Implementar:** Agregar parámetro opcional `?Uuid $excludeId = null` a la firma y excluirlo en el loop:

**Cambiar la firma de:**
```php
public function verificarDisponibilidad(
    Trabajador $trabajador,
    \DateTimeInterface $fecha,
    \DateTimeInterface $horaInicio,
    \DateTimeInterface $horaTermino,
): void {
```

**A:**
```php
public function verificarDisponibilidad(
    Trabajador $trabajador,
    \DateTimeInterface $fecha,
    \DateTimeInterface $horaInicio,
    \DateTimeInterface $horaTermino,
    ?\Symfony\Component\Uid\Uuid $excludeId = null,
): void {
```

**En el loop de solapamiento, agregar exclusión al inicio del foreach:**
```php
foreach ($turnosExistentes as $existente) {
    // Excluir el turno que se está editando
    if ($excludeId !== null && $existente->getId()?->equals($excludeId)) {
        continue;
    }

    if ($existente->getEstado() === EstadoTurno::DESCUBIERTO) {
        continue;
    }
    // ... resto del código existente sin cambios
```

**En `actualizarEstadoSegunTrabajador()`, pasar el ID del turno al verificar:**
```php
// Buscar la llamada a verificarDisponibilidad dentro de actualizarEstadoSegunTrabajador y cambiarla a:
$this->verificarDisponibilidad(
    $trabajador,
    $turno->getFecha(),
    $turno->getHoraInicio(),
    $turno->getHoraTermino(),
    $turno->getId(),   // AGREGAR ESTE ARGUMENTO
);
```

Las otras llamadas a `verificarDisponibilidad()` (en `crear()` y `asignarReemplazo()`) no necesitan el parámetro ya que no están editando un turno existente.

---

## PUNTO 3 — `EstadoFactura::VENCIDA` nunca se activa automáticamente

**Problema:** `FacturaRepository::findVencidas()` ya existe y devuelve facturas `EMITIDA` con `fechaVencimiento < hoy`. Sin embargo, ningún proceso las transita automáticamente a `VENCIDA`. El estado existe en el enum pero nunca se aplica.

### 3a. Crear el Message

**Archivo nuevo:** `app/src/Message/MarcarFacturasVencidasMessage.php`

```php
<?php

declare(strict_types=1);

namespace App\Message;

final class MarcarFacturasVencidasMessage
{
    public function __construct(
        public readonly bool $allTenants = true,
    ) {}
}
```

### 3b. Crear el Handler

**Archivo nuevo:** `app/src/MessageHandler/MarcarFacturasVencidasHandler.php`

```php
<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Main\TenantDb;
use App\Enum\EstadoFactura;
use App\Message\MarcarFacturasVencidasMessage;
use App\Repository\FacturaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MarcarFacturasVencidasHandler
{
    public function __construct(
        private readonly FacturaRepository $facturaRepository,
        private readonly EntityManagerInterface $tenantEm,
        private readonly EntityManagerInterface $defaultEm,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(MarcarFacturasVencidasMessage $message): void
    {
        if ($message->allTenants) {
            /** @var TenantDb[] $tenants */
            $tenants = $this->defaultEm->getRepository(TenantDb::class)->findBy(['isActive' => true]);

            foreach ($tenants as $tenant) {
                try {
                    $this->eventDispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
                    $this->tenantEm->clear();
                    $marcadas = $this->procesarTenantActual();

                    $this->logger->info('Facturas marcadas como vencidas.', [
                        'tenant_id' => $tenant->getId(),
                        'marcadas'  => $marcadas,
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Error al marcar facturas vencidas.', [
                        'tenant_id' => $tenant->getId(),
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            return;
        }

        $this->procesarTenantActual();
    }

    private function procesarTenantActual(): int
    {
        $facturas = $this->facturaRepository->findVencidas();

        foreach ($facturas as $factura) {
            $factura->setEstado(EstadoFactura::VENCIDA);
        }

        if (count($facturas) > 0) {
            $this->tenantEm->flush();
        }

        return count($facturas);
    }
}
```

### 3c. Crear el Schedule

**Archivo nuevo:** `app/src/Scheduler/MarcarFacturasVencidasSchedule.php`

```php
<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\MarcarFacturasVencidasMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('facturas_vencidas')]
final class MarcarFacturasVencidasSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            // Ejecutar diariamente a las 06:00
            RecurringMessage::cron('0 6 * * *', new MarcarFacturasVencidasMessage()),
        );
    }
}
```

### 3d. Registrar el scheduler en `services.yaml`

**Archivo:** `app/config/services.yaml`

Agregar junto a los otros schedulers registrados:

```yaml
App\Scheduler\MarcarFacturasVencidasSchedule:
    tags:
        - { name: scheduler.schedule_provider, scheduler: facturas_vencidas }
```

---

## PUNTO 4 — `TurnoService::asignarReemplazo()` no verifica estado del paciente

**Archivo:** `app/src/Service/TurnoService.php`
**Método:** `asignarReemplazo(Turno $turno, Trabajador $reemplazo, MotivoReemplazo $motivo)`

**Problema:** Si el paciente del turno fue suspendido después de la asignación original, se puede asignar un reemplazo igualmente. Ninguna validación verifica el estado del paciente.

**Implementar:** Al inicio del método, antes de cualquier otra validación, agregar:

```php
public function asignarReemplazo(Turno $turno, Trabajador $reemplazo, MotivoReemplazo $motivo): Turno
{
    // AGREGAR ESTO AL INICIO:
    $paciente = $turno->getPaciente();
    if ($paciente !== null && $paciente->getEstado() !== EstadoPaciente::ACTIVO) {
        $razon = $paciente->getEstado() === EstadoPaciente::SUSPENDIDO
            ? 'El paciente está suspendido temporalmente.'
            : 'El paciente está dado de baja.';
        throw new \DomainException(sprintf(
            'No se puede asignar reemplazo: %s (Paciente: %s)',
            $razon,
            $paciente->getNombreCompleto(),
        ));
    }

    // ... resto del método sin cambios
```

Verificar que `use App\Enum\EstadoPaciente;` esté en los imports del archivo. Ya debe existir.

---

## PUNTO 5 — `MandanteService::toggleActivo()` no verifica pacientes activos

**Problema:** Desactivar un mandante no avisa si tiene pacientes `ACTIVO`. Esos pacientes quedan activos pero pertenecen a un mandante inactivo.

### 5a. Inyectar `PacienteRepository` en `MandanteService`

**Archivo:** `app/src/Service/MandanteService.php`

Agregar al constructor:

```php
use App\Repository\PacienteRepository;

// Agregar al constructor:
private readonly PacienteRepository $pacienteRepository,
```

### 5b. Agregar validación en `toggleActivo()`

Reemplazar el método completo por:

```php
public function toggleActivo(Mandante $mandante): Mandante
{
    // Si se va a desactivar, verificar pacientes activos
    if ($mandante->isActivo()) {
        $pacientesActivos = array_filter(
            $this->pacienteRepository->findByMandante($mandante),
            fn(\App\Entity\Tenant\Paciente $p) => $p->getEstado() === \App\Enum\EstadoPaciente::ACTIVO,
        );

        if (count($pacientesActivos) > 0) {
            throw new \DomainException(sprintf(
                'No se puede desactivar el mandante "%s": tiene %d paciente(s) activo(s). ' .
                'Da de baja o suspende a los pacientes antes de desactivar el mandante.',
                $mandante->getNombre(),
                count($pacientesActivos),
            ));
        }
    }

    $mandante->setActivo(!$mandante->isActivo());
    $this->em->flush();

    return $mandante;
}
```

### 5c. Capturar la excepción en `MandanteController`

**Archivo:** `app/src/Controller/MandanteController.php`
**Método:** `toggleActivo()`

Envolver la llamada al servicio para capturar la excepción:

```php
public function toggleActivo(Request $request, Mandante $mandante): Response
{
    if (!$this->isCsrfTokenValid('toggle-' . $mandante->getId(), $request->getPayload()->getString('_token'))) {
        $this->addFlash('error', 'Token CSRF inválido.');
        return $this->redirectToRoute('app_mandante_index');
    }

    try {
        $this->mandanteService->toggleActivo($mandante);
        $this->addFlash('success', 'Estado del mandante actualizado.');
    } catch (\DomainException $e) {
        $this->addFlash('danger', $e->getMessage());
    }

    return $this->redirectToRoute('app_mandante_index');
}
```

---

## PUNTO 6 — `TarifaController` no valida períodos solapados

**Problema:** Al crear o editar una tarifa no se verifica si existe otra tarifa activa para el mismo `tipoConcepto` + `mandante` en el mismo rango de fechas. Dos tarifas activas solapadas para el mismo concepto causan que `findTarifaVigente()` devuelva resultados no deterministas (elige por `vigenciaDesde DESC`, pero si hay dos con la misma fecha, el resultado varía).

### 6a. Agregar método de búsqueda de solapamiento en `TarifaRepository`

**Archivo:** `app/src/Repository/TarifaRepository.php`

Agregar el siguiente método:

```php
/**
 * Busca si ya existe una tarifa activa solapada para el mismo concepto y mandante.
 * Permite excluir una tarifa específica (para edición).
 */
public function findSolapada(
    \App\Enum\TipoConcepto $concepto,
    \DateTimeImmutable $vigenciaDesde,
    ?\DateTimeImmutable $vigenciaHasta,
    ?\App\Entity\Tenant\Mandante $mandante = null,
    ?\Symfony\Component\Uid\Uuid $excludeId = null,
): ?Tarifa {
    $qb = $this->createQueryBuilder('t')
        ->where('t.tipoConcepto = :concepto')
        ->andWhere('t.activa = true')
        ->setParameter('concepto', $concepto);

    // Mismo mandante (o ambas generales)
    if ($mandante !== null) {
        $qb->andWhere('t.mandante = :mandante')->setParameter('mandante', $mandante);
    } else {
        $qb->andWhere('t.mandante IS NULL');
    }

    // Solapamiento de intervalos: [A,B] solapa [C,D] si A <= D && B >= C
    // vigenciaHasta null = vigente indefinidamente (hasta infinito)
    $qb->andWhere(
        '(:desde <= COALESCE(t.vigenciaHasta, :infinito)) AND (COALESCE(:hasta, :infinito) >= t.vigenciaDesde)'
    )
    ->setParameter('desde', $vigenciaDesde->format('Y-m-d'))
    ->setParameter('hasta', $vigenciaHasta?->format('Y-m-d'))
    ->setParameter('infinito', '9999-12-31');

    if ($excludeId !== null) {
        $qb->andWhere('t.id != :excludeId')->setParameter('excludeId', $excludeId);
    }

    return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
}
```

### 6b. Validar en `TarifaController` al crear y editar

**Archivo:** `app/src/Controller/TarifaController.php`
**Métodos:** `new()` y `edit()`

En `new()`, reemplazar el bloque `if ($form->isSubmitted() && $form->isValid())` por:

```php
if ($form->isSubmitted() && $form->isValid()) {
    $solapada = $this->tarifaRepository->findSolapada(
        concepto:      $tarifa->getTipoConcepto(),
        vigenciaDesde: $tarifa->getVigenciaDesde(),
        vigenciaHasta: $tarifa->getVigenciaHasta(),
        mandante:      $tarifa->getMandante(),
    );

    if ($solapada !== null) {
        $this->addFlash('danger', sprintf(
            'Ya existe una tarifa activa para "%s"%s que se solapa con el período indicado (desde %s).',
            $tarifa->getTipoConcepto()->etiqueta(),
            $tarifa->getMandante() !== null ? ' del mandante ' . $tarifa->getMandante()->getNombre() : ' general',
            $solapada->getVigenciaDesde()->format('d/m/Y'),
        ));

        return $this->render('tarifa/new.html.twig', ['form' => $form]);
    }

    $this->em->persist($tarifa);
    $this->em->flush();
    $this->addFlash('success', 'Tarifa creada correctamente.');

    return $this->redirectToRoute('app_tarifa_index');
}
```

En `edit()`, misma validación pero pasando `$tarifa->getId()` como `excludeId`:

```php
if ($form->isSubmitted() && $form->isValid()) {
    $solapada = $this->tarifaRepository->findSolapada(
        concepto:      $tarifa->getTipoConcepto(),
        vigenciaDesde: $tarifa->getVigenciaDesde(),
        vigenciaHasta: $tarifa->getVigenciaHasta(),
        mandante:      $tarifa->getMandante(),
        excludeId:     $tarifa->getId(),
    );

    if ($solapada !== null) {
        $this->addFlash('danger', sprintf(
            'Ya existe otra tarifa activa para "%s"%s que se solapa con el período indicado (desde %s).',
            $tarifa->getTipoConcepto()->etiqueta(),
            $tarifa->getMandante() !== null ? ' del mandante ' . $tarifa->getMandante()->getNombre() : ' general',
            $solapada->getVigenciaDesde()->format('d/m/Y'),
        ));

        return $this->render('tarifa/edit.html.twig', ['form' => $form, 'tarifa' => $tarifa]);
    }

    $this->em->flush();
    $this->addFlash('success', 'Tarifa actualizada correctamente.');

    return $this->redirectToRoute('app_tarifa_index');
}
```

**Nota:** El método DQL usa `COALESCE` que requiere Doctrine DQL estándar. Si genera error con `COALESCE` en DQL, reemplazar la condición por PHP-side post-query: buscar todas las tarifas activas del mismo concepto+mandante y filtrar el solapamiento en un loop.

---

## PUNTO 7 — `PacienteService::actualizarEstado(DADO_DE_BAJA)` no registra bitácora

**Problema:** `darDeBaja()` registra motivo en `BitacoraOperativa`. Pero `actualizarEstado(EstadoPaciente::DADO_DE_BAJA)` también puede dar de baja a un paciente sin registrar entrada en bitácora ni requerir motivo. Dos caminos distintos para la misma acción con consistencia de auditoría diferente.

**Archivo:** `app/src/Service/PacienteService.php`
**Método:** `actualizarEstado(Paciente $paciente, EstadoPaciente $nuevoEstado)`

**Implementar:** Agregar registro en bitácora cuando el nuevo estado es `DADO_DE_BAJA` o `SUSPENDIDO`:

```php
public function actualizarEstado(Paciente $paciente, EstadoPaciente $nuevoEstado): Paciente
{
    $estadoAnterior = $paciente->getEstado();

    if ($estadoAnterior === $nuevoEstado) {
        return $paciente;
    }

    // Al dar de baja, registrar fecha de término
    if ($nuevoEstado === EstadoPaciente::DADO_DE_BAJA && $paciente->getFechaTermino() === null) {
        $paciente->setFechaTermino(new \DateTime());
    }

    $paciente->setEstado($nuevoEstado);

    // AGREGAR: Registrar cambio de estado en bitácora
    $descripcion = match ($nuevoEstado) {
        EstadoPaciente::DADO_DE_BAJA => sprintf(
            'Paciente dado de baja. Estado anterior: %s.',
            $estadoAnterior->etiqueta(),
        ),
        EstadoPaciente::SUSPENDIDO => sprintf(
            'Paciente suspendido. Estado anterior: %s.',
            $estadoAnterior->etiqueta(),
        ),
        EstadoPaciente::ACTIVO => sprintf(
            'Paciente reactivado. Estado anterior: %s.',
            $estadoAnterior->etiqueta(),
        ),
    };

    $entrada = new BitacoraOperativa();
    $entrada->setPaciente($paciente)
            ->setTipo(TipoBitacora::NOVEDAD)
            ->setDescripcion($descripcion);
    // Nota: actualizarEstado no recibe $usuario. Agregar parámetro opcional o
    // dejar creadoPor como null si la entidad lo permite.
    // Si BitacoraOperativa->creadoPor es nullable, persistir sin él.
    // Si NO es nullable, agregar User $usuario = null como parámetro a actualizarEstado().

    $this->em->persist($entrada);
    $this->em->flush();

    if ($nuevoEstado !== EstadoPaciente::ACTIVO) {
        $this->cancelarTurnosFuturos($paciente);
    }

    $this->bus->dispatch(new PacienteEstadoCambioMessage(
        pacienteId:     (string) $paciente->getId(),
        pacienteNombre: $paciente->getNombreCompleto(),
        estadoAnterior: $estadoAnterior->value,
        estadoNuevo:    $nuevoEstado->value,
    ));

    return $paciente;
}
```

**Verificar:** Si `BitacoraOperativa::creadoPor` no es nullable en la entidad/BD, entonces agregar `?User $usuario = null` como tercer parámetro de `actualizarEstado()` y pasarlo a `$entrada->setCreadoPor($usuario)`. También actualizar los lugares del código que llaman a `actualizarEstado()` si se agrega el parámetro.

---

## Resumen de archivos a crear o modificar

| Archivo | Acción | Punto |
|---------|--------|-------|
| `app/src/Service/PacienteService.php` | Modificar | 1, 7 |
| `app/src/Service/TurnoService.php` | Modificar | 2, 4 |
| `app/src/Service/MandanteService.php` | Modificar | 5 |
| `app/src/Controller/MandanteController.php` | Modificar | 5 |
| `app/src/Controller/TarifaController.php` | Modificar | 6 |
| `app/src/Repository/TarifaRepository.php` | Modificar | 6 |
| `app/src/Message/MarcarFacturasVencidasMessage.php` | Crear | 3 |
| `app/src/MessageHandler/MarcarFacturasVencidasHandler.php` | Crear | 3 |
| `app/src/Scheduler/MarcarFacturasVencidasSchedule.php` | Crear | 3 |
| `app/config/services.yaml` | Modificar | 3 |

## Instrucciones finales

1. Crear branch: `git checkout -b fix/logica-negocio-v3 develop`
2. Implementar los 7 puntos en el orden listado
3. Ejecutar `php bin/console cache:clear` después de los cambios
4. Commitear con mensaje descriptivo por grupo lógico:
   - `fix(paciente): desvincular trabajador y notificar al cancelar turnos por baja` (punto 1)
   - `fix(turnos): excluir turno propio en verificación de solapamiento al editar` (punto 2)
   - `feat(facturas): marcar automáticamente facturas vencidas con scheduler diario` (punto 3)
   - `fix(reemplazo): validar estado del paciente al asignar reemplazo` (punto 4)
   - `fix(mandante): bloquear desactivación si tiene pacientes activos` (punto 5)
   - `fix(tarifas): validar períodos solapados al crear y editar tarifas` (punto 6)
   - `fix(paciente): registrar bitácora al cambiar estado vía actualizarEstado()` (punto 7)
