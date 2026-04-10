# Fix: Incongruencias de lógica de negocio (menor impacto)

## Contexto del proyecto

Sistema de gestión de atención domiciliaria de salud en Chile.
Stack: PHP 8.3 + Symfony 7.4, PostgreSQL, Doctrine ORM.
El código vive en `app/src/`.

Este prompt asume que los branches `fix/logica-negocio-alto-impacto` y
`fix/logica-negocio-impacto-medio` ya fueron mergeados a `develop`.
Crear el branch de trabajo desde `develop`:

```
git checkout develop && git pull
git checkout -b fix/logica-negocio-incongruencias
```

**Nota:** Los ítems que en el análisis original figuraban como "menor impacto
pero incongruente" (seguimientos en eventos cerrados, doble alerta de turnos
descubiertos, responsable obligatorio en eventos graves) ya fueron corregidos
dentro del branch de impacto medio. Este prompt aborda 4 incongruencias
adicionales que quedaron pendientes.

---

## Problema 1: `registrarAsistencia()` no valida el estado del turno

### Situación actual

`TurnoService::registrarAsistencia()` (`app/src/Service/TurnoService.php`)
no verifica el estado del turno antes de operar. Casos incongruentes posibles:

- Registrar **inicio** en un turno `COMPLETADO` → sobreescribe `registroInicio`
  y lo devuelve a `PARCIAL`, deshaciendo el cierre y afectando la liquidación.
- Registrar **inicio** en un turno `DESCUBIERTO` (sin trabajador asignado) →
  queda `PARCIAL` sin que haya nadie formalmente asignado.
- Registrar **término** en un turno ya `COMPLETADO` → sobreescribe `registroTermino`
  con un nuevo timestamp, alterando silenciosamente las horas ya liquidadas.

El método actual:

```php
// app/src/Service/TurnoService.php
public function registrarAsistencia(Turno $turno, string $tipo): Turno
{
    if ($tipo === 'inicio') {
        $turno->setRegistroInicio(new \DateTime());
        $turno->setEstado(EstadoTurno::PARCIAL);
    } elseif ($tipo === 'termino') {
        if ($turno->getRegistroInicio() === null) {
            throw new \LogicException('No se puede registrar el término sin haber registrado el inicio.');
        }
        $turno->setRegistroTermino(new \DateTime());
        $turno->setEstado(EstadoTurno::COMPLETADO);
    }

    $this->em->flush();
    return $turno;
}
```

### Cambio requerido

Agregar guards de estado al inicio del método:

```php
public function registrarAsistencia(Turno $turno, string $tipo): Turno
{
    if ($tipo === 'inicio') {
        if ($turno->getEstado() !== EstadoTurno::CUBIERTO) {
            throw new \LogicException(sprintf(
                'Solo se puede registrar el inicio en un turno Cubierto (estado actual: %s).',
                $turno->getEstado()->etiqueta(),
            ));
        }
        $turno->setRegistroInicio(new \DateTime());
        $turno->setEstado(EstadoTurno::PARCIAL);

    } elseif ($tipo === 'termino') {
        if ($turno->getEstado() !== EstadoTurno::PARCIAL) {
            throw new \LogicException(sprintf(
                'Solo se puede registrar el término en un turno Parcial (estado actual: %s).',
                $turno->getEstado()->etiqueta(),
            ));
        }
        if ($turno->getRegistroInicio() === null) {
            throw new \LogicException('No se puede registrar el término sin haber registrado el inicio.');
        }
        $turno->setRegistroTermino(new \DateTime());
        $turno->setEstado(EstadoTurno::COMPLETADO);
    }

    $this->em->flush();
    return $turno;
}
```

Archivo a modificar: `app/src/Service/TurnoService.php`

---

## Problema 2: Turno creado para paciente inactivo o dado de baja

### Situación actual

`TurnoService::crear()` valida el estado del trabajador (fix anterior) pero
**no valida el estado del paciente**. Se puede crear un turno para un paciente
`SUSPENDIDO` o `DADO_DE_BAJA`, lo que no tiene sentido operativo.

`EstadoPaciente` (`app/src/Enum/EstadoPaciente.php`) tiene los casos:
- `ACTIVO`
- `SUSPENDIDO`
- `DADO_DE_BAJA`

