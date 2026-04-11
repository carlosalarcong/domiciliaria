<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\Factura;
use App\Entity\Tenant\ItemLiquidacion;
use App\Entity\Tenant\LiquidacionMensual;
use App\Entity\Tenant\Mandante;
use App\Entity\Tenant\Trabajador;
use App\Entity\Tenant\Turno;
use App\Entity\Tenant\User;
use App\Enum\EstadoFactura;
use App\Enum\EstadoLiquidacion;
use App\Enum\EstadoTurno;
use App\Enum\TipoConcepto;
use App\Enum\TipoTurno;
use App\Repository\LiquidacionMensualRepository;
use App\Repository\TarifaRepository;
use App\Repository\TurnoRepository;
use Doctrine\ORM\EntityManagerInterface;

class FinanzasService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TurnoRepository $turnoRepository,
        private readonly LiquidacionMensualRepository $liquidacionRepository,
        private readonly TarifaRepository $tarifaRepository,
        private readonly ConfiguracionService $configuracionService,
    ) {}

    // ─── Liquidaciones ───────────────────────────────────────────────────────

    /**
     * Genera (o regenera) la liquidación mensual de un trabajador
     * a partir de sus turnos completados en el período.
     * Las tarifas se resuelven automáticamente desde la BD según el concepto,
     * la fecha del turno y el mandante del paciente.
     */
    public function generarLiquidacion(
        Trabajador $trabajador,
        int $anio,
        int $mes,
        User $creadoPor,
    ): LiquidacionMensual {
        // Buscar o crear
        $liquidacion = $this->liquidacionRepository->findOneByTrabajadorYPeriodo($trabajador, $anio, $mes);

        if ($liquidacion === null) {
            $liquidacion = new LiquidacionMensual($anio, $mes);
            $liquidacion->setTrabajador($trabajador)->setCreadoPor($creadoPor);
            $this->em->persist($liquidacion);
        } else {
            if (!in_array($liquidacion->getEstado(), [EstadoLiquidacion::BORRADOR, EstadoLiquidacion::ANULADA], true)) {
                throw new \LogicException(sprintf(
                    'Solo se puede regenerar una liquidación en estado Borrador o Anulada (estado actual: %s).',
                    $liquidacion->getEstado()->etiqueta(),
                ));
            }

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

            $nota = sprintf(
                '[Regenerada por sistema el %s - items anteriores eliminados]',
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

            $concepto  = $this->tipoTurnoAConcepto($turno->getTipoTurno(), $turno->isEsReemplazo());
            $horas     = $this->calcularHorasEfectivas($turno);
            $mandante  = $turno->getPaciente()?->getMandante();
            $fecha     = $turno->getFecha() ?? new \DateTimeImmutable();
            $esHorasReales = $turno->getRegistroInicio() !== null && $turno->getRegistroTermino() !== null;
            $descripcion = $turno->getFecha()?->format('d/m/Y')
                . ' — ' . $turno->getPaciente()?->getNombreCompleto()
                . ($esHorasReales ? '' : ' (horas planificadas)');

            $tarifa = $this->tarifaRepository->findTarifaVigente($concepto, $fecha, $mandante);

            if ($tarifa === null) {
                throw new \RuntimeException(sprintf(
                    'No existe tarifa configurada para "%s" vigente en %s. Configure una tarifa general o específica para el mandante en Configuración → Tarifas.',
                    $concepto->etiqueta(),
                    $fecha->format('d/m/Y'),
                ));
            }

            $valorUnitario = (float) $tarifa->getValorUnitario();
            $subtotal      = $horas * $valorUnitario;

            $item = new ItemLiquidacion();
            $item->setLiquidacion($liquidacion)
                 ->setConcepto($concepto)
                 ->setDescripcion($descripcion)
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

    public function marcarPagadaLiquidacion(LiquidacionMensual $liquidacion, \DateTimeInterface $fechaPago): LiquidacionMensual
    {
        if ($liquidacion->getEstado() !== EstadoLiquidacion::APROBADA) {
            throw new \LogicException(sprintf(
                'Solo se puede marcar como pagada una liquidación aprobada (estado actual: %s).',
                $liquidacion->getEstado()->etiqueta(),
            ));
        }

        $manana = new \DateTime('+1 day');
        if ($fechaPago > $manana) {
            throw new \LogicException('La fecha de pago no puede ser una fecha futura.');
        }

        $liquidacion->setEstado(EstadoLiquidacion::PAGADA)
            ->setFechaPago($fechaPago)
            ->setFuePagada(true);
        $this->em->flush();

        return $liquidacion;
    }

    public function anularLiquidacion(LiquidacionMensual $liquidacion, string $motivo, User $anuladoPor): LiquidacionMensual
    {
        if ($liquidacion->getEstado() === EstadoLiquidacion::ANULADA) {
            throw new \LogicException('La liquidación ya está anulada.');
        }

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
        float $descuentoPorTurnoDescubierto = 0.0,
    ): Factura {
        $config = $this->configuracionService->get();

        $factura = new Factura($anio, $mes);
        $factura->setMandante($mandante)
                ->setNumeroFactura($numeroFactura)
                ->setMontoNeto(number_format($montoNeto, 2, '.', ''))
                ->setPorcentajeIva($config->getPorcentajeIva())
                ->setCreadoPor($creadoPor);

        // Calcular totales del período (turnos del mandante)
        $desde  = new \DateTime("{$anio}-{$mes}-01");
        $hasta  = (clone $desde)->modify('last day of this month');
        $turnos = $this->turnoRepository->findByMandanteYRango($mandante, $desde, $hasta);

        $completados  = 0;
        $descubiertos = 0;
        foreach ($turnos as $turno) {
            if ($turno->getEstado() === EstadoTurno::COMPLETADO) {
                $completados++;
            } elseif ($turno->getEstado() === EstadoTurno::DESCUBIERTO) {
                $descubiertos++;
            }
        }

        $factura->setTotalTurnos($completados)
                ->setTurnosDescubiertos($descubiertos);

        // Descuento automático por turnos descubiertos
        if ($descubiertos > 0 && $descuentoPorTurnoDescubierto > 0.0) {
            $montoDescuento = $descubiertos * $descuentoPorTurnoDescubierto;
            $factura->setMontoDescuento(number_format($montoDescuento, 2, '.', ''))
                    ->setDescripcionDescuento(sprintf(
                        'Descuento por %d turno%s descubierto%s ($%s c/u)',
                        $descubiertos,
                        $descubiertos > 1 ? 's' : '',
                        $descubiertos > 1 ? 's' : '',
                        number_format($descuentoPorTurnoDescubierto, 0, ',', '.'),
                    ));
        }

        $factura->recalcularIva();
        $factura->setFechaVencimiento(
            (new \DateTime())->modify('+' . $config->getDiasVencimientoFactura() . ' days')
        );
        $this->em->persist($factura);
        $this->em->flush();

        return $factura;
    }

    public function emitirFactura(Factura $factura, \DateTimeInterface $fechaEmision, ?int $diasVencimiento = null): Factura
    {
        if ($factura->getEstado() !== EstadoFactura::BORRADOR) {
            throw new \LogicException(sprintf(
                'Solo se puede emitir una factura en estado Borrador (estado actual: %s).',
                $factura->getEstado()->etiqueta(),
            ));
        }

        if ($diasVencimiento === null) {
            $diasVencimiento = $this->configuracionService->get()->getDiasVencimientoFactura();
        }

        $vencimiento = (clone $fechaEmision)->modify("+{$diasVencimiento} days");
        $factura->setEstado(EstadoFactura::EMITIDA)
                ->setFechaEmision($fechaEmision)
                ->setFechaVencimiento($vencimiento);
        $this->em->flush();

        return $factura;
    }

    public function marcarPagadaFactura(Factura $factura, \DateTimeInterface $fechaPago): Factura
    {
        if ($factura->getEstado() !== EstadoFactura::EMITIDA) {
            throw new \LogicException(sprintf(
                'Solo se puede marcar como pagada una factura emitida (estado actual: %s).',
                $factura->getEstado()->etiqueta(),
            ));
        }

        $manana = new \DateTime('+1 day');
        if ($fechaPago > $manana) {
            throw new \LogicException('La fecha de pago no puede ser una fecha futura.');
        }

        $factura->setEstado(EstadoFactura::PAGADA)->setFechaPago($fechaPago);
        $this->em->flush();

        return $factura;
    }

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

    private function calcularHorasEfectivas(Turno $turno): float
    {
        $inicio  = $turno->getRegistroInicio();
        $termino = $turno->getRegistroTermino();

        if ($inicio !== null && $termino !== null) {
            $minutos = ($termino->getTimestamp() - $inicio->getTimestamp()) / 60;
            if ($minutos < 0) {
                $minutos += 1440;
            }

            return round($minutos / 60, 2);
        }

        return (float) $turno->getTipoTurno()->duracionHoras();
    }
}
