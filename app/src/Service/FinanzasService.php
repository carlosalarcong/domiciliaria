<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Factura;
use App\Entity\ItemLiquidacion;
use App\Entity\LiquidacionMensual;
use App\Entity\Mandante;
use App\Entity\Trabajador;
use App\Entity\User;
use App\Enum\EstadoFactura;
use App\Enum\EstadoLiquidacion;
use App\Enum\EstadoTurno;
use App\Enum\TipoConcepto;
use App\Enum\TipoTurno;
use App\Repository\LiquidacionMensualRepository;
use App\Repository\TurnoRepository;
use Doctrine\ORM\EntityManagerInterface;

class FinanzasService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TurnoRepository $turnoRepository,
        private readonly LiquidacionMensualRepository $liquidacionRepository,
    ) {}

    // ─── Liquidaciones ───────────────────────────────────────────────────────

    /**
     * Genera (o regenera) la liquidación mensual de un trabajador
     * a partir de sus turnos completados en el período.
     */
    public function generarLiquidacion(
        Trabajador $trabajador,
        int $anio,
        int $mes,
        array $tarifas,
        User $creadoPor,
    ): LiquidacionMensual {
        // Buscar o crear
        $liquidacion = $this->liquidacionRepository->findOneByTrabajadorYPeriodo($trabajador, $anio, $mes);

        if ($liquidacion === null) {
            $liquidacion = new LiquidacionMensual($anio, $mes);
            $liquidacion->setTrabajador($trabajador)->setCreadoPor($creadoPor);
            $this->em->persist($liquidacion);
        } else {
            // Limpiar items anteriores
            foreach ($liquidacion->getItems() as $item) {
                $this->em->remove($item);
            }
        }

        // Obtener turnos del período
        $desde  = new \DateTime("{$anio}-{$mes}-01");
        $hasta  = (clone $desde)->modify('last day of this month');
        $turnos = $this->turnoRepository->findByTrabajadorYRango($trabajador, $desde, $hasta);

        $montoTotal  = 0.0;
        $totalTurnos = 0;
        $totalHoras  = 0.0;

        foreach ($turnos as $turno) {
            if ($turno->getEstado() !== EstadoTurno::COMPLETADO) {
                continue;
            }

            $concepto   = $this->tipoTurnoAConcepto($turno->getTipoTurno(), $turno->isEsReemplazo());
            $horas      = (float) $turno->getTipoTurno()->duracionHoras();
            $valorUnitario = $tarifas[$concepto->value] ?? 0.0;
            $subtotal   = $horas * $valorUnitario;

            $item = new ItemLiquidacion();
            $item->setLiquidacion($liquidacion)
                 ->setConcepto($concepto)
                 ->setDescripcion($turno->getFecha()?->format('d/m/Y') . ' — ' . $turno->getPaciente()?->getNombreCompleto())
                 ->setCantidad(number_format($horas, 2, '.', ''))
                 ->setValorUnitario(number_format($valorUnitario, 2, '.', ''))
                 ->setSubtotal(number_format($subtotal, 2, '.', ''))
                 ->setTurno($turno);

            $this->em->persist($item);

            $montoTotal  += $subtotal;
            $totalTurnos++;
            $totalHoras  += $horas;
        }

        $liquidacion->setTotalTurnos($totalTurnos)
                    ->setTotalHoras(number_format($totalHoras, 2, '.', ''))
                    ->setMontoTotal(number_format($montoTotal, 2, '.', ''));

        $this->em->flush();

        return $liquidacion;
    }

    public function aprobarLiquidacion(LiquidacionMensual $liquidacion): LiquidacionMensual
    {
        $liquidacion->setEstado(EstadoLiquidacion::APROBADA);
        $this->em->flush();

        return $liquidacion;
    }

    public function marcarPagadaLiquidacion(LiquidacionMensual $liquidacion, \DateTimeInterface $fechaPago): LiquidacionMensual
    {
        $liquidacion->setEstado(EstadoLiquidacion::PAGADA)->setFechaPago($fechaPago);
        $this->em->flush();

        return $liquidacion;
    }

    public function exportarLiquidacionCsv(LiquidacionMensual $liquidacion): string
    {
        $lines   = [];
        $lines[] = implode(';', ['Concepto', 'Descripción', 'Cantidad (h)', 'Valor unitario', 'Subtotal']);

        foreach ($liquidacion->getItems() as $item) {
            $lines[] = implode(';', [
                $item->getConcepto()->etiqueta(),
                $item->getDescripcion() ?? '',
                number_format((float) $item->getCantidad(), 2, ',', ''),
                number_format((float) $item->getValorUnitario(), 2, ',', ''),
                number_format((float) $item->getSubtotal(), 2, ',', ''),
            ]);
        }

        $lines[] = '';
        $lines[] = implode(';', ['TOTAL', '', '', '', number_format((float) $liquidacion->getMontoTotal(), 2, ',', '')]);

        return "\xEF\xBB\xBF" . implode("\r\n", $lines);
    }

    // ─── Facturas ────────────────────────────────────────────────────────────

    public function generarFactura(
        Mandante $mandante,
        int $anio,
        int $mes,
        float $montoNeto,
        User $creadoPor,
        ?string $numeroFactura = null,
    ): Factura {
        $factura = new Factura($anio, $mes);
        $factura->setMandante($mandante)
                ->setNumeroFactura($numeroFactura)
                ->setMontoNeto(number_format($montoNeto, 2, '.', ''))
                ->setCreadoPor($creadoPor);

        // Calcular totales del período (turnos completados de pacientes del mandante)
        $desde  = new \DateTime("{$anio}-{$mes}-01");
        $hasta  = (clone $desde)->modify('last day of this month');
        $turnos = $this->turnoRepository->findByMandanteYRango($mandante, $desde, $hasta);
        $factura->setTotalTurnos(count(array_filter($turnos, fn($t) => $t->getEstado() === EstadoTurno::COMPLETADO)));

        $factura->recalcularIva();
        $this->em->persist($factura);
        $this->em->flush();

        return $factura;
    }

    public function emitirFactura(Factura $factura, \DateTimeInterface $fechaEmision, int $diasVencimiento = 30): Factura
    {
        $vencimiento = (clone $fechaEmision)->modify("+{$diasVencimiento} days");
        $factura->setEstado(EstadoFactura::EMITIDA)
                ->setFechaEmision($fechaEmision)
                ->setFechaVencimiento($vencimiento);
        $this->em->flush();

        return $factura;
    }

    public function marcarPagadaFactura(Factura $factura, \DateTimeInterface $fechaPago): Factura
    {
        $factura->setEstado(EstadoFactura::PAGADA)->setFechaPago($fechaPago);
        $this->em->flush();

        return $factura;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function tipoTurnoAConcepto(TipoTurno $tipo, bool $esReemplazo): TipoConcepto
    {
        if ($esReemplazo) {
            return TipoConcepto::REEMPLAZO;
        }

        return match ($tipo) {
            TipoTurno::T12H_DIA   => TipoConcepto::TURNO_DIA,
            TipoTurno::T12H_NOCHE => TipoConcepto::TURNO_NOCHE,
            TipoTurno::T24H       => TipoConcepto::TURNO_24H,
            TipoTurno::VISITA     => TipoConcepto::VISITA,
        };
    }
}
