<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Tenant\DisponibilidadTrabajador;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Entity\Tenant\Turno;
use App\Entity\Tenant\User;
use App\Enum\EstadoTurno;
use App\Enum\TipoTurno;
use App\Repository\Tenant\LiquidacionMensualRepository;
use App\Repository\Tenant\TurnoRepository;
use App\Service\TrabajadorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class TrabajadorServiceTest extends TestCase
{
    private TrabajadorService $service;
    private EntityManagerInterface $em;
    private TurnoRepository $turnoRepo;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->em        = $this->createMock(EntityManagerInterface::class);
        $this->turnoRepo = $this->createMock(TurnoRepository::class);
        $this->tmpDir    = sys_get_temp_dir() . '/test_uploads_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $this->service = new TrabajadorService(
            $this->em,
            $this->turnoRepo,
            $this->createMock(LiquidacionMensualRepository::class),
            $this->createMock(MessageBusInterface::class),
            $this->tmpDir,
        );
    }

    protected function tearDown(): void
    {
        // Limpiar directorio temporal
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        @rmdir($this->tmpDir);
    }

    // ─── calcularHorasTrabajadas ──────────────────────────────────────────────

    public function testCalcularHorasSoloContabilizaCompletados(): void
    {
        $trabajador = new Trabajador();
        $desde = new \DateTime('2025-06-01');
        $hasta = new \DateTime('2025-06-30');

        $t1 = $this->buildTurnoCompletado('08:00', '20:00'); // 12h
        $t2 = $this->buildTurnoCompletado('20:00', '08:00'); // 12h (noche, +1d)
        $t3 = $this->buildTurnoCubierto();                   // no cuenta

        $this->turnoRepo->method('findByTrabajadorYRango')->willReturn([$t1, $t2, $t3]);

        $resumen = $this->service->calcularHorasTrabajadas($trabajador, $desde, $hasta);

        $this->assertSame(2, $resumen['total_turnos']);
        $this->assertEqualsWithDelta(24.0, $resumen['total_horas'], 0.1);
    }

    public function testCalcularHorasSinTurnosDevuelveCero(): void
    {
        $this->turnoRepo->method('findByTrabajadorYRango')->willReturn([]);

        $resumen = $this->service->calcularHorasTrabajadas(
            new Trabajador(),
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30'),
        );

        $this->assertSame(0, $resumen['total_turnos']);
        $this->assertSame(0.0, $resumen['total_horas']);
    }

    public function testCalcularHorasUsaRegistroRealCuandoExiste(): void
    {
        $trabajador = new Trabajador();

        $turno = $this->buildTurnoCompletado('08:00', '20:00');
        // Sobrescribir con registros reales de 9h
        $turno->setRegistroInicio(new \DateTime('2025-06-10 09:00'));
        $turno->setRegistroTermino(new \DateTime('2025-06-10 18:00'));

        $this->turnoRepo->method('findByTrabajadorYRango')->willReturn([$turno]);

        $resumen = $this->service->calcularHorasTrabajadas($trabajador, new \DateTime('2025-06-01'), new \DateTime('2025-06-30'));

        $this->assertSame(1, $resumen['total_turnos']);
        $this->assertEqualsWithDelta(9.0, $resumen['total_horas'], 0.1);
    }

    // ─── generarCsvHoras ─────────────────────────────────────────────────────

    public function testGenerarCsvContieneEncabezadoYTotal(): void
    {
        $trabajador = new Trabajador();
        $t = $this->buildTurnoCompletado('08:00', '20:00');

        $this->turnoRepo->method('findByTrabajadorYRango')->willReturn([$t]);

        $resumen = $this->service->calcularHorasTrabajadas($trabajador, new \DateTime('2025-06-01'), new \DateTime('2025-06-30'));
        $csv = $this->service->generarCsvHoras($trabajador, $resumen);

        $this->assertStringContainsString('Fecha;Paciente;Tipo turno', $csv);
        $this->assertStringContainsString('TOTAL', $csv);
        $this->assertStringContainsString('12', $csv);
    }

    public function testGenerarCsvTieneBomUtf8(): void
    {
        $resumen = ['turnos' => [], 'total_horas' => 0.0, 'total_turnos' => 0];
        $csv = $this->service->generarCsvHoras(new Trabajador(), $resumen);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    // ─── registrarDisponibilidad ──────────────────────────────────────────────

    public function testRegistrarDisponibilidadAsignaTrabajadorYPersiste(): void
    {
        $trabajador    = new Trabajador();
        $disponibilidad = new DisponibilidadTrabajador();

        $this->em->expects($this->once())->method('persist')->with($disponibilidad);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->registrarDisponibilidad($trabajador, $disponibilidad);

        $this->assertSame($trabajador, $result->getTrabajador());
    }

    public function testEliminarDisponibilidadLlamaRemoveYFlush(): void
    {
        $disponibilidad = new DisponibilidadTrabajador();

        $this->em->expects($this->once())->method('remove')->with($disponibilidad);
        $this->em->expects($this->once())->method('flush');

        $this->service->eliminarDisponibilidad($disponibilidad);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function buildTurnoCompletado(string $inicio, string $termino): Turno
    {
        $turno = $this->buildTurnoBase();
        $turno->setEstado(EstadoTurno::COMPLETADO)
              ->setHoraInicio(new \DateTime($inicio))
              ->setHoraTermino(new \DateTime($termino));

        return $turno;
    }

    private function buildTurnoCubierto(): Turno
    {
        $turno = $this->buildTurnoBase();
        $turno->setEstado(EstadoTurno::CUBIERTO);

        return $turno;
    }

    private function buildTurnoBase(): Turno
    {
        $paciente = new Paciente();
        $r = new \ReflectionClass($paciente);
        foreach (['nombres' => 'Test', 'apellidos' => 'Paciente'] as $prop => $val) {
            if ($r->hasProperty($prop)) {
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($paciente, $val);
            }
        }

        $turno = new Turno();
        $turno->setPaciente($paciente)
              ->setFecha(new \DateTime('2025-06-10'))
              ->setTipoTurno(TipoTurno::T12H_DIA);

        return $turno;
    }
}
