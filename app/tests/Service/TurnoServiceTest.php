<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Paciente;
use App\Entity\Trabajador;
use App\Entity\Turno;
use App\Entity\User;
use App\Enum\EstadoTurno;
use App\Enum\MotivoReemplazo;
use App\Enum\TipoTurno;
use App\Message\TurnoDescubiertoMessage;
use App\Repository\TurnoRepository;
use App\Service\TurnoService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TurnoServiceTest extends TestCase
{
    private TurnoService $service;
    private EntityManagerInterface $em;
    private TurnoRepository $repo;
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        $this->em   = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(TurnoRepository::class);
        $this->bus  = $this->createMock(MessageBusInterface::class);

        $this->service = new TurnoService($this->em, $this->repo, $this->bus);
    }

    // ─── crear() ─────────────────────────────────────────────────────────────

    public function testCrearSinTrabajadorMarcaDescubierto(): void
    {
        $turno = $this->buildTurno();
        $user  = new User();

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn($m) => new Envelope($m));

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->crear($turno, $user);

        $this->assertSame(EstadoTurno::DESCUBIERTO, $result->getEstado());
    }

    public function testCrearConTrabajadorMarcaCubierto(): void
    {
        $trabajador = new Trabajador();
        $turno = $this->buildTurno($trabajador);
        $user  = new User();

        $this->repo->method('findByTrabajadorYFecha')->willReturn([]);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->crear($turno, $user);

        $this->assertSame(EstadoTurno::CUBIERTO, $result->getEstado());
    }

    // ─── verificarDisponibilidad() ────────────────────────────────────────────

    public function testVerificarDisponibilidadSinConflicto(): void
    {
        $trabajador = new Trabajador();
        $this->repo->method('findByTrabajadorYFecha')->willReturn([]);

        $this->expectNotToPerformAssertions();
        $this->service->verificarDisponibilidad(
            $trabajador,
            new \DateTime('2025-06-10'),
            new \DateTime('08:00'),
            new \DateTime('20:00'),
        );
    }

    public function testVerificarDisponibilidadLanzaExcepcionConSolapamiento(): void
    {
        $trabajador = new Trabajador();

        $existente = $this->buildTurno($trabajador);
        $existente->setEstado(EstadoTurno::CUBIERTO);
        $existente->setHoraInicio(new \DateTime('08:00'));
        $existente->setHoraTermino(new \DateTime('20:00'));

        $this->repo->method('findByTrabajadorYFecha')->willReturn([$existente]);

        $this->expectException(\DomainException::class);
        $this->service->verificarDisponibilidad(
            $trabajador,
            new \DateTime('2025-06-10'),
            new \DateTime('10:00'),
            new \DateTime('14:00'),
        );
    }

    public function testVerificarDisponibilidadIgnoraTurnosDescubiertos(): void
    {
        $trabajador = new Trabajador();

        $descubierto = $this->buildTurno(null);
        $descubierto->setEstado(EstadoTurno::DESCUBIERTO);
        $descubierto->setHoraInicio(new \DateTime('08:00'));
        $descubierto->setHoraTermino(new \DateTime('20:00'));

        $this->repo->method('findByTrabajadorYFecha')->willReturn([$descubierto]);

        $this->expectNotToPerformAssertions();
        $this->service->verificarDisponibilidad(
            $trabajador,
            new \DateTime('2025-06-10'),
            new \DateTime('10:00'),
            new \DateTime('14:00'),
        );
    }

    // ─── asignarReemplazo() ───────────────────────────────────────────────────

    public function testAsignarReemplazoActualizaTurno(): void
    {
        $reemplazo = new Trabajador();
        $turno     = $this->buildTurno();
        $turno->setEstado(EstadoTurno::DESCUBIERTO);

        $this->repo->method('findByTrabajadorYFecha')->willReturn([]);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->asignarReemplazo($turno, $reemplazo, MotivoReemplazo::LICENCIA);

        $this->assertTrue($result->isEsReemplazo());
        $this->assertSame(EstadoTurno::CUBIERTO, $result->getEstado());
        $this->assertSame(MotivoReemplazo::LICENCIA, $result->getMotivoReemplazo());
        $this->assertSame($reemplazo, $result->getTrabajador());
    }

    // ─── registrarAsistencia() ────────────────────────────────────────────────

    public function testRegistrarAsistenciaInicio(): void
    {
        $turno = $this->buildTurno(new Trabajador());
        $turno->setEstado(EstadoTurno::CUBIERTO);

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->registrarAsistencia($turno, 'inicio');

        $this->assertNotNull($result->getRegistroInicio());
        $this->assertSame(EstadoTurno::PARCIAL, $result->getEstado());
    }

    public function testRegistrarAsistenciaTermino(): void
    {
        $turno = $this->buildTurno(new Trabajador());
        $turno->setEstado(EstadoTurno::PARCIAL);
        $turno->setRegistroInicio(new \DateTime());

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->registrarAsistencia($turno, 'termino');

        $this->assertNotNull($result->getRegistroTermino());
        $this->assertSame(EstadoTurno::COMPLETADO, $result->getEstado());
    }

    public function testRegistrarTerminoSinInicioLanzaExcepcion(): void
    {
        $turno = $this->buildTurno(new Trabajador());
        $turno->setEstado(EstadoTurno::CUBIERTO);

        $this->expectException(\LogicException::class);
        $this->service->registrarAsistencia($turno, 'termino');
    }

    // ─── calcularEstadoCobertura() ────────────────────────────────────────────

    public function testCalcularEstadoCoberturaConTurnos(): void
    {
        $paciente = new Paciente();

        $t1 = $this->buildTurno(); $t1->setEstado(EstadoTurno::CUBIERTO);
        $t2 = $this->buildTurno(); $t2->setEstado(EstadoTurno::COMPLETADO);
        $t3 = $this->buildTurno(); $t3->setEstado(EstadoTurno::DESCUBIERTO);
        $t4 = $this->buildTurno(); $t4->setEstado(EstadoTurno::PARCIAL);

        $this->repo->method('findByPacienteYSemana')->willReturn([$t1, $t2, $t3, $t4]);

        $resumen = $this->service->calcularEstadoCobertura($paciente, new \DateTime());

        $this->assertSame(4, $resumen['total']);
        $this->assertSame(1, $resumen['cubiertos']);
        $this->assertSame(1, $resumen['completados']);
        $this->assertSame(1, $resumen['descubiertos']);
        $this->assertSame(1, $resumen['parciales']);
        $this->assertEquals(50, $resumen['porcentaje']);
    }

    public function testCalcularEstadoCoberturaVacio(): void
    {
        $this->repo->method('findByPacienteYSemana')->willReturn([]);

        $resumen = $this->service->calcularEstadoCobertura(new Paciente(), new \DateTime());

        $this->assertSame(0, $resumen['total']);
        $this->assertSame(0, $resumen['porcentaje']);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function buildTurno(?Trabajador $trabajador = null): Turno
    {
        $paciente = new Paciente();
        // Inject minimal data via reflection to avoid requiring full entity setup
        $r = new \ReflectionClass(Paciente::class);
        foreach (['nombres' => 'Test', 'apellidos' => 'Paciente'] as $prop => $val) {
            if ($r->hasProperty($prop)) {
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($paciente, $val);
            }
        }

        $turno = new Turno();
        $turno->setPaciente($paciente)
              ->setTrabajador($trabajador)
              ->setFecha(new \DateTime('2025-06-10'))
              ->setHoraInicio(new \DateTime('08:00'))
              ->setHoraTermino(new \DateTime('20:00'))
              ->setTipoTurno(TipoTurno::T12H_DIA);

        return $turno;
    }
}
