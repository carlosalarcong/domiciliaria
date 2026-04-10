# Fix: Correcciones de lógica de negocio (impacto medio)

## Contexto del proyecto

Sistema de gestión de atención domiciliaria de salud en Chile.
Stack: PHP 8.3 + Symfony 7.4, PostgreSQL, Doctrine ORM.
El código vive en `app/src/`.

Este prompt asume que el branch `fix/logica-negocio-alto-impacto` ya fue mergeado
a `develop`. Crear el branch de trabajo desde `develop`.

```
git checkout develop && git pull
git checkout -b fix/logica-negocio-impacto-medio
```

---

## Problema 1: Editar un turno no recalcula su estado

### Situación actual

`TurnoController::edit()` (`app/src/Controller/TurnoController.php`, línea 100)
solo llama `verificarDisponibilidad()` si hay trabajador, pero nunca actualiza el
campo `estado` del turno. Casos que quedan rotos:

- Se quita el trabajador de un turno `CUBIERTO` → sigue `CUBIERTO` (falsa cobertura).
- Se asigna trabajador a un turno `DESCUBIERTO` → sigue `DESCUBIERTO`.
- Se cambia el trabajador por otro → el estado no se recalcula.

### Cambio requerido

En `TurnoController::edit()`, después de la validación y antes del `flush()`,
agregar la lógica de recálculo de estado. Además, mover esa lógica a un método
nuevo en `TurnoService` para no mezclar lógica de negocio en el controller.

**Nuevo método en `TurnoService`:**

```php
/**
 * Recalcula y aplica el estado correcto del turno según si tiene o no trabajador.
 * También valida estado activo y disponibilidad si se asigna trabajador.
 *
 * @throws \DomainException si el trabajador no está activo o tiene conflicto
 */
public function actualizarEstadoSegunTrabajador(Turno $turno): void
{
    if ($turno->getTrabajador() !== null) {
        $trabajador = $turno->getTrabajador();

        if ($trabajador->getEstado() !== EstadoTrabajador::ACTIVO) {
            throw new \DomainException(sprintf(
                'No se puede asignar el turno: el trabajador %s no está activo (estado: %s).',
                $trabajador->getNombreCompleto(),
                $trabajador->getEstado()->etiqueta(),
            ));
        }

        $this->verificarDisponibilidad(
            $trabajador,
            $turno->getFecha(),
            $turno->getHoraInicio(),
            $turno->getHoraTermino(),
        );

        // Solo cambiar a CUBIERTO si estaba DESCUBIERTO (no sobreescribir COMPLETADO/PARCIAL)
        if ($turno->getEstado() === EstadoTurno::DESCUBIERTO) {
            $turno->setEstado(EstadoTurno::CUBIERTO);
        }
    } else {
        // Sin trabajador solo puede estar DESCUBIERTO
        if (in_array($turno->getEstado(), [EstadoTurno::CUBIERTO, EstadoTurno::PARCIAL], true)) {
            $turno->setEstado(EstadoTurno::DESCUBIERTO);
        }
    }
}
```

**`TurnoController::edit()` actualizado** — reemplazar el bloque del if/flush por:

```php
if ($form->isSubmitted() && $form->isValid()) {
    try {
        $this->turnoService->actualizarEstadoSegunTrabajador($turno);
        $this->em->flush();
        $this->addFlash('success', 'Turno actualizado correctamente.');
        return $this->redirectToRoute('app_turno_show', ['id' => $turno->getId()]);
    } catch (\DomainException $e) {
        $this->addFlash('danger', $e->getMessage());
    }
}
```

Archivos a modificar:
- `app/src/Service/TurnoService.php`
- `app/src/Controller/TurnoController.php`

---

## Problema 2: Transiciones de estado en finanzas sin guardrails

### Situación actual

`app/src/Service/FinanzasService.php` — ninguno de estos métodos valida el estado
previo antes de hacer la transición:

- `aprobarLiquidacion()` (línea 123): puede aprobar una liquidación `PAGADA` o `ANULADA`.
- `marcarPagadaLiquidacion()` (línea 131): puede pagar una `BORRADOR` (saltándose la aprobación).
- `emitirFactura()` (línea 221): puede emitir una factura `PAGADA` o `ANULADA`.
- `marcarPagadaFactura()` (línea 236): puede pagar una factura `BORRADOR`.

