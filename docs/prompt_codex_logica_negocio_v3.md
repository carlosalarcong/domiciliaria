# Prompt Codex — Correcciones de lógica de negocio v3 (7 puntos)

## Contexto del sistema

Sistema SaaS multi-tenant de gestión de atención domiciliaria de salud, desarrollado en **Symfony 7.4 / PHP 8.3**.

### Entidades principales
- **Turno** — estados: `CUBIERTO | PARCIAL | DESCUBIERTO | COMPLETADO` (`App\Enum\EstadoTurno`)
- **Trabajador** — estados: `ACTIVO | INACTIVO | SUSPENDIDO` (`App\Enum\EstadoTrabajador`)
- **LiquidacionMensual** — estados: `BORRADOR | APROBADA | PAGADA | ANULADA` (`App\Enum\EstadoLiquidacion`)
- **Factura** — estados: `BORRADOR | EMITIDA | PAGADA | VENCIDA | ANULADA` (`App\Enum\EstadoFactura`)

### Servicios clave
- `App\Service\TurnoService` — `app/src/Service/TurnoService.php`
- `App\Service\FinanzasService` — `app/src/Service/FinanzasService.php`

### Controladores relevantes
- `App\Controller\TrabajadorController` — `app/src/Controller/TrabajadorController.php`
- `App\Controller\FinanzasController` — `app/src/Controller/FinanzasController.php`

### Formularios relevantes
- `App\Form\TurnoType` — `app/src/Form/TurnoType.php`
- `App\Form\TarifaType` — `app/src/Form/TarifaType.php`

### Entidades relevantes
- `App\Entity\Tenant\Turno` — `app/src/Entity/Tenant/Turno.php`
- `App\Entity\Tenant\Tarifa` — `app/src/Entity/Tenant/Tarifa.php`
- `App\Entity\Tenant\Factura` — `app/src/Entity/Tenant/Factura.php`

### Templates relevantes
- `app/templates/finanzas/factura_show.html.twig`
- `app/templates/trabajador/show.html.twig` (o donde esté el botón de toggle-estado)

---

## Trabajo a realizar

Crea un branch `fix/logica-negocio-v4` desde `develop` e implementa los 7 puntos siguientes.

---

## PUNTO 1 — `generarLiquidacion()` bloquea cuando ya existe una ANULADA

**Archivo:** `app/src/Service/FinanzasService.php`
**Método:** `generarLiquidacion(Trabajador $trabajador, int $anio, int $mes, User $creadoPor)`

**Problema:** Cuando una liquidación está ANULADA y se intenta generar una nueva para el mismo período, el sistema encuentra la existente y lanza `LogicException` con mensaje contradictorio ("Anula la liquidación actual antes de crear una nueva" — ya está anulada). El flujo queda atascado: no hay forma de crear una liquidación correctora tras una anulación.

**Implementar:** En el bloque `else` del método, ampliar la condición para permitir regenerar también cuando el estado es `ANULADA`. En ese caso, resetear el estado a `BORRADOR` y limpiar el `motivoAnulacion`:

