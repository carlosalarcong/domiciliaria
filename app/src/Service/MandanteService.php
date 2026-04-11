<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\Mandante;
use App\Enum\EstadoPaciente;
use App\Repository\MandanteRepository;
use App\Repository\PacienteRepository;
use Doctrine\ORM\EntityManagerInterface;

class MandanteService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MandanteRepository $mandanteRepository,
        private readonly PacienteRepository $pacienteRepository,
    ) {}

    public function crear(Mandante $mandante): Mandante
    {
        $this->em->persist($mandante);
        $this->em->flush();
        return $mandante;
    }

    public function actualizar(Mandante $mandante): Mandante
    {
        $this->em->flush();
        return $mandante;
    }

    public function toggleActivo(Mandante $mandante): Mandante
    {
        if ($mandante->isActivo()) {
            $pacientesActivos = array_filter(
                $this->pacienteRepository->findByMandante($mandante),
                fn(\App\Entity\Tenant\Paciente $p) => $p->getEstado() === EstadoPaciente::ACTIVO,
            );

            if (count($pacientesActivos) > 0) {
                throw new \DomainException(sprintf(
                    'No se puede desactivar el mandante "%s": tiene %d paciente(s) activo(s). ' .
                    'Da de baja o suspende a los pacientes antes de desactivar el mandante.',
                    $mandante->getNombre(),
                    count($pacientesActivos),
                ));
            }
        }

        $mandante->setActivo(!$mandante->isActivo());
        $this->em->flush();
        return $mandante;
    }
}
