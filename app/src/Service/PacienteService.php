<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\BitacoraOperativa;
use App\Entity\Tenant\CondicionDomicilio;
use App\Entity\Tenant\HistorialComunicacion;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\User;
use App\Enum\EstadoPaciente;
use App\Enum\TipoBitacora;
use App\Enum\TipoComunicacion;
use App\Repository\PacienteRepository;
use Doctrine\ORM\EntityManagerInterface;

class PacienteService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PacienteRepository $pacienteRepository,
    ) {}

    public function registrar(Paciente $paciente): Paciente
    {
        // Inicializar condición de domicilio vacía
        if ($paciente->getCondicionDomicilio() === null) {
            $condicion = new CondicionDomicilio();
            $paciente->setCondicionDomicilio($condicion);
        }

        $this->em->persist($paciente);
        $this->em->flush();

        return $paciente;
    }

    public function actualizar(Paciente $paciente): Paciente
    {
        $this->em->flush();
        return $paciente;
    }

    public function actualizarCondicion(Paciente $paciente, CondicionDomicilio $condicion): void
    {
        $this->em->flush();
    }

    public function actualizarEstado(Paciente $paciente, EstadoPaciente $nuevoEstado): Paciente
    {
        $estadoAnterior = $paciente->getEstado();

        if ($estadoAnterior === $nuevoEstado) {
            return $paciente;
        }

        // Al dar de baja, se debe registrar fecha de término
        if ($nuevoEstado === EstadoPaciente::DADO_DE_BAJA && $paciente->getFechaTermino() === null) {
            $paciente->setFechaTermino(new \DateTime());
        }

        $paciente->setEstado($nuevoEstado);
        $this->em->flush();

        return $paciente;
    }

    public function darDeBaja(Paciente $paciente, User $usuario, string $motivo): Paciente
    {
        if ($paciente->getEstado() === EstadoPaciente::DADO_DE_BAJA) {
            throw new \LogicException('El paciente ya está dado de baja.');
        }

        $paciente->setEstado(EstadoPaciente::DADO_DE_BAJA);
        $paciente->setFechaTermino(new \DateTime());

        // Registrar en bitácora
        $entrada = new BitacoraOperativa();
        $entrada->setPaciente($paciente);
        $entrada->setTipo(TipoBitacora::NOVEDAD);
        $entrada->setDescripcion('Paciente dado de baja. Motivo: ' . $motivo);
        $entrada->setCreadoPor($usuario);

        $this->em->persist($entrada);
        $this->em->flush();

        return $paciente;
    }

    public function agregarBitacora(Paciente $paciente, TipoBitacora $tipo, string $descripcion, User $usuario): BitacoraOperativa
    {
        $entrada = new BitacoraOperativa();
        $entrada->setPaciente($paciente);
        $entrada->setTipo($tipo);
        $entrada->setDescripcion($descripcion);
        $entrada->setCreadoPor($usuario);

        $this->em->persist($entrada);
        $this->em->flush();

        return $entrada;
    }

    public function agregarComunicacion(Paciente $paciente, TipoComunicacion $tipo, string $descripcion, User $usuario, ?string $contacto = null): HistorialComunicacion
    {
        $historial = new HistorialComunicacion();
        $historial->setPaciente($paciente);
        $historial->setTipo($tipo);
        $historial->setDescripcion($descripcion);
        $historial->setContacto($contacto);
        $historial->setCreadoPor($usuario);

        $this->em->persist($historial);
        $this->em->flush();

        return $historial;
    }
}
