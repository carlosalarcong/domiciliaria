# Prompt Codex — Lógica de Negocio v6

Proyecto: sistema multi-tenant Symfony 7.4 / PHP 8.3 para gestión domiciliaria de salud.
Rama de trabajo: `fix/logica-negocio-v7` (crear desde `develop`).

Aplica el siguiente cambio en un único commit.

---

## Problema — `resumenHorasPorMes()` incluye turnos no completados en el resumen de horas

**Archivo:** `app/src/Repository/TurnoRepository.php`  
**Método:** `resumenHorasPorMes()` (líneas ~327–359)

**Situación actual:**  
La query filtra turnos excluyendo solo el estado DESCUBIERTO:

```php
->andWhere('t.estado != :desc')
->setParameter('desc', EstadoTurno::DESCUBIERTO)
```

Esto incluye turnos en estado CUBIERTO (asignado pero no iniciado) y PARCIAL (iniciado sin terminar). Para un turno CUBIERTO sin `registroInicio`, el método privado `calcularHorasEfectivasTurno()` devuelve el fallback `duracionHoras()` (horas planificadas). El resultado es que el resumen mensual visible en el perfil del trabajador mezcla horas reales trabajadas con horas planificadas de turnos futuros o en curso, inflando el total reportado.

`calcularHorasTrabajadas()` en `TrabajadorService` filtra correctamente solo COMPLETADO — el resumen mensual debe ser consistente con esa lógica.

**Corrección:**  
Cambiar el filtro para incluir solo turnos en estado COMPLETADO:

```php
->andWhere('t.estado = :completado')
->setParameter('completado', EstadoTurno::COMPLETADO)
```

Reemplaza las dos líneas actuales (`andWhere` + `setParameter` de `desc`) por las dos líneas anteriores.

Commit: `fix(reportes): excluir turnos no completados del resumen mensual de horas`

---

## Notas generales

- Stack: PHP 8.3, Symfony 7.4, Doctrine ORM, PostgreSQL.
- No se requieren migraciones.
- No modifiques tests ni archivos de configuración.