### Cambio requerido

Agregar guards al inicio de cada método. Usar `\LogicException` (igual que el
resto del sistema usa para violaciones de flujo de estado).

**`aprobarLiquidacion()`:**

```php
public function aprobarLiquidacion(LiquidacionMensual $liquidacion): LiquidacionMensual
{
    if ($liquidacion->getEstado() !== EstadoLiquidacion::BORRADOR) {
        throw new \LogicException(sprintf(
            'Solo se puede aprobar una liquidación en estado Borrador (estado actual: %s).',
            $liquidacion->getEstado()->etiqueta(),
        ));
    }

    $liquidacion->setEstado(EstadoLiquidacion::APROBADA);
    $this->em->flush();

    return $liquidacion;
}
```

**`marcarPagadaLiquidacion()`:**

```php
public function marcarPagadaLiquidacion(LiquidacionMensual $liquidacion, \DateTimeInterface $fechaPago): LiquidacionMensual
{
    if ($liquidacion->getEstado() !== EstadoLiquidacion::APROBADA) {
        throw new \LogicException(sprintf(
            'Solo se puede marcar como pagada una liquidación aprobada (estado actual: %s).',
            $liquidacion->getEstado()->etiqueta(),
        ));
    }

    $liquidacion->setEstado(EstadoLiquidacion::PAGADA)->setFechaPago($fechaPago);
    $this->em->flush();

    return $liquidacion;
}
```

**`emitirFactura()`:**

```php
public function emitirFactura(Factura $factura, \DateTimeInterface $fechaEmision, ?int $diasVencimiento = null): Factura
{
    if ($factura->getEstado() !== EstadoFactura::BORRADOR) {
        throw new \LogicException(sprintf(
            'Solo se puede emitir una factura en estado Borrador (estado actual: %s).',
            $factura->getEstado()->etiqueta(),
        ));
    }

    // ... resto igual
}
```

**`marcarPagadaFactura()`:**

```php
public function marcarPagadaFactura(Factura $factura, \DateTimeInterface $fechaPago): Factura
{
    if ($factura->getEstado() !== EstadoFactura::EMITIDA) {
        throw new \LogicException(sprintf(
            'Solo se puede marcar como pagada una factura emitida (estado actual: %s).',
            $factura->getEstado()->etiqueta(),
        ));
    }

    $factura->setEstado(EstadoFactura::PAGADA)->setFechaPago($fechaPago);
    $this->em->flush();

    return $factura;
}
```

En los controllers `FinanzasController` (`app/src/Controller/FinanzasController.php`),
capturar `\LogicException` en los endpoints `aprobar`, `pagar` (liquidación) y
`emitir`, `pagar` (factura), y mostrarla como flash `danger`, igual que se hace
con `\DomainException` en `TurnoController`.

Archivos a modificar:
- `app/src/Service/FinanzasService.php`
- `app/src/Controller/FinanzasController.php`

---

## Problema 3: Reemplazo posible sobre turno ya COMPLETADO o PARCIAL

### Situación actual

`TurnoService::asignarReemplazo()` (`app/src/Service/TurnoService.php`, línea 120)
no valida el estado del turno. Se puede hacer un reemplazo sobre un turno que ya
terminó o que está en curso, lo que no tiene sentido operativo.

### Cambio requerido

Al inicio de `asignarReemplazo()`, antes de cualquier validación del trabajador,
agregar:

```php
if (!in_array($turno->getEstado(), [EstadoTurno::DESCUBIERTO, EstadoTurno::CUBIERTO], true)) {
    throw new \DomainException(sprintf(
        'No se puede asignar reemplazo a un turno en estado "%s". Solo se permite en turnos Descubiertos o Cubiertos.',
        $turno->getEstado()->etiqueta(),
    ));
}
```

Archivo a modificar: `app/src/Service/TurnoService.php`

---

## Problema 4: Turno PARCIAL queda en el limbo sin proceso de resolución

### Situación actual

