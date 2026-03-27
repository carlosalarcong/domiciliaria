<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DisponibilidadTrabajadorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DisponibilidadTrabajadorRepository::class)]
#[ORM\Table(name: 'disponibilidad_trabajador')]
#[ORM\UniqueConstraint(columns: ['trabajador_id', 'fecha'])]
class DisponibilidadTrabajador
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Trabajador::class, inversedBy: 'disponibilidades')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Trabajador $trabajador = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $fecha = null;

    /** Hora de inicio de disponibilidad (ej: 08:00) */
    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $horaDesde = null;

    /** Hora de término de disponibilidad (ej: 20:00) */
    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $horaHasta = null;

    #[ORM\Column(type: 'boolean')]
    private bool $disponible = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observacion = null;

    public function getId(): ?int { return $this->id; }
    public function getTrabajador(): ?Trabajador { return $this->trabajador; }
    public function setTrabajador(?Trabajador $t): static { $this->trabajador = $t; return $this; }
    public function getFecha(): ?\DateTimeInterface { return $this->fecha; }
    public function setFecha(\DateTimeInterface $f): static { $this->fecha = $f; return $this; }
    public function getHoraDesde(): ?\DateTimeInterface { return $this->horaDesde; }
    public function setHoraDesde(?\DateTimeInterface $h): static { $this->horaDesde = $h; return $this; }
    public function getHoraHasta(): ?\DateTimeInterface { return $this->horaHasta; }
    public function setHoraHasta(?\DateTimeInterface $h): static { $this->horaHasta = $h; return $this; }
    public function isDisponible(): bool { return $this->disponible; }
    public function setDisponible(bool $d): static { $this->disponible = $d; return $this; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function setObservacion(?string $o): static { $this->observacion = $o; return $this; }
}