### Cambio requerido

En `TurnoService::crear()`, justo después de asignar `creadoPor` y antes de
evaluar si tiene trabajador, agregar:

```php
$paciente = $turno->getPaciente();
if ($paciente !== null && $paciente->getEstado() !== EstadoPaciente::ACTIVO) {
    throw new \DomainException(sprintf(
        'No se puede crear el turno: el paciente %s no está activo (estado: %s).',
        $paciente->getNombreCompleto(),
        $paciente->getEstado()->etiqueta(),
    ));
}
```

También aplicar la misma validación en `TurnoService::actualizarEstadoSegunTrabajador()`
para que la protección cubra también la edición:

```php
// Al inicio del método, antes de evaluar el trabajador
$paciente = $turno->getPaciente();
if ($paciente !== null && $paciente->getEstado() !== EstadoPaciente::ACTIVO) {
    throw new \DomainException(sprintf(
        'No se puede modificar el turno: el paciente %s no está activo (estado: %s).',
        $paciente->getNombreCompleto(),
        $paciente->getEstado()->etiqueta(),
    ));
}
```

Agregar el import necesario:
```php
use App\Enum\EstadoPaciente;
```

Archivo a modificar: `app/src/Service/TurnoService.php`

---

## Problema 3: Dar de baja a un paciente no limpia sus turnos futuros

### Situación actual

`PacienteService::darDeBaja()` (`app/src/Service/PacienteService.php`, línea 79)
registra la baja, guarda la `fechaTermino` y despacha un mensaje, pero **no
toca los turnos futuros del paciente**. Esos turnos quedan asignados a
trabajadores que se presentarán en un domicilio donde el paciente ya no vive
o ya no requiere el servicio.

El patrón simétrico fue resuelto para trabajadores en
`TrabajadorService::descubrirTurnosFuturos()` — aquí hay que hacer lo mismo
desde el lado del paciente.

### Cambio requerido

**Paso A — Nuevo método en `TurnoRepository`:**

```php
/**
 * Retorna turnos futuros con trabajador asignado (CUBIERTO o PARCIAL)
 * que pertenecen a un paciente dado.
 *
 * @return Turno[]
 */
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

Agregar el import de `Paciente` si no existe en el repositorio:
```php
use App\Entity\Tenant\Paciente;
```

**Paso B — Nuevo método en `PacienteService`:**

Inyectar `TurnoRepository` en el constructor de `PacienteService` y agregar:

```php
public function cancelarTurnosFuturos(Paciente $paciente): int
{
    $turnos = $this->turnoRepository->findFuturosAsignadosDePaciente($paciente);

    foreach ($turnos as $turno) {
        $turno->setEstado(EstadoTurno::DESCUBIERTO);
    }

    if (count($turnos) > 0) {
        $this->em->flush();
    }

    return count($turnos);
}
```

Agregar el import:
```php
use App\Enum\EstadoTurno;
use App\Repository\TurnoRepository;
```

A diferencia del caso del trabajador, aquí no se elimina el `trabajador` del
turno — el trabajador sigue vinculado pero el turno queda `DESCUBIERTO` para
que el coordinador decida cómo gestionarlo (reagendar, cancelar, etc.).

**Paso C — Llamar desde `PacienteService::darDeBaja()`:**

Al final de `darDeBaja()`, después del `flush()` y antes del dispatch:

```php
$turnosCancelados = $this->cancelarTurnosFuturos($paciente);
```

Y también al final de `actualizarEstado()`, cuando el nuevo estado no es `ACTIVO`:

```php
if ($nuevoEstado !== EstadoPaciente::ACTIVO) {
    $this->cancelarTurnosFuturos($paciente);
}
```

**Paso D — Flash informativo en `PacienteController`:**

En `PacienteController`, en el endpoint que llama `darDeBaja()` o
`actualizarEstado()`, capturar el retorno y mostrar aviso si hay turnos
cancelados. El patrón a seguir es el mismo que en `TrabajadorController`
(buscar el bloque `$descubiertos = $this->trabajadorService->descubrirTurnosFuturos(...)`).

Archivos a modificar:
- `app/src/Repository/TurnoRepository.php`
- `app/src/Service/PacienteService.php`
- `app/src/Controller/PacienteController.php`

---

## Problema 4: `findParcialesVencidos()` existe pero nadie la llama

### Situación actual

En el branch anterior se añadió `TurnoRepository::findParcialesVencidos(int $horas = 26)`
(`app/src/Repository/TurnoRepository.php`) pero es un método huérfano: ningún
scheduler, handler ni controller lo invoca. Un turno puede quedar en estado
`PARCIAL` indefinidamente sin que el sistema haga nada al respecto.

### Cambio requerido

Conectar el método al scheduler diario existente para que revise los parciales
vencidos y alerte al coordinador.

**Paso A — Nuevo Message:**

Crear `app/src/Message/TurnoParcialVencidoMessage.php`:

```php
<?php