Reemplazar:
```php
} else {
    if (!in_array($liquidacion->getEstado(), [EstadoLiquidacion::BORRADOR], true)) {
        throw new \LogicException(sprintf(
            'Solo se puede regenerar una liquidación en estado Borrador (estado actual: %s). ' .
            'Anula la liquidación actual antes de crear una nueva.',
            $liquidacion->getEstado()->etiqueta(),
        ));
    }

    $nota = sprintf(
        '[Regenerada por sistema el %s - items anteriores eliminados]',
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

Por:
```php
} else {
    if (!in_array($liquidacion->getEstado(), [EstadoLiquidacion::BORRADOR, EstadoLiquidacion::ANULADA], true)) {
        throw new \LogicException(sprintf(
            'Solo se puede regenerar una liquidación en estado Borrador o Anulada (estado actual: %s).',
            $liquidacion->getEstado()->etiqueta(),
        ));
    }

    // Si estaba ANULADA, volver a BORRADOR y limpiar el motivo de anulación
    if ($liquidacion->getEstado() === EstadoLiquidacion::ANULADA) {
        $liquidacion->setEstado(EstadoLiquidacion::BORRADOR)
                    ->setMotivoAnulacion(null);
    }

    $nota = sprintf(
        '[Regenerada por sistema el %s — ítems anteriores eliminados]',
        (new \DateTime())->format('d/m/Y H:i'),
    );
    $liquidacion->setCreadoPor($creadoPor)
                ->setObservaciones(
                    trim(($liquidacion->getObservaciones() ?? '') . ' ' . $nota)
                );

    // Limpiar items anteriores
    foreach ($liquidacion->getItems() as $item) {
        $this->em->remove($item);
    }
}
```

---

## PUNTO 2 — `EstadoFactura::ANULADA` existe pero no hay `anularFactura()`

**Problema:** `EstadoFactura::ANULADA` existe en el enum, aparece en reportes y filtros, pero no existe ningún método para anularlo ni ruta en el controller. Las facturas con error no pueden anularse.

### 2a. Agregar método `anularFactura()` en `FinanzasService`

**Archivo:** `app/src/Service/FinanzasService.php`

Agregar después de `marcarPagadaFactura()`:

```php
public function anularFactura(Factura $factura, string $motivo, User $anuladoPor): Factura
{
    if ($factura->getEstado() === EstadoFactura::ANULADA) {
        throw new \LogicException('La factura ya está anulada.');
    }

    if ($factura->getEstado() === EstadoFactura::PAGADA) {
        throw new \LogicException(
            'No se puede anular una factura ya pagada. Contacta al administrador para realizar un ajuste manual.'
        );
    }

    $registro = sprintf(
        '[Anulada por %s el %s. Motivo: %s]',
        $anuladoPor->getNombreCompleto(),
        (new \DateTime())->format('d/m/Y H:i'),
        $motivo,
    );

    $factura->setEstado(EstadoFactura::ANULADA)
            ->setObservaciones(trim(($factura->getObservaciones() ?? '') . ' ' . $registro));

    $this->em->flush();

    return $factura;
}
```

### 2b. Agregar ruta `facturaAnular` en `FinanzasController`

**Archivo:** `app/src/Controller/FinanzasController.php`

Agregar después de la acción `facturaPagar()`:

```php
#[Route('/facturas/{id}/anular', name: 'factura_anular', methods: ['POST'])]
#[IsGranted('FINANZAS_EDITAR')]
public function facturaAnular(Request $request, Factura $factura): Response
{
    if (!$this->isCsrfTokenValid('fac_' . $factura->getId(), $request->request->get('_token'))) {
        throw $this->createAccessDeniedException();
    }

    $motivo = trim($request->request->get('motivo', ''));
    if ($motivo === '') {
        $this->addFlash('danger', 'Debe ingresar un motivo para anular la factura.');
        return $this->redirectToRoute('app_finanzas_factura_show', ['id' => $factura->getId()]);
    }

    try {
        $this->finanzasService->anularFactura($factura, $motivo, $this->getUser());
        $this->addFlash('warning', 'Factura anulada correctamente.');
    } catch (\LogicException $e) {
        $this->addFlash('danger', $e->getMessage());
    }

    return $this->redirectToRoute('app_finanzas_factura_show', ['id' => $factura->getId()]);
}
```

### 2c. Agregar botón de anulación en la vista

**Archivo:** `app/templates/finanzas/factura_show.html.twig`

Dentro del bloque de acciones (`{% if is_granted('FINANZAS_EDITAR') %}`), agregar el formulario de anulación junto a los otros botones. Debe mostrarse para cualquier estado que no sea `ANULADA` ni `PAGADA`:

```twig
{% if factura.estado.value != 'ANULADA' and factura.estado.value != 'PAGADA' %}
<form method="post" action="{{ path('app_finanzas_factura_anular', {id: factura.id}) }}"
      onsubmit="return confirm('¿Seguro que deseas anular esta factura? Esta acción no se puede revertir.')">
    <input type="hidden" name="_token" value="{{ csrf_token('fac_' ~ factura.id) }}">
    <div class="mb-2">
        <input type="text" name="motivo" class="form-control form-control-sm"
               placeholder="Motivo de anulación (obligatorio)" required>
    </div>
    <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-x-circle me-1"></i> Anular factura
    </button>
</form>
{% endif %}

{% if factura.estado.value == 'ANULADA' %}
<div class="alert alert-danger mt-3">
    <i class="bi bi-x-circle me-2"></i>
    <strong>Factura anulada.</strong>
    {% if factura.observaciones %}
        <span class="text-muted small ms-2">{{ factura.observaciones }}</span>
    {% endif %}
