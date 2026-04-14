<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\Factura;
use App\Entity\Tenant\LiquidacionMensual;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Entity\Tenant\Turno;
use App\Enum\TipoConcepto;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ExportService
{
    private const BOM = "\xEF\xBB\xBF";

    public function __construct(
        #[Autowire(service: 'monolog.logger.finanzas')]
        private readonly LoggerInterface $logger,
    ) {}

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

    // ─── Trabajadores ────────────────────────────────────────────────────────

    /**
     * @param Trabajador[] $trabajadores
     */
    public function exportarTrabajadoresCsv(array $trabajadores): string
    {
        $lines = [];
        $lines[] = implode(';', [
            'Apellidos',
            'Nombres',
            'RUT',
            'Perfil',
            'Estado',
            'Teléfono',
            'Email',
            'Dirección',
            'Fecha ingreso',
            'Fecha salida',
        ]);

        foreach ($trabajadores as $t) {
            $lines[] = implode(';', [
                $this->esc($t->getApellidos()),
                $this->esc($t->getNombres()),
                $this->esc($t->getRut()),
                $this->esc($t->getPerfil()->etiqueta()),
                $this->esc($t->getEstado()->etiqueta()),
                $this->esc($t->getTelefono()),
                $this->esc($t->getEmail()),
                $this->esc($t->getDireccion()),
                $t->getFechaIngreso()?->format('d/m/Y') ?? '',
                $t->getFechaSalida()?->format('d/m/Y') ?? '',
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

    // ─── Liquidaciones Buk ────────────────────────────────────────────────────

    /**
     * @param LiquidacionMensual[] $liquidaciones
     */
    public function exportarLiquidacionesBuk(array $liquidaciones, int $anio, int $mes): string
    {
        $periodo = sprintf('%04d-%02d', $anio, $mes);
        $header = implode(';', [
            'RUT_TRABAJADOR',
            'NOMBRES',
            'APELLIDO_PATERNO',
            'APELLIDO_MATERNO',
            'CONCEPTO',
            'CANTIDAD',
            'MONTO_UNITARIO',
            'MONTO_TOTAL',
            'PERIODO',
        ]);

        $workers = [];

        foreach ($liquidaciones as $liquidacion) {
            $trabajador = $liquidacion->getTrabajador();
            if ($trabajador === null) {
                continue;
            }

            $rut = $this->normalizarRut($trabajador->getRut());
            if ($rut === null) {
                $this->logger->warning(sprintf(
                    'Trabajador sin RUT omitido del export Buk: %s %s',
                    trim((string) $trabajador->getNombres()),
                    trim((string) $trabajador->getApellidos()),
                ));
                continue;
            }

            $key = ($trabajador->getApellidos() ?? '') . '|' . ($trabajador->getNombres() ?? '') . '|' . $rut;

            if (!isset($workers[$key])) {
                [$apellidoPaterno, $apellidoMaterno] = $this->separarApellidos($trabajador->getApellidos());
                $workers[$key] = [
                    'rut' => $rut,
                    'nombres' => $trabajador->getNombres() ?? '',
                    'apellido_paterno' => $apellidoPaterno,
                    'apellido_materno' => $apellidoMaterno,
                    'rows' => [],
                ];
            }

            foreach ($liquidacion->getItems() as $item) {
                $concepto = $item->getConcepto();

                if ($concepto === TipoConcepto::DESCUENTO) {
                    continue;
                }

                $cantidad = (float) $item->getCantidad();
                $unitario = (int) round((float) $item->getValorUnitario());
                $subtotal = (float) $item->getSubtotal();

                if ($concepto === TipoConcepto::TURNO_24H) {
                    foreach (['HORA_NORMAL', 'HORA_NOCTURNA'] as $codigoBuk) {
                        $workers[$key]['rows'][] = [
                            'concepto' => $codigoBuk,
                            'cantidad' => number_format($cantidad / 2, 2, '.', ''),
                            'monto_unitario' => (string) $unitario,
                            'monto_total' => (string) (int) round($subtotal / 2),
                            'periodo' => $periodo,
                        ];
                    }
                    continue;
                }

                $workers[$key]['rows'][] = [
                    'concepto' => $this->mapearConceptoBuk($concepto),
                    'cantidad' => number_format($cantidad, 2, '.', ''),
                    'monto_unitario' => (string) $unitario,
                    'monto_total' => (string) (int) round($subtotal),
                    'periodo' => $periodo,
                ];
            }
        }

        ksort($workers, SORT_NATURAL | SORT_FLAG_CASE);

        $lines = [$header];

        foreach ($workers as $worker) {
            usort($worker['rows'], fn(array $a, array $b): int => strcmp((string) $a['concepto'], (string) $b['concepto']));

            foreach ($worker['rows'] as $row) {
                $lines[] = implode(';', [
                    $this->esc((string) $worker['rut']),
                    $this->esc((string) $worker['nombres']),
                    $this->esc((string) $worker['apellido_paterno']),
                    $this->esc((string) $worker['apellido_materno']),
                    $this->esc((string) $row['concepto']),
                    (string) $row['cantidad'],
                    (string) $row['monto_unitario'],
                    (string) $row['monto_total'],
                    (string) $row['periodo'],
                ]);
            }
        }

        return self::BOM . implode("\r\n", $lines);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function mapearConceptoBuk(TipoConcepto $concepto): string
    {
        return match ($concepto) {
            TipoConcepto::TURNO_DIA => 'HORA_NORMAL',
            TipoConcepto::TURNO_NOCHE => 'HORA_NOCTURNA',
            TipoConcepto::VISITA => 'BONO_VISITA',
            TipoConcepto::REEMPLAZO => 'HORA_EXTRA',
            TipoConcepto::BONO => 'BONO_OTRO',
            TipoConcepto::TURNO_24H, TipoConcepto::DESCUENTO => '',
        };
    }

    private function normalizarRut(?string $rut): ?string
    {
        $valor = strtoupper(str_replace('.', '', trim((string) $rut)));
        if ($valor === '') {
            return null;
        }

        if (!str_contains($valor, '-')) {
            if (strlen($valor) < 2) {
                return null;
            }
            $valor = substr($valor, 0, -1) . '-' . substr($valor, -1);
        }

        [$numero, $dv] = array_pad(explode('-', $valor, 2), 2, '');
        $numero = preg_replace('/\D+/', '', $numero ?? '') ?? '';
        $dv = strtoupper(trim($dv));

        if ($numero === '' || $dv === '') {
            return null;
        }

        return $numero . '-' . $dv;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function separarApellidos(?string $apellidos): array
    {
        $texto = trim((string) $apellidos);
        if ($texto === '') {
            return ['', ''];
        }

        $partes = preg_split('/\s+/', $texto, 2) ?: [];
        $paterno = $partes[0] ?? '';
        $materno = $partes[1] ?? '';

        return [$paterno, $materno];
    }

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
