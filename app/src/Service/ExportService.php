<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\Factura;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Turno;

class ExportService
{
    private const BOM = "\xEF\xBB\xBF";

    // ─── Pacientes ────────────────────────────────────────────────────────────

    /**
     * @param Paciente[] $pacientes
     */
    public function exportarPacientesCsv(array $pacientes): string
    {
        $lines = [];
        $lines[] = implode(';', [
            'Apellidos',
            'Nombres',
            'RUT',
            'Fecha nacimiento',
            'Edad',
            'Tipo servicio',
            'Estado',
            'Mandante',
            'Dirección',
            'Comuna',
            'Región',
            'Teléfono',
            'Tutor',
            'Teléfono tutor',
            'Relación tutor',
            'Fecha ingreso',
            'Fecha término',
        ]);

        foreach ($pacientes as $p) {
            $lines[] = implode(';', [
                $this->esc($p->getApellidos()),
                $this->esc($p->getNombres()),
                $this->esc($p->getRut()),
                $p->getFechaNacimiento()?->format('d/m/Y') ?? '',
                (string) ($p->getEdad() ?? ''),
                $this->esc($p->getTipoServicio()->etiqueta()),
                $this->esc($p->getEstado()->etiqueta()),
                $this->esc($p->getMandante()?->getNombre()),
                $this->esc($p->getDireccion()),
                $this->esc($p->getComuna()),
                $this->esc($p->getRegion()),
                $this->esc($p->getTelefono()),
                $this->esc($p->getTutorNombre()),
                $this->esc($p->getTutorTelefono()),
                $this->esc($p->getTutorRelacion()),
                $p->getFechaIngreso()?->format('d/m/Y') ?? '',
                $p->getFechaTermino()?->format('d/m/Y') ?? '',
            ]);
        }

        return self::BOM . implode("\r\n", $lines);
    }

    // ─── Turnos ───────────────────────────────────────────────────────────────

    /**
     * @param Turno[] $turnos
     */
    public function exportarTurnosCsv(array $turnos): string
    {
        $lines = [];
        $lines[] = implode(';', [
            'Fecha',
            'Tipo turno',
            'Estado',
            'Paciente',
            'Trabajador',
            'Hora inicio',
            'Hora término',
            'Reemplazo',
            'Motivo reemplazo',
            'Registro inicio',
            'Registro término',
            'Observaciones',
        ]);

        foreach ($turnos as $t) {
            $lines[] = implode(';', [
                $t->getFecha()?->format('d/m/Y') ?? '',
                $this->esc($t->getTipoTurno()->etiqueta()),
                $this->esc($t->getEstado()->etiqueta()),
                $this->esc($t->getPaciente()?->getNombreCompleto()),
                $this->esc($t->getTrabajador()?->getNombreCompleto()),
                $t->getHoraInicio()?->format('H:i') ?? '',
                $t->getHoraTermino()?->format('H:i') ?? '',
                $t->isEsReemplazo() ? 'Sí' : 'No',
                $this->esc($t->getMotivoReemplazo()?->etiqueta()),
                $t->getRegistroInicio()?->format('d/m/Y H:i') ?? '',
                $t->getRegistroTermino()?->format('d/m/Y H:i') ?? '',
                $this->esc($t->getObservaciones()),
            ]);
        }

        return self::BOM . implode("\r\n", $lines);
    }

    // ─── Facturas ─────────────────────────────────────────────────────────────

    /**
     * @param Factura[] $facturas
     */
    public function exportarFacturasCsv(array $facturas): string
    {
        $lines = [];
        $lines[] = implode(';', [
            'N° Factura',
            'Período',
            'Mandante',
            'Estado',
            'Total turnos',
            'Monto neto',
            'IVA (%)',
            'Monto IVA',
            'Monto total',
            'Fecha emisión',
            'Fecha vencimiento',
            'Fecha pago',
        ]);

        foreach ($facturas as $f) {
            $lines[] = implode(';', [
                $this->esc($f->getNumeroFactura()),
                $f->getPeriodoLabel(),
                $this->esc($f->getMandante()?->getNombre()),
                $this->esc($f->getEstado()->etiqueta()),
                (string) $f->getTotalTurnos(),
                number_format((float) $f->getMontoNeto(), 2, ',', '.'),
                number_format((float) $f->getPorcentajeIva(), 2, ',', ''),
                number_format((float) $f->getMontoIva(), 2, ',', '.'),
                number_format((float) $f->getMontoTotal(), 2, ',', '.'),
                $f->getFechaEmision()?->format('d/m/Y') ?? '',
                $f->getFechaVencimiento()?->format('d/m/Y') ?? '',
                $f->getFechaPago()?->format('d/m/Y') ?? '',
            ]);
        }

        return self::BOM . implode("\r\n", $lines);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function esc(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        // Wrap in quotes if the value contains semicolons, quotes or newlines
        if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
