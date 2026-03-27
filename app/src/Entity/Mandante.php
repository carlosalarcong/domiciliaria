<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Log\LogEntry;
use App\Repository\MandanteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MandanteRepository::class)]
#[ORM\Table(name: 'mandantes')]
#[UniqueEntity(fields: ['rut'], message: 'Este RUT ya está registrado.')]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]
class Mandante
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Gedmo\Versioned]
    private ?string $nombre = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Gedmo\Versioned]
    private ?string $rut = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $contacto = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Gedmo\Versioned]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $telefono = null;

    #[ORM\Column(type: 'boolean')]
    private bool $activo = true;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $actualizadoEn = null;

    #[ORM\OneToMany(targetEntity: Paciente::class, mappedBy: 'mandante')]
    private Collection $pacientes;

    public function __construct()
    {
        $this->pacientes = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }
    public function getRut(): ?string { return $this->rut; }
    public function setRut(string $rut): static { $this->rut = $rut; return $this; }
    public function getContacto(): ?string { return $this->contacto; }
    public function setContacto(?string $contacto): static { $this->contacto = $contacto; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function setTelefono(?string $telefono): static { $this->telefono = $telefono; return $this; }
    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): static { $this->activo = $activo; return $this; }
    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }
    public function getActualizadoEn(): ?\DateTimeImmutable { return $this->actualizadoEn; }

    /** @return Collection<int, Paciente> */
    public function getPacientes(): Collection { return $this->pacientes; }

    public function __toString(): string { return $this->nombre ?? ''; }
}
