<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Tenant\ItemLiquidacion;
use App\Entity\Tenant\LiquidacionMensual;
use App\Entity\Tenant\Trabajador;
use App\Enum\EstadoLiquidacion;
use App\Enum\TipoConcepto;
use App\Service\ExportService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExportBukTest extends TestCase
{
    public function testRutConPuntosYGuionSeNormaliza(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $service = new ExportService($logger);

        $liq = $this->crearLiquidacionConItem('12.345.678-9', TipoConcepto::TURNO_DIA, '12.00', '5000.00', '60000.00');
        $csv = $service->exportarLiquidacionesBuk([$liq], 2026, 4);

        $dataLines = $this->csvDataLines($csv);
        $this->assertSame('12345678-9', explode(';', $dataLines[0])[0]);
    }

    public function testRutSinFormatoSeNormalizaConGuion(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $service = new ExportService($logger);

        $liq = $this->crearLiquidacionConItem('123456789', TipoConcepto::TURNO_DIA, '12.00', '5000.00', '60000.00');
        $csv = $service->exportarLiquidacionesBuk([$liq], 2026, 4);

        $dataLines = $this->csvDataLines($csv);
        $this->assertSame('12345678-9', explode(';', $dataLines[0])[0]);
    }

    public function testRutConKMinusculaSeNormalizaMayuscula(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $service = new ExportService($logger);

        $liq = $this->crearLiquidacionConItem('9876543-k', TipoConcepto::TURNO_DIA, '10.00', '4500.00', '45000.00');
        $csv = $service->exportarLiquidacionesBuk([$liq], 2026, 4);

        $dataLines = $this->csvDataLines($csv);
        $this->assertSame('9876543-K', explode(';', $dataLines[0])[0]);
    }

    public function testTrabajadorSinRutSeOmiteYRegistraWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Trabajador sin RUT omitido del export Buk: Juan Perez');

        $service = new ExportService($logger);

        $sinRut = $this->crearLiquidacionConItem(null, TipoConcepto::TURNO_DIA, '8.00', '4000.00', '32000.00', 'Juan', 'Perez');
        $conRut = $this->crearLiquidacionConItem('11111111-1', TipoConcepto::VISITA, '1.00', '15000.00', '15000.00', 'Ana', 'González Pérez');

        $csv = $service->exportarLiquidacionesBuk([$sinRut, $conRut], 2026, 4);
        $lines = $this->csvLines($csv);

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('11111111-1', $lines[1]);
    }

    public function testTurno24hGeneraDosFilasConMitadCantidadYMonto(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $service = new ExportService($logger);

        $liq = $this->crearLiquidacionConItem('12345678-9', TipoConcepto::TURNO_24H, '24.00', '5000.00', '120000.00');

        $csv = $service->exportarLiquidacionesBuk([$liq], 2026, 4);
        $dataLines = $this->csvDataLines($csv);

        $this->assertCount(2, $dataLines);

        $row1 = explode(';', $dataLines[0]);
        $row2 = explode(';', $dataLines[1]);

        $this->assertSame('HORA_NOCTURNA', $row1[4]);
        $this->assertSame('12.00', $row1[5]);
        $this->assertSame('60000', $row1[7]);

        $this->assertSame('HORA_NORMAL', $row2[4]);
        $this->assertSame('12.00', $row2[5]);
        $this->assertSame('60000', $row2[7]);
    }

    public function testConceptoDescuentoNoApareceEnSalida(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $service = new ExportService($logger);

        $liq = $this->crearLiquidacionBase('12345678-9');
        $this->agregarItem($liq, TipoConcepto::DESCUENTO, '1.00', '1000.00', '1000.00');
        $this->agregarItem($liq, TipoConcepto::BONO, '1.00', '5000.00', '5000.00');

        $csv = $service->exportarLiquidacionesBuk([$liq], 2026, 4);
        $dataLines = $this->csvDataLines($csv);

        $this->assertCount(1, $dataLines);
        $this->assertStringContainsString('BONO_OTRO', $dataLines[0]);
        $this->assertStringNotContainsString('DESCUENTO', $dataLines[0]);
    }

    public function testCabeceraTieneOrdenCorrecto(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $service = new ExportService($logger);

        $liq = $this->crearLiquidacionConItem('12345678-9', TipoConcepto::VISITA, '1.00', '12000.00', '12000.00');
        $csv = $service->exportarLiquidacionesBuk([$liq], 2026, 4);
        $lines = $this->csvLines($csv);

        $this->assertSame(
            'RUT_TRABAJADOR;NOMBRES;APELLIDO_PATERNO;APELLIDO_MATERNO;CONCEPTO;CANTIDAD;MONTO_UNITARIO;MONTO_TOTAL;PERIODO',
            $lines[0],
        );
    }

    public function testCsvIncluyeBomUtf8(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $service = new ExportService($logger);

        $liq = $this->crearLiquidacionConItem('12345678-9', TipoConcepto::TURNO_DIA, '1.00', '1000.00', '1000.00');
        $csv = $service->exportarLiquidacionesBuk([$liq], 2026, 4);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    private function crearLiquidacionConItem(
        ?string $rut,
        TipoConcepto $concepto,
        string $cantidad,
        string $valorUnitario,
        string $subtotal,
        string $nombres = 'Juan',
        string $apellidos = 'Pérez Soto',
    ): LiquidacionMensual {
        $liquidacion = $this->crearLiquidacionBase($rut, $nombres, $apellidos);
        $this->agregarItem($liquidacion, $concepto, $cantidad, $valorUnitario, $subtotal);

        return $liquidacion;
    }

    private function crearLiquidacionBase(?string $rut, string $nombres = 'Juan', string $apellidos = 'Pérez Soto'): LiquidacionMensual
    {
        $trabajador = new Trabajador();
        $trabajador->setNombres($nombres);
        $trabajador->setApellidos($apellidos);
        if ($rut !== null) {
            $trabajador->setRut($rut);
        }

        $liquidacion = new LiquidacionMensual(2026, 4);
        $liquidacion->setTrabajador($trabajador);
        $liquidacion->setEstado(EstadoLiquidacion::APROBADA);

        return $liquidacion;
    }

    private function agregarItem(
        LiquidacionMensual $liquidacion,
        TipoConcepto $concepto,
        string $cantidad,
        string $valorUnitario,
        string $subtotal,
    ): void {
        $item = new ItemLiquidacion();
        $item->setLiquidacion($liquidacion);
        $item->setConcepto($concepto);
        $item->setCantidad($cantidad);
        $item->setValorUnitario($valorUnitario);
        $item->setSubtotal($subtotal);

        $liquidacion->getItems()->add($item);
    }

    /** @return string[] */
    private function csvLines(string $csv): array
    {
        return explode("\r\n", substr($csv, 3));
    }

    /** @return string[] */
    private function csvDataLines(string $csv): array
    {
        $lines = $this->csvLines($csv);
        array_shift($lines);

        return $lines;
    }
}
