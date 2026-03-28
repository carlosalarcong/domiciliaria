<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Repository\SeguimientoEventoRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: SeguimientoEventoRepository::class)]
#[ORM\Table(name: 'seguimientos_evento')]
class SeguimientoEvento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EventoAdverso::class, inversedBy: 'seguimientos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EventoAdverso $evento = null;

    #[ORM\Column(type: 'text')]
    private string $nota = '';

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $creadoPor = null;

    public function getId(): ?int { return $this->id; }

    public function getEvento(): ?EventoAdverso { return $this->evento; }
    public function setEvento(?EventoAdverso $e): static { $this->evento = $e; return $this; }

    public function getNota(): string { return $this->nota; }
    public function setNota(string $n): static { $this->nota = $n; return $this; }

    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }

    public function getCreadoPor(): ?User { return $this->creadoPor; }
    public function setCreadoPor(?User $u): static { $this->creadoPor = $u; return $this; }
}