Un turno pasa a `PARCIAL` cuando se registra el inicio pero no el término
(`TurnoService::registrarAsistencia()`). No existe ningún proceso que:
- Alerte al coordinador sobre turnos `PARCIAL` que llevan horas sin cerrarse.
- Permita al coordinador forzar el cierre de un turno `PARCIAL`.

### Cambio requerido

**Paso A — Nuevo método en `TurnoRepository`:**

```php
/**
 * Turnos PARCIAL cuyo inicio fue hace más de N horas y aún no tienen término.
 * @return Turno[]
 */
public function findParcialesVencidos(int $horas = 26): array
{
    $limite = new \DateTime("-{$horas} hours");

    return $this->createQueryBuilder('t')
        ->where('t.estado = :estado')
        ->andWhere('t.registroInicio <= :limite')
        ->setParameter('estado', EstadoTurno::PARCIAL)
        ->setParameter('limite', $limite)
        ->getQuery()
        ->getResult();
}
```

El umbral de 26 horas cubre el turno de 24h más un margen de 2 horas.

**Paso B — Nuevo método en `TurnoService` para cierre forzado:**

```php
/**
 * Fuerza el cierre de un turno PARCIAL usando la hora planificada de término
 * como hora de término real. Usado por coordinadores cuando el trabajador
 * olvidó marcar el término.
 */
public function forzarCierreParcial(Turno $turno, User $autor): Turno
{
    if ($turno->getEstado() !== EstadoTurno::PARCIAL) {
        throw new \LogicException('Solo se puede forzar el cierre de un turno en estado Parcial.');
    }

    // Usar hora planificada de término como fallback
    $termino = $turno->getHoraTermino();
    $turno->setRegistroTermino($termino)
          ->setEstado(EstadoTurno::COMPLETADO)
          ->setObservaciones(
              trim(($turno->getObservaciones() ?? '') . ' [Cierre forzado por ' . $autor->getNombreCompleto() . ' el ' . (new \DateTime())->format('d/m/Y H:i') . ']')
          );

    $this->em->flush();

    return $turno;
}
```

**Paso C — Nuevo endpoint en `TurnoController`:**

```php
#[Route('/{id}/forzar-cierre', name: 'forzar_cierre', methods: ['POST'])]
public function forzarCierre(Request $request, Turno $turno): Response
{
    if (!$this->isCsrfTokenValid('forzar_cierre_' . $turno->getId(), $request->request->get('_token'))) {
        throw $this->createAccessDeniedException();
    }

    try {
        $this->turnoService->forzarCierreParcial($turno, $this->getUser());
        $this->addFlash('warning', 'Turno cerrado forzosamente usando hora planificada de término.');
    } catch (\LogicException $e) {
        $this->addFlash('danger', $e->getMessage());
    }

    return $this->redirectToRoute('app_turno_show', ['id' => $turno->getId()]);
}
```

Archivos a modificar:
- `app/src/Repository/TurnoRepository.php`
- `app/src/Service/TurnoService.php`
- `app/src/Controller/TurnoController.php`

---

## Problema 5: Seguimientos agregables a eventos adversos CERRADOS

### Situación actual

`EventoAdversoService::agregarSeguimiento()` (`app/src/Service/EventoAdversoService.php`,
línea 71) no valida el estado del evento. Un evento `CERRADO` debería ser inmutable.

### Cambio requerido

Agregar al inicio de `agregarSeguimiento()`:

```php
if ($evento->getEstado() === EstadoEvento::CERRADO) {
    throw new \LogicException('No se puede agregar seguimiento a un evento cerrado.');
}
```

Archivo a modificar: `app/src/Service/EventoAdversoService.php`

---

## Problema 6: Evento GRAVE o CRÍTICO se puede cerrar sin responsable asignado

### Situación actual

`EventoAdversoService::cerrar()` (línea 84) solo valida que el evento esté en
`EN_PROCESO`, pero no exige responsable en eventos graves. Un evento
`GRAVE` o `CRITICO` debería tener un responsable formal antes de cerrarse.

`GravedadEvento::requiereNotificacion()` (`app/src/Enum/GravedadEvento.php`,
línea 34) ya distingue `GRAVE`/`CRITICO` de `LEVE`/`MODERADO` — reutilizar esa
lógica.

### Cambio requerido