</div>
{% endif %}
```

---

## PUNTO 3 — `toggleEstado` del trabajador: SUSPENDIDO inaccesible desde la UI

**Problema:** `EstadoTrabajador::SUSPENDIDO` existe en el enum (con badge y lógica implementada) pero `toggleEstado()` solo alterna `ACTIVO ↔ INACTIVO`. No hay forma de suspender temporalmente a un trabajador. El estado es un callejón sin salida desde la lógica actual.

**Implementar:** Cambiar `toggleEstado()` a un enfoque de tres estados con ruta dedicada para suspensión.

### 3a. Cambiar la lógica de `toggleEstado` en `TrabajadorController`

**Archivo:** `app/src/Controller/TrabajadorController.php`
**Método:** `toggleEstado()`

Reemplazar el cálculo de `$nuevoEstado` para que acepte el estado deseado desde el request:

```php
#[Route('/{id}/toggle-estado', name: 'toggle_estado', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
public function toggleEstado(Request $request, Trabajador $trabajador): Response
{
    if (!$this->isCsrfTokenValid('toggle_estado_' . $trabajador->getId(), $request->request->get('_token'))) {
        throw $this->createAccessDeniedException();
    }

    // Permitir especificar el estado destino vía formulario; si no se especifica,
    // alternar entre ACTIVO e INACTIVO como antes.
    $estadoParam = $request->request->get('nuevo_estado');
    $nuevoEstado = EstadoTrabajador::tryFrom($estadoParam ?? '')
        ?? ($trabajador->getEstado() === EstadoTrabajador::ACTIVO
            ? EstadoTrabajador::INACTIVO
            : EstadoTrabajador::ACTIVO);

    try {
        if (in_array($nuevoEstado, [EstadoTrabajador::INACTIVO, EstadoTrabajador::SUSPENDIDO], true)) {
            $descubiertos = $this->trabajadorService->descubrirTurnosFuturos($trabajador);
            if ($descubiertos > 0) {
                $this->addFlash('warning', sprintf(
                    'Se marcaron %d turno(s) futuro(s) como descubiertos porque el trabajador ya no está activo.',
                    $descubiertos,
                ));
            }
        }

        $trabajador->setEstado($nuevoEstado);
        $this->em->flush();
        $this->addFlash('success', 'Estado del trabajador actualizado.');
    } catch (\RuntimeException $e) {
        $this->addFlash('danger', $e->getMessage());
    }

    return $this->redirectToRoute('app_trabajador_show', ['id' => $trabajador->getId()]);
}
```

### 3b. Actualizar la vista del trabajador

**Archivo:** `app/templates/trabajador/show.html.twig` (o donde esté el formulario de toggle-estado)

Buscar el formulario de cambio de estado y agregar un botón adicional para SUSPENDIDO cuando el trabajador está ACTIVO, y para reactivar cuando está SUSPENDIDO:

```twig
{# Botón principal: ACTIVO → INACTIVO o INACTIVO/SUSPENDIDO → ACTIVO #}
<form method="post" action="{{ path('app_trabajador_toggle_estado', {id: trabajador.id}) }}"
      onsubmit="return confirm('¿Confirmas el cambio de estado?')">
    <input type="hidden" name="_token" value="{{ csrf_token('toggle_estado_' ~ trabajador.id) }}">
    {% if trabajador.estado.value == 'ACTIVO' %}
        <input type="hidden" name="nuevo_estado" value="INACTIVO">
        <button type="submit" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-person-x me-1"></i> Inactivar
        </button>
    {% else %}
        <input type="hidden" name="nuevo_estado" value="ACTIVO">
        <button type="submit" class="btn btn-sm btn-outline-success">
            <i class="bi bi-person-check me-1"></i> Reactivar
        </button>
    {% endif %}
</form>

{# Botón adicional: suspender temporalmente (solo cuando ACTIVO) #}
{% if trabajador.estado.value == 'ACTIVO' %}
<form method="post" action="{{ path('app_trabajador_toggle_estado', {id: trabajador.id}) }}"
      onsubmit="return confirm('¿Confirmas suspender temporalmente al trabajador?')">
    <input type="hidden" name="_token" value="{{ csrf_token('toggle_estado_' ~ trabajador.id) }}">
    <input type="hidden" name="nuevo_estado" value="SUSPENDIDO">
    <button type="submit" class="btn btn-sm btn-outline-warning">
        <i class="bi bi-pause-circle me-1"></i> Suspender
    </button>
</form>
{% endif %}
```

**Nota:** Adaptar el HTML al layout actual de los botones en la vista. Lo importante es que el `input[name="nuevo_estado"]` se envíe con el valor correcto (`ACTIVO`, `INACTIVO`, o `SUSPENDIDO`).

---

## PUNTO 4 — `Turno.horaTermino` sin validación de que sea mayor que `horaInicio`

**Problema:** No hay ninguna validación en el formulario ni en el servicio que garantice `horaTermino > horaInicio`. Un turno con `horaTermino <= horaInicio` produce cálculos de horas negativos o incorrectos en liquidaciones y reportes.

### 4a. Agregar validación en `TurnoService::crear()` y `actualizarEstadoSegunTrabajador()`

**Archivo:** `app/src/Service/TurnoService.php`

En `crear()`, agregar antes de cualquier otra validación:

```php
// AGREGAR al inicio de crear(), antes de validar el paciente:
if ($turno->getHoraInicio() !== null && $turno->getHoraTermino() !== null) {
    if ($turno->getHoraTermino() <= $turno->getHoraInicio()) {
        throw new \DomainException(
            'La hora de término debe ser posterior a la hora de inicio.'
        );
    }
}
```

En `actualizarEstadoSegunTrabajador()`, agregar la misma validación al inicio (después del check de COMPLETADO):

```php
// AGREGAR tras el bloque que lanza si el turno está COMPLETADO:
if ($turno->getHoraInicio() !== null && $turno->getHoraTermino() !== null) {
    if ($turno->getHoraTermino() <= $turno->getHoraInicio()) {
        throw new \DomainException(
            'La hora de término debe ser posterior a la hora de inicio.'
        );
    }
}
```

### 4b. Agregar constraint `Assert\Expression` en la entidad `Turno`

**Archivo:** `app/src/Entity/Tenant/Turno.php`

Agregar a nivel de clase, junto a los otros atributos de clase:

```php
use Symfony\Component\Validator\Constraints as Assert;

// Agregar a la clase (a nivel de atributo de clase, no de propiedad):
#[Assert\Expression(
    expression: 'this.getHoraTermino() === null or this.getHoraInicio() === null or this.getHoraTermino() > this.getHoraInicio()',
    message: 'La hora de término debe ser posterior a la hora de inicio.',
)]
```

---

## PUNTO 5 — `asignarReemplazo()` marca `esReemplazo = true` permanentemente

**Problema:** Una vez asignado un reemplazo, el turno queda con `esReemplazo = true` y `motivoReemplazo` para siempre. Si luego se edita el turno y se reasigna el trabajador original (via `TurnoController::edit()`), el turno sigue liquidándose con tarifa de `REEMPLAZO` en lugar de la tarifa del tipo de turno real.

**Archivo:** `app/src/Service/TurnoService.php`
**Método:** `actualizarEstadoSegunTrabajador(Turno $turno)`

**Implementar:** Al reasignar un trabajador mediante el formulario de edición, si el nuevo trabajador es distinto al que activó el flag de reemplazo, limpiar el flag. La lógica más simple: si el turno se edita y tiene trabajador asignado (cubierto), y el trabajador es diferente al de la asignación de reemplazo original — es decir, si el usuario edita el turno manualmente — resetear `esReemplazo`:

En el bloque `if ($turno->getTrabajador() !== null)` dentro de `actualizarEstadoSegunTrabajador()`, agregar al final del bloque (antes del `setEstado`):

```php
// Si el turno se está editando manualmente y tenía una asignación de reemplazo previa,
// limpiarla: el administrador está corrigiendo el turno deliberadamente.
if ($turno->isEsReemplazo()) {
    $turno->setEsReemplazo(false)
          ->setMotivoReemplazo(null);
}

