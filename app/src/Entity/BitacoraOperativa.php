<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TipoBitacora;
use App\Repository\BitacoraOperativaRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: BitacoraOperativaRepository::class)]
#[ORM\Table(name: 'bitacora_operativa')]
class BitacoraOperativa
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Paciente::class, inversedBy: 'bitacoras')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Paciente $paciente = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $fecha = null;

    #[ORM\Column(type: 'string', enumType: TipoBitacora::class)]
    private TipoBitacora $tipo = TipoBitacora::NOVEDAD;

    #[ORM\Column(type: 'text')]
    private ?string $descripcion = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creadoPor = null;

    public function getId(): ?int { return $this->id; }
    public function getPaciente(): ?Paciente { return $this->paciente; }
    public function setPaciente(?Paciente $p): static { $this->paciente = $p; return $this; }
    public function getFecha(): ?\DateTimeImmutable { return $this->fecha; }
    public function getTipo(): TipoBitacora { return $this->tipo; }
    public function setTipo(TipoBitacora $t): static { $this->tipo = $t; return $this; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(string $d): static { $this->descripcion = $d; return $this; }
    public function getCreadoPor(): ?User { return $this->creadoPor; }
    public function setCreadoPor(?User $u): static { $this->creadoPor = $u; return $this; }
}