En `EventoAdversoService::cerrar()`, después de la validación de estado existente,
agregar:

```php
if ($evento->getGravedad()->requiereNotificacion() && $evento->getResponsable() === null) {
    throw new \LogicException(
        'Los eventos de gravedad Grave o Crítico deben tener un responsable asignado antes de cerrarse.'
    );
}
```

Archivo a modificar: `app/src/Service/EventoAdversoService.php`

---

## Problema 7: Doble alerta por turno descubierto

### Situación actual

Cuando se crea un turno sin trabajador, `TurnoService::crear()` despacha
`TurnoDescubiertoMessage` inmediatamente. El scheduler diario
(`TurnosDescubiertosSchedule`, cron `0 20 * * *`) vuelve a alertar por **todos**
los turnos descubiertos próximos. Un turno creado hoy como descubierto genera
alerta inmediata y luego una alerta diaria repetida hasta que se cubra.

### Cambio requerido

El scheduler debe excluir los turnos descubiertos que ya fueron alertados
recientemente. La solución más simple es agregar un campo `ultimaAlertaEn` al
turno para traquear cuándo se envió la última alerta.

**Paso A — Agregar campo a `Turno`:**

En `app/src/Entity/Tenant/Turno.php`, agregar:

```php
#[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $ultimaAlertaDescubiertoEn = null;

public function getUltimaAlertaDescubiertoEn(): ?\DateTimeImmutable
{
    return $this->ultimaAlertaDescubiertoEn;
}

public function setUltimaAlertaDescubiertoEn(?\DateTimeImmutable $dt): static
{
    $this->ultimaAlertaDescubiertoEn = $dt;
    return $this;
}
```

Crear la migración correspondiente:
```
php bin/console make:migration
```

**Paso B — Actualizar `TurnoDescubiertoMessage` para incluir el ID:**

El message ya tiene `turnoId`. Solo necesitamos que el handler actualice el campo.

**Paso C — Actualizar `TurnoDescubiertoHandler`:**

En `app/src/MessageHandler/TurnoDescubiertoHandler.php`, después de enviar las
notificaciones, actualizar el turno:

```php
// Al final de __invoke(), antes de retornar
$turno = $this->turnoRepository->find($message->turnoId);
if ($turno !== null) {
    $turno->setUltimaAlertaDescubiertoEn(new \DateTimeImmutable());
    $this->em->flush();
}
```

Inyectar `TurnoRepository` y `EntityManagerInterface` en el handler.

**Paso D — Filtrar en `RevisarTurnosDescubiertosHandler`:**

En `app/src/MessageHandler/RevisarTurnosDescubiertosHandler.php`, agregar filtro
para no re-alertar turnos alertados en las últimas 20 horas:

```php
foreach ($turnos as $turno) {
    $ultimaAlerta = $turno->getUltimaAlertaDescubiertoEn();
    if ($ultimaAlerta !== null) {
        $horasDesdeUltimaAlerta = (new \DateTimeImmutable())->diff($ultimaAlerta)->h
            + ((new \DateTimeImmutable())->diff($ultimaAlerta)->days * 24);
        if ($horasDesdeUltimaAlerta < 20) {
            continue; // Ya fue alertado recientemente
        }
    }

    $this->bus->dispatch(new TurnoDescubiertoMessage(...));
}
```

Archivos a modificar:
- `app/src/Entity/Tenant/Turno.php`
- `app/src/MessageHandler/TurnoDescubiertoHandler.php`
- `app/src/MessageHandler/RevisarTurnosDescubiertosHandler.php`
- Nueva migración Doctrine

---

## Instrucciones generales

- No cambiar tests existentes ni crear tests nuevos (no hay suite de tests activa).
- No agregar comentarios innecesarios; solo donde la lógica no sea evidente.
- No refactorizar código que no esté relacionado con los 7 problemas anteriores.
- Respetar el estilo de código existente: `declare(strict_types=1)`, `readonly`
  en constructores, mensajes de excepción en español.
- Un commit por problema resuelto, con mensaje descriptivo en español siguiendo
  la convención `fix(módulo): descripción`.
- El branch de trabajo debe llamarse `fix/logica-negocio-impacto-medio` creado
  desde `develop`.
