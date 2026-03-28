<?php

declare(strict_types=1);

namespace App\DataFixtures;

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
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;

#[TenantFixture]
class FinanzasFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int { return 4; }

    public function load(ObjectManager $manager): void
    {
        /** @var User $admin */
        $admin = $manager->getRepository(User::class)->findAll()[0];

        $trabajadores = $manager->getRepository(Trabajador::class)->findAll();
        $mandantes    = $manager->getRepository(Mandante::class)->findAll();

        if (empty($trabajadores) || empty($mandantes)) {
            return;
        }

        $anioActual = (int) date('Y');
        $mesActual  = (int) date('n');

        // Mes anterior para liquidaciones ya cerradas
        $mesAnterior = $mesActual === 1 ? 12 : $mesActual - 1;
        $anioAnterior = $mesActual === 1 ? $anioActual - 1 : $anioActual;

        // Tarifas de ejemplo ($ por hora), indexadas por value del enum
        $tarifas = [
            TipoConcepto::TURNO_DIA->value   => 5_500.0,
            TipoConcepto::TURNO_NOCHE->value => 7_000.0,
            TipoConcepto::TURNO_24H->value   => 6_200.0,
            TipoConcepto::VISITA->value      => 4_000.0,
            TipoConcepto::REEMPLAZO->value   => 6_000.0,
        ];

        // ─── Liquidaciones del mes anterior (PAGADAS) ────────────────────────

        foreach (array_slice($trabajadores, 0, 2) as $idx => $trabajador) {
            $liq = $this->crearLiquidacionDesdeTurnos(
                $manager, $trabajador, $anioAnterior, $mesAnterior, $tarifas, $admin
            );
            $liq->setEstado(EstadoLiquidacion::PAGADA)
                ->setFechaPago(new \DateTime('first day of this month'));
            $manager->persist($liq);
        }

        // ─── Liquidación del mes actual del primer trabajador (APROBADA) ────

        if (isset($trabajadores[0])) {
            $liq = $this->crearLiquidacionDesdeTurnos(
                $manager, $trabajadores[0], $anioActual, $mesActual, $tarifas, $admin
            );
            $liq->setEstado(EstadoLiquidacion::APROBADA);
            $manager->persist($liq);
        }

        // ─── Liquidación del mes actual del segundo trabajador (BORRADOR) ───

        if (isset($trabajadores[1])) {
            $liq = $this->crearLiquidacionDesdeTurnos(
                $manager, $trabajadores[1], $anioActual, $mesActual, $tarifas, $admin
            );
            // queda en BORRADOR por defecto
            $manager->persist($liq);
        }

        $manager->flush();

        // ─── Facturas ────────────────────────────────────────────────────────

        // Factura del mes anterior — PAGADA
        if (isset($mandantes[0])) {
            $fac = $this->crearFactura($manager, $mandantes[0], $anioAnterior, $mesAnterior, 1_850_000.0, 'F-2025-001', $admin);
            $fac->setEstado(EstadoFactura::PAGADA)
                ->setFechaEmision(new \DateTime('first day of last month'))
                ->setFechaVencimiento(new \DateTime('first day of this month'))
                ->setFechaPago(new \DateTime('first day of this month'));
        }

        // Factura del mes actual — EMITIDA
        if (isset($mandantes[1])) {
            $fac = $this->crearFactura($manager, $mandantes[1], $anioActual, $mesActual, 2_200_000.0, 'F-2025-002', $admin);
            $fac->setEstado(EstadoFactura::EMITIDA)
                ->setFechaEmision(new \DateTime('today'))
                ->setFechaVencimiento(new \DateTime('+30 days'));
        }

        // Factura del mes actual del tercer mandante — BORRADOR
        if (isset($mandantes[2])) {
            $this->crearFactura($manager, $mandantes[2], $anioActual, $mesActual, 980_000.0, null, $admin);
        }

        $manager->flush();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function crearLiquidacionDesdeTurnos(
        ObjectManager $manager,
        Trabajador $trabajador,
        int $anio,
        int $mes,
        array $tarifas,
        User $admin,
    ): LiquidacionMensual {
        $liq = new LiquidacionMensual($anio, $mes);
        $liq->setTrabajador($trabajador)->setCreadoPor($admin);
        $manager->persist($liq);

        // Buscar turnos completados del período
        $desde  = new \DateTime("{$anio}-{$mes}-01");
        $hasta  = (clone $desde)->modify('last day of this month');

        /** @var Turno[] $turnos */
        $turnos = $manager->getRepository(Turno::class)->createQueryBuilder('t')
            ->where('t.trabajador = :trabajador')
            ->andWhere('t.fecha BETWEEN :desde AND :hasta')
            ->andWhere('t.estado = :estado')
            ->setParameter('trabajador', $trabajador)
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->setParameter('estado', EstadoTurno::COMPLETADO)
            ->getQuery()
            ->getResult();

        $montoTotal  = 0.0;
        $totalTurnos = 0;
        $totalHoras  = 0.0;

        foreach ($turnos as $turno) {
            $concepto      = $this->tipoTurnoAConcepto($turno->getTipoTurno(), $turno->isEsReemplazo());
            $horas         = (float) $turno->getTipoTurno()->duracionHoras();
            $valorUnitario = $tarifas[$concepto->value] ?? 0.0;
            $subtotal      = $horas * $valorUnitario;

            $item = new ItemLiquidacion();
            $item->setLiquidacion($liq)
                 ->setConcepto($concepto)
                 ->setDescripcion($turno->getFecha()?->format('d/m/Y') . ' — ' . $turno->getPaciente()?->getNombreCompleto())
                 ->setCantidad(number_format($horas, 2, '.', ''))
                 ->setValorUnitario(number_format($valorUnitario, 2, '.', ''))
                 ->setSubtotal(number_format($subtotal, 2, '.', ''))
                 ->setTurno($turno);
            $manager->persist($item);

            $montoTotal  += $subtotal;
            $totalTurnos++;
            $totalHoras  += $horas;
        }

        // Si no hay turnos completados (ej. mes actual con todos futuros), generamos ítems de ejemplo
        if ($totalTurnos === 0) {
            $ejemplos = [
                [TipoConcepto::TURNO_DIA,   6, $tarifas[TipoConcepto::TURNO_DIA->value]   ?? 5500.0],
                [TipoConcepto::TURNO_NOCHE, 4, $tarifas[TipoConcepto::TURNO_NOCHE->value] ?? 7000.0],
            ];
            foreach ($ejemplos as [$concepto, $horas, $valor]) {
                $subtotal = $horas * $valor;
                $item = new ItemLiquidacion();
                $item->setLiquidacion($liq)
                     ->setConcepto($concepto)
                     ->setDescripcion("Turnos {$concepto->etiqueta()} — {$mes}/{$anio}")
                     ->setCantidad(number_format((float) $horas, 2, '.', ''))
                     ->setValorUnitario(number_format($valor, 2, '.', ''))
                     ->setSubtotal(number_format($subtotal, 2, '.', ''));
                $manager->persist($item);
                $montoTotal  += $subtotal;
                $totalTurnos += $horas;
                $totalHoras  += $horas;
            }
        }

        $liq->setTotalTurnos($totalTurnos)
            ->setTotalHoras(number_format($totalHoras, 2, '.', ''))
            ->setMontoTotal(number_format($montoTotal, 2, '.', ''));

        return $liq;
    }

    private function crearFactura(
        ObjectManager $manager,
        Mandante $mandante,
        int $anio,
        int $mes,
        float $montoNeto,
        ?string $numeroFactura,
        User $admin,
    ): Factura {
        $factura = new Factura($anio, $mes);
        $factura->setMandante($mandante)
                ->setNumeroFactura($numeroFactura)
                ->setMontoNeto(number_format($montoNeto, 2, '.', ''))
                ->setCreadoPor($admin);
        $factura->recalcularIva();
        $manager->persist($factura);

        return $factura;
    }

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