if ($turno->getEstado() === EstadoTurno::DESCUBIERTO) {
    $turno->setEstado(EstadoTurno::CUBIERTO);
}
```

**Nota:** Verificar que `setMotivoReemplazo(null)` es válido en la entidad (que el campo sea nullable). Si `MotivoReemplazo` es un enum no-nullable, cambiar a verificar si existe un setter que acepte null o agregar `?MotivoReemplazo` como tipo.

---

## PUNTO 6 — `registrarAsistencia()` no valida que el turno sea del día actual

**Problema:** Se puede registrar el inicio o término de un turno de cualquier fecha pasada o futura. Esto permite marcar asistencia en turnos históricos o futuros, generando registros de asistencia incoherentes.

**Archivo:** `app/src/Service/TurnoService.php`
**Método:** `registrarAsistencia(Turno $turno, string $tipo)`

**Implementar:** Agregar validación de fecha al inicio del método, antes de los checks de estado. Permitir un margen de ±1 día para cubrir turnos nocturnos que cruzan medianoche:

```php
public function registrarAsistencia(Turno $turno, string $tipo): Turno
{
    // AGREGAR: Validar que el turno corresponde al día actual (±1 día de margen)
    $fechaTurno = $turno->getFecha();
    if ($fechaTurno !== null) {
        $hoy   = new \DateTime('today');
        $ayer  = new \DateTime('yesterday');
        $manana = new \DateTime('tomorrow');

        if ($fechaTurno < $ayer || $fechaTurno > $manana) {
            throw new \LogicException(sprintf(
                'Solo se puede registrar asistencia en turnos del día actual o adyacentes (turno del %s).',
                $fechaTurno->format('d/m/Y'),
            ));
        }
    }

    if ($tipo === 'inicio') {
        // ... resto del código sin cambios
```

---

## PUNTO 7 — `Tarifa.vigenciaHasta` puede ser anterior a `vigenciaDesde`

**Problema:** No hay validación en la entidad ni en el formulario que garantice `vigenciaHasta >= vigenciaDesde`. Una tarifa con rango inválido nunca se encontraría en `findTarifaVigente()`, generando `RuntimeException` silenciosa al liquidar turnos del período.

### 7a. Agregar constraint en la entidad `Tarifa`

**Archivo:** `app/src/Entity/Tenant/Tarifa.php`

Agregar a nivel de clase:

```php
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: 'this.getVigenciaHasta() === null or this.getVigenciaHasta() >= this.getVigenciaDesde()',
    message: 'La fecha de fin de vigencia debe ser igual o posterior a la fecha de inicio.',
)]
```

### 7b. Agregar validación en `TarifaController` al crear y editar

**Archivo:** `app/src/Controller/TarifaController.php`

En `new()` y `edit()`, el framework ya ejecutará los constraints de la entidad porque se usa `$form->isValid()`. Si el constraint de `Assert\Expression` está correctamente definido en la entidad, la validación es automática. Solo verificar que el constraint funciona end-to-end.

Si por alguna razón el constraint de clase no se ejecuta (requiere `cascade` en la config de validación), agregar validación explícita en el controller:

```php
// En new() y edit(), dentro del bloque if ($form->isSubmitted() && $form->isValid()):
if ($tarifa->getVigenciaHasta() !== null && $tarifa->getVigenciaHasta() < $tarifa->getVigenciaDesde()) {
    $this->addFlash('danger', 'La fecha de fin de vigencia debe ser igual o posterior a la fecha de inicio.');
    return $this->render('tarifa/new.html.twig', ['form' => $form]); // o edit.html.twig
}
```

---

## Resumen de archivos a crear o modificar

| Archivo | Acción | Punto |
|---------|--------|-------|
| `app/src/Service/FinanzasService.php` | Modificar | 1, 2a |
| `app/src/Controller/FinanzasController.php` | Modificar | 2b |
| `app/templates/finanzas/factura_show.html.twig` | Modificar | 2c |
| `app/src/Controller/TrabajadorController.php` | Modificar | 3a |
| `app/templates/trabajador/show.html.twig` | Modificar | 3b |
| `app/src/Service/TurnoService.php` | Modificar | 4a, 5, 6 |
| `app/src/Entity/Tenant/Turno.php` | Modificar | 4b |
| `app/src/Entity/Tenant/Tarifa.php` | Modificar | 7a |
| `app/src/Controller/TarifaController.php` | Modificar | 7b |

## Instrucciones finales

1. Crear branch: `git checkout -b fix/logica-negocio-v4 develop`
2. Implementar los 7 puntos en el orden listado
3. Ejecutar `php bin/console cache:clear` después de los cambios
4. Commitear con mensaje descriptivo por grupo lógico:
   - `fix(finanzas): permitir regenerar liquidación desde estado ANULADA` (punto 1)
   - `feat(finanzas): implementar anulación de facturas` (punto 2)
   - `feat(trabajador): habilitar estado SUSPENDIDO desde la UI` (punto 3)
   - `fix(turnos): validar que horaTermino sea posterior a horaInicio` (punto 4)
   - `fix(reemplazo): limpiar flag esReemplazo al reasignar trabajador manualmente` (punto 5)
   - `fix(asistencia): restringir registro de asistencia al día actual` (punto 6)
   - `fix(tarifas): validar que vigenciaHasta no sea anterior a vigenciaDesde` (punto 7)
