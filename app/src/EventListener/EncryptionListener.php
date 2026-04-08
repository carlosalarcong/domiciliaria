<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Tenant\EventoAdverso;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Service\EncryptionService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Cifra campos sensibles antes de persistir y los descifra tras cargar.
 * Usa el prefijo "enc:" para distinguir valores cifrados de los que no lo están.
 */
#[AsDoctrineListener(event: Events::prePersist, connection: 'tenant')]
#[AsDoctrineListener(event: Events::preUpdate, connection: 'tenant')]
#[AsDoctrineListener(event: Events::postLoad, connection: 'tenant')]
class EncryptionListener
{
    public function __construct(private readonly EncryptionService $encryption) {}

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->encryptEntity($args->getObject());
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->encryptEntity($args->getObject());
    }

    public function postLoad(LifecycleEventArgs $args): void
    {
        $this->decryptEntity($args->getObject());
    }

    private function encryptEntity(object $entity): void
    {
        if ($entity instanceof Paciente) {
            if ($entity->getRut() !== null) {
                $plain = $this->encryption->isEncrypted($entity->getRut())
                    ? $this->encryption->decrypt($entity->getRut())
                    : $entity->getRut();
                $entity->setRutHash($this->encryption->hmac($plain));
                $entity->setRut($this->encryption->encrypt($plain));
            }
            if ($entity->getDiagnosticos() !== null) {
                $entity->setDiagnosticos($this->encryption->encrypt($entity->getDiagnosticos()));
            }
            if ($entity->getMedicamentos() !== null) {
                $entity->setMedicamentos($this->encryption->encrypt($entity->getMedicamentos()));
            }
            if ($entity->getObservaciones() !== null) {
                $entity->setObservaciones($this->encryption->encrypt($entity->getObservaciones()));
            }
        }

        if ($entity instanceof Trabajador) {
            if ($entity->getRut() !== null) {
                $plain = $this->encryption->isEncrypted($entity->getRut())
                    ? $this->encryption->decrypt($entity->getRut())
                    : $entity->getRut();
                $entity->setRutHash($this->encryption->hmac($plain));
                $entity->setRut($this->encryption->encrypt($plain));
            }
            if ($entity->getCuentaBancaria() !== null) {
                $entity->setCuentaBancaria($this->encryption->encrypt($entity->getCuentaBancaria()));
            }
            if ($entity->getDatosPrevisionales() !== null) {
                $entity->setDatosPrevisionales($this->encryption->encrypt($entity->getDatosPrevisionales()));
            }
        }

        if ($entity instanceof EventoAdverso) {
            if ($entity->getDescripcion() !== '') {
                $entity->setDescripcion($this->encryption->encrypt($entity->getDescripcion()));
            }
            if ($entity->getAccionesTomadas() !== null) {
                $entity->setAccionesTomadas($this->encryption->encrypt($entity->getAccionesTomadas()));
            }
        }
    }

    private function decryptEntity(object $entity): void
    {
        if ($entity instanceof Paciente) {
            if ($entity->getRut() !== null) {
                $entity->setRut($this->encryption->decrypt($entity->getRut()));
            }
            if ($entity->getDiagnosticos() !== null) {
                $entity->setDiagnosticos($this->encryption->decrypt($entity->getDiagnosticos()));
            }
            if ($entity->getMedicamentos() !== null) {
                $entity->setMedicamentos($this->encryption->decrypt($entity->getMedicamentos()));
            }
            if ($entity->getObservaciones() !== null) {
                $entity->setObservaciones($this->encryption->decrypt($entity->getObservaciones()));
            }
        }

        if ($entity instanceof Trabajador) {
            if ($entity->getRut() !== null) {
                $entity->setRut($this->encryption->decrypt($entity->getRut()));
            }
            if ($entity->getCuentaBancaria() !== null) {
                $entity->setCuentaBancaria($this->encryption->decrypt($entity->getCuentaBancaria()));
            }
            if ($entity->getDatosPrevisionales() !== null) {
                $entity->setDatosPrevisionales($this->encryption->decrypt($entity->getDatosPrevisionales()));
            }
        }

        if ($entity instanceof EventoAdverso) {
            if ($entity->getDescripcion() !== '') {
                $entity->setDescripcion($this->encryption->decrypt($entity->getDescripcion()));
            }
            if ($entity->getAccionesTomadas() !== null) {
                $entity->setAccionesTomadas($this->encryption->decrypt($entity->getAccionesTomadas()));
            }
        }
    }
}
