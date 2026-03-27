<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Factura;
use App\Entity\LiquidacionMensual;
use App\Entity\Mandante;
use App\Entity\Paciente;
use App\Entity\Trabajador;
use App\Entity\Turno;
use App\Entity\User;
use App\Enum\EstadoFactura;
use App\Enum\EstadoLiquidacion;
use App\Enum\EstadoTurno;
use App\Enum\TipoTurno;
use App\Repository\LiquidacionMensualRepository;
use App\Repository\TurnoRepository;
use App\Service\FinanzasService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class FinanzasServiceTest extends TestCase
{
    private FinanzasService $service;
    private EntityManagerInterface $em;
    private TurnoRepository $turnoRepository;
    private LiquidacionMensualRepository $liquidacionRepository;

    protected function setUp(): void
    {
        $this->em                   = $this->createMock(EntityManagerInterface::class);
        $this->turnoRepository      = $this->createMock(TurnoRepository::class);
        $this->liquidacionRepository = $this->createMock(LiquidacionMensualRepository::class);

        $this->service = new FinanzasService(
            $this->em,
            $this->turnoRepository,
            $this->liquidacionRepository,
        );
    }

    // ─── generarLiquidacion ───────────────────────────────────────────────────

    public function testGenerarLiquidacionSinTurnosCreaBorrador(): void
    {
        $trabajador = $this->createMock(Trabajador::class);
        $user       = $this->createMock(User::class);

        $this->liquidacionRepository
            ->method('findOneByTrabajadorYPeriodo')
            ->willReturn(null);

        $this->turnoRepository
            ->method('findByTrabajadorYRango')
            ->willReturn([]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $liq = $this->service->generarLiquidacion($trabajador, 2025, 3, [], $user);

        $this->assertInstanceOf(LiquidacionMensual::class, $liq);
        $this->assertSame(EstadoLiquidacion::BORRADOR, $liq->getEstado());
        $this->assertSame(0, $liq->getTotalTurnos());
        $this->assertSame('0.00', $liq->getMontoTotal());
    }

    public function testGenerarLiquidacionConTurnosCompletados(): void
    {
        $trabajador = $this->createMock(Trabajador::class);
        $user       = $this->createMock(User::class);
        $paciente   = $this->createMock(Paciente::class);
        $paciente->method('getNombreCompleto')->willReturn('Juan Test');

        $turno = $this->createMock(Turno::class);
        $turno->method('getEstado')->willReturn(EstadoTurno::COMPLETADO);
        $turno->method('getTipoTurno')->willReturn(TipoTurno::T12H_DIA);
        $turno->method('isEsReemplazo')->willReturn(false);
        $turno->method('getFecha')->willReturn(new \DateTime('2025-03-15'));
        $turno->method('getPaciente')->willReturn($paciente);

        $this->liquidacionRepository
            ->method('findOneByTrabajadorYPeriodo')
            ->willReturn(null);

        $this->turnoRepository
            ->method('findByTrabajadorYRango')
            ->willReturn([$turno]);

        $this->em->expects($this->exactly(2))->method('persist'); // liquidacion + item
        $this->em->expects($this->once())->method('flush');

        $tarifas = ['TURNO_DIA' => 5000.0];
        $liq = $this->service->generarLiquidacion($trabajador, 2025, 3, $tarifas, $user);

        $this->assertSame(1, $liq->getTotalTurnos());
        $this->assertSame('12.00', $liq->getTotalHoras());
        $this->assertSame('60000.00', $liq->getMontoTotal()); // 12h * 5000
    }

    public function testGenerarLiquidacionIgnoraTurnosNOCompletados(): void
    {
        $trabajador = $this->createMock(Trabajador::class);
        $user       = $this->createMock(User::class);

        $turno = $this->createMock(Turno::class);
        $turno->method('getEstado')->willReturn(EstadoTurno::DESCUBIERTO);

        $this->liquidacionRepository->method('findOneByTrabajadorYPeriodo')->willReturn(null);
        $this->turnoRepository->method('findByTrabajadorYRango')->willReturn([$turno]);

        $this->em->expects($this->once())->method('persist');

        $liq = $this->service->generarLiquidacion($trabajador, 2025, 3, [], $user);

        $this->assertSame(0, $liq->getTotalTurnos());
    }

    // ─── aprobarLiquidacion ───────────────────────────────────────────────────

    public function testAprobarLiquidacionCambiaEstado(): void
    {
        $liq = new LiquidacionMensual(2025, 3);
        $this->assertSame(EstadoLiquidacion::BORRADOR, $liq->getEstado());

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->aprobarLiquidacion($liq);

        $this->assertSame(EstadoLiquidacion::APROBADA, $result->getEstado());
    }

    // ─── marcarPagadaLiquidacion ──────────────────────────────────────────────

    public function testMarcarPagadaLiquidacionSetFechaPago(): void
    {
        $liq   = new LiquidacionMensual(2025, 3);
        $fecha = new \DateTime('2025-04-10');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->marcarPagadaLiquidacion($liq, $fecha);

        $this->assertSame(EstadoLiquidacion::PAGADA, $result->getEstado());
        $this->assertSame($fecha, $result->getFechaPago());
    }

    // ─── exportarLiquidacionCsv ───────────────────────────────────────────────

    public function testExportarCsvIncluyeBOM(): void
    {
        $liq = new LiquidacionMensual(2025, 3);
        $csv = $this->service->exportarLiquidacionCsv($liq);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Concepto', $csv);
        $this->assertStringContainsString('TOTAL', $csv);
    }

    // ─── generarFactura ───────────────────────────────────────────────────────

    public function testGenerarFacturaCalculaIva(): void
    {
        $mandante = $this->createMock(Mandante::class);
        $user     = $this->createMock(User::class);

        $this->turnoRepository->method('findByMandanteYRango')->willReturn([]);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $factura = $this->service->generarFactura($mandante, 2025, 3, 100000.0, $user, 'F-001');

        $this->assertSame('100000.00', $factura->getMontoNeto());
        $this->assertSame('19000.00', $factura->getMontoIva());
        $this->assertSame('119000.00', $factura->getMontoTotal());
        $this->assertSame(EstadoFactura::BORRADOR, $factura->getEstado());
        $this->assertSame('F-001', $factura->getNumeroFactura());
    }

    // ─── emitirFactura ────────────────────────────────────────────────────────

    public function testEmitirFacturaCambiaEstadoYVencimiento(): void
    {
        $factura = new Factura(2025, 3);
        $fecha   = new \DateTime('2025-04-01');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->emitirFactura($factura, $fecha, 30);

        $this->assertSame(EstadoFactura::EMITIDA, $result->getEstado());
        $this->assertSame($fecha, $result->getFechaEmision());
        $this->assertSame('2025-05-01', $result->getFechaVencimiento()->format('Y-m-d'));
    }

    // ─── marcarPagadaFactura ──────────────────────────────────────────────────

    public function testMarcarPagadaFactura(): void
    {
        $factura = new Factura(2025, 3);
        $fecha   = new \DateTime('2025-04-15');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->marcarPagadaFactura($factura, $fecha);

        $this->assertSame(EstadoFactura::PAGADA, $result->getEstado());
        $this->assertSame($fecha, $result->getFechaPago());
    }
}
