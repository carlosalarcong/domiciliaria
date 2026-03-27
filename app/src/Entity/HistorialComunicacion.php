<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TipoComunicacion;
use App\Repository\HistorialComunicacionRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: HistorialComunicacionRepository::class)]
#[ORM\Table(name: 'historial_comunicacion')]
class HistorialComunicacion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Paciente::class, inversedBy: 'comunicaciones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Paciente $paciente = null;

    #[ORM\Column(type: 'string', enumType: TipoComunicacion::class)]
    private TipoComunicacion $tipo = TipoComunicacion::FAMILIA;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $contacto = null;

    #[ORM\Column(type: 'text')]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creadoPor = null;

    public function getId(): ?int { return $this->id; }
    public function getPaciente(): ?Paciente { return $this->paciente; }
    public function setPaciente(?Paciente $p): static { $this->paciente = $p; return $this; }
    public function getTipo(): TipoComunicacion { return $this->tipo; }
    public function setTipo(TipoComunicacion $t): static { $this->tipo = $t; return $this; }
    public function getContacto(): ?string { return $this->contacto; }
    public function setContacto(?string $c): static { $this->contacto = $c; return $this; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(string $d): static { $this->descripcion = $d; return $this; }
    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }
    public function getCreadoPor(): ?User { return $this->creadoPor; }
    public function setCreadoPor(?User $u): static { $this->creadoPor = $u; return $this; }
}
