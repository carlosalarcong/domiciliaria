<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\EventoAdverso;
use App\Entity\Paciente;
use App\Entity\User;
use App\Enum\EstadoEvento;
use App\Enum\GravedadEvento;
use App\Enum\TipoEventoAdverso;
use App\Message\EventoAdversoGraveMessage;
use App\Service\EventoAdversoService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class EventoAdversoServiceTest extends TestCase
{
    private EventoAdversoService $service;
    private EntityManagerInterface $em;
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        $this->em  = $this->createMock(EntityManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->service = new EventoAdversoService($this->em, $this->bus);
    }

    // ─── registrar ────────────────────────────────────────────────────────────

    public function testRegistrarMarcaEstadoAbierto(): void
    {
        $evento = $this->buildEvento(GravedadEvento::LEVE);
        $user   = new User();

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->bus->expects($this->never())->method('dispatch');

        $result = $this->service->registrar($evento, $user);

        $this->assertSame(EstadoEvento::ABIERTO, $result->getEstado());
        $this->assertSame($user, $result->getCreadoPor());
    }

    public function testRegistrarEventoGraveDespachaAlerta(): void
    {
        $evento = $this->buildEvento(GravedadEvento::GRAVE);

        $this->em->method('persist');
        $this->em->method('flush');
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(EventoAdversoGraveMessage::class))
            ->willReturnCallback(fn($m) => new Envelope($m));

        $this->service->registrar($evento, new User());
    }

    public function testRegistrarEventoCriticoDespachaAlerta(): void
    {
        $evento = $this->buildEvento(GravedadEvento::CRITICO);

        $this->em->method('persist');
        $this->em->method('flush');
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn($m) => new Envelope($m));

        $this->service->registrar($evento, new User());
    }

    public function testRegistrarEventoModeradoNoDespachaAlerta(): void
    {
        $evento = $this->buildEvento(GravedadEvento::MODERADO);

        $this->em->method('persist');
        $this->em->method('flush');
        $this->bus->expects($this->never())->method('dispatch');

        $this->service->registrar($evento, new User());
    }

    // ─── agregarSeguimiento ───────────────────────────────────────────────────

    public function testAgregarSeguimientoCambiaEstadoAEnProceso(): void
    {
        $evento = $this->buildEvento(GravedadEvento::LEVE);
        $evento->setEstado(EstadoEvento::ABIERTO);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $seg = $this->service->agregarSeguimiento($evento, 'Se tomaron medidas.', new User());

        $this->assertSame('Se tomaron medidas.', $seg->getNota());
        $this->assertSame(EstadoEvento::EN_PROCESO, $evento->getEstado());
    }

    public function testAgregarSeguimientoNoModificaEventoCerrado(): void
    {
        $evento = $this->buildEvento(GravedadEvento::LEVE);
        $evento->setEstado(EstadoEvento::CERRADO);

        $this->em->method('persist');
        $this->em->method('flush');

        $this->service->agregarSeguimiento($evento, 'Nota post-cierre.', new User());

        $this->assertSame(EstadoEvento::CERRADO, $evento->getEstado());
    }

    // ─── cerrar ───────────────────────────────────────────────────────────────

    public function testCerrarEstableceFechaCierreYEstado(): void
    {
        $evento = $this->buildEvento(GravedadEvento::LEVE);
        $evento->setEstado(EstadoEvento::EN_PROCESO);

        $this->em->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->cerrar($evento, 'Resuelto sin secuelas.', new User());

        $this->assertSame(EstadoEvento::CERRADO, $result->getEstado());
        $this->assertNotNull($result->getFechaCierre());
        $this->assertSame('Resuelto sin secuelas.', $result->getObservacionCierre());
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function buildEvento(GravedadEvento $gravedad): EventoAdverso
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

        $evento = new EventoAdverso();
        $evento->setPaciente($paciente)
               ->setTipo(TipoEventoAdverso::CAIDA)
               ->setGravedad($gravedad)
               ->setFechaEvento(new \DateTime('2025-06-10'))
               ->setDescripcion('Descripción de prueba del evento.');

        return $evento;
    }
}
