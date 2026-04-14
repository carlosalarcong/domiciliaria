<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\User;
use App\Enum\EstadoPaciente;
use App\Enum\TipoBitacora;
use App\Repository\Tenant\PacienteRepository;
use App\Repository\Tenant\TurnoRepository;
use App\Service\PacienteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class PacienteServiceTest extends TestCase
{
    private PacienteService $service;
    private EntityManagerInterface $em;

    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $repo          = $this->createMock(PacienteRepository::class);
        $turnoRepo     = $this->createMock(TurnoRepository::class);
        $this->bus     = $this->createMock(MessageBusInterface::class);
        $this->bus->method('dispatch')
            ->willReturnCallback(fn($m) => new \Symfony\Component\Messenger\Envelope($m));
        $this->service = new PacienteService($this->em, $repo, $turnoRepo, $this->bus);
    }

    public function testRegistrarPersistePaciente(): void
    {
        $paciente = new Paciente();
        $paciente->setNombres('Juan')->setApellidos('Pérez')->setRut('12.345.678-9');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->registrar($paciente);

        $this->assertSame($paciente, $result);
        $this->assertNotNull($result->getCondicionDomicilio(), 'Debe inicializar CondicionDomicilio automáticamente');
    }

    public function testActualizarEstadoADadoDeBaja(): void
    {
        $paciente = new Paciente();
        $paciente->setEstado(EstadoPaciente::ACTIVO);
        $user = new User();
        $user->setEmail('a@b.cl')->setNombre('A')->setApellido('B');

        $this->em->expects($this->once())->method('flush');

        $this->service->actualizarEstado($paciente, EstadoPaciente::DADO_DE_BAJA, $user);

        $this->assertSame(EstadoPaciente::DADO_DE_BAJA, $paciente->getEstado());
        $this->assertNotNull($paciente->getFechaTermino(), 'Debe registrar fecha de término automáticamente');
    }

    public function testActualizarEstadoMismoEstadoNoHaceNada(): void
    {
        $paciente = new Paciente();
        $paciente->setEstado(EstadoPaciente::ACTIVO);
        $user = new User();
        $user->setEmail('a@b.cl')->setNombre('A')->setApellido('B');

        $this->em->expects($this->never())->method('flush');

        $this->service->actualizarEstado($paciente, EstadoPaciente::ACTIVO, $user);

        $this->assertSame(EstadoPaciente::ACTIVO, $paciente->getEstado());
    }

    public function testDarDeBajaRegistraBitacoraYCambaEstado(): void
    {
        $paciente = new Paciente();
        $paciente->setEstado(EstadoPaciente::ACTIVO);

        $user = new User();
        $user->setEmail('admin@test.cl')->setNombre('Admin')->setApellido('Test');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->darDeBaja($paciente, $user, 'Alta médica');

        $this->assertSame(EstadoPaciente::DADO_DE_BAJA, $paciente->getEstado());
        $this->assertNotNull($paciente->getFechaTermino());
    }

    public function testDarDeBajaYaEnBajaLanzaExcepcion(): void
    {
        $paciente = new Paciente();
        $paciente->setEstado(EstadoPaciente::DADO_DE_BAJA);

        $user = new User();
        $user->setEmail('a@b.cl')->setNombre('A')->setApellido('B');

        $this->expectException(\LogicException::class);

        $this->service->darDeBaja($paciente, $user, 'Motivo');
    }

    public function testAgregarBitacoraPersiste(): void
    {
        $paciente = new Paciente();
        $user = new User();
        $user->setEmail('a@b.cl')->setNombre('A')->setApellido('B');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $entrada = $this->service->agregarBitacora($paciente, TipoBitacora::NOVEDAD, 'Prueba', $user);

        $this->assertSame('Prueba', $entrada->getDescripcion());
        $this->assertSame(TipoBitacora::NOVEDAD, $entrada->getTipo());
    }
}