declare(strict_types=1);

namespace App\Message;

final class TurnoParcialVencidoMessage
{
    public function __construct(
        public readonly string $turnoId,
        public readonly string $pacienteNombre,
        public readonly string $trabajadorNombre,
        public readonly string $fecha,
        public readonly string $tipoTurno,
    ) {}
}
```

**Paso B — Nuevo MessageHandler:**

Crear `app/src/MessageHandler/TurnoParcialVencidoHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TurnoParcialVencidoMessage;
use App\Repository\UserRepository;
use App\Service\NotificacionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class TurnoParcialVencidoHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NotificacionService $notificacionService,
        private readonly LoggerInterface $logger,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(TurnoParcialVencidoMessage $message): void
    {
        $this->logger->warning('Turno parcial vencido detectado', [
            'turno_id'   => $message->turnoId,
            'paciente'   => $message->pacienteNombre,
            'trabajador' => $message->trabajadorNombre,
            'fecha'      => $message->fecha,
        ]);

        $url = $this->urlGenerator->generate(
            'app_turno_show',
            ['id' => $message->turnoId],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        $destinatarios = $this->userRepository->findActivosByRoles(['ROLE_ADMIN', 'ROLE_COORDINADOR']);

        $this->notificacionService->crearParaVarios(
            $destinatarios,
            'turno_parcial_vencido',
            "Turno parcial vencido — {$message->pacienteNombre}",
            "{$message->fecha} · {$message->tipoTurno} · Trabajador: {$message->trabajadorNombre}",
            $url,
        );
    }
}
```

**Paso C — Integrar en el scheduler diario:**

En `RevisarTurnosDescubiertosHandler` (`app/src/MessageHandler/RevisarTurnosDescubiertosHandler.php`),
inyectar `TurnoRepository` y agregar al final de `__invoke()`, después del loop
de descubiertos:

```php
// Revisar parciales vencidos (+26h sin término registrado)
$parcialesVencidos = $this->turnoRepository->findParcialesVencidos(26);

foreach ($parcialesVencidos as $turno) {
    $this->bus->dispatch(new TurnoParcialVencidoMessage(
        turnoId:         (string) $turno->getId(),
        pacienteNombre:  $turno->getPaciente()?->getNombreCompleto() ?? 'Desconocido',
        trabajadorNombre: $turno->getTrabajador()?->getNombreCompleto() ?? 'Sin asignar',
        fecha:           $turno->getFecha()?->format('d/m/Y') ?? '—',
        tipoTurno:       $turno->getTipoTurno()->etiqueta(),
    ));
}
```

Agregar el import:
```php
use App\Message\TurnoParcialVencidoMessage;
```

Archivos a crear:
- `app/src/Message/TurnoParcialVencidoMessage.php`
- `app/src/MessageHandler/TurnoParcialVencidoHandler.php`

Archivos a modificar:
- `app/src/MessageHandler/RevisarTurnosDescubiertosHandler.php`

---

## Instrucciones generales

- No cambiar tests existentes ni crear tests nuevos.
- No agregar comentarios innecesarios; solo donde la lógica no sea evidente.
- No refactorizar código que no esté relacionado con los 4 problemas anteriores.
- Respetar el estilo de código existente: `declare(strict_types=1)`, `readonly`
  en constructores, mensajes de excepción en español.
- Un commit por problema resuelto, con mensaje descriptivo en español siguiendo
  la convención `fix(módulo): descripción`.
- El branch de trabajo debe llamarse `fix/logica-negocio-incongruencias` creado
  desde `develop`.
