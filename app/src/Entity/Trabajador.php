<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Log\LogEntry;
use App\Enum\EstadoTrabajador;
use App\Enum\PerfilTrabajador;
use App\Repository\TrabajadorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TrabajadorRepository::class)]
#[ORM\Table(name: 'trabajadores')]
#[UniqueEntity(fields: ['rut'], message: 'Este RUT ya está registrado.')]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]
class Trabajador
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Gedmo\Versioned]
    private ?string $nombres = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Gedmo\Versioned]
    private ?string $apellidos = null;

    #[ORM\Column(length: 15, unique: true)]
    #[Assert\NotBlank]
    #[Gedmo\Versioned]
    private ?string $rut = null;

    #[ORM\Column(type: 'string', enumType: PerfilTrabajador::class)]
    #[Gedmo\Versioned]
    private PerfilTrabajador $perfil = PerfilTrabajador::TENS;

    #[ORM\Column(length: 20, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $telefono = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Gedmo\Versioned]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $direccion = null;

    #[ORM\Column(type: 'string', enumType: EstadoTrabajador::class)]
    #[Gedmo\Versioned]
    private EstadoTrabajador $estado = EstadoTrabajador::ACTIVO;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaIngreso = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaSalida = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $actualizadoEn = null;

    #[ORM\OneToMany(targetEntity: Turno::class, mappedBy: 'trabajador')]
    private Collection $turnos;

    #[ORM\OneToMany(targetEntity: DisponibilidadTrabajador::class, mappedBy: 'trabajador', cascade: ['remove'])]
    private Collection $disponibilidades;

    public function __construct()
    {
        $this->turnos          = new ArrayCollection();
        $this->disponibilidades = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }
    public function getNombres(): ?string { return $this->nombres; }
    public function setNombres(string $n): static { $this->nombres = $n; return $this; }
    public function getApellidos(): ?string { return $this->apellidos; }
    public function setApellidos(string $a): static { $this->apellidos = $a; return $this; }
    public function getNombreCompleto(): string { return trim($this->nombres . ' ' . $this->apellidos); }
    public function getRut(): ?string { return $this->rut; }
    public function setRut(string $r): static { $this->rut = $r; return $this; }
    public function getPerfil(): PerfilTrabajador { return $this->perfil; }
    public function setPerfil(PerfilTrabajador $p): static { $this->perfil = $p; return $this; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function setTelefono(?string $t): static { $this->telefono = $t; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $e): static { $this->email = $e; return $this; }
    public function getDireccion(): ?string { return $this->direccion; }
    public function setDireccion(?string $d): static { $this->direccion = $d; return $this; }
    public function getEstado(): EstadoTrabajador { return $this->estado; }
    public function setEstado(EstadoTrabajador $e): static { $this->estado = $e; return $this; }
    public function getFechaIngreso(): ?\DateTimeInterface { return $this->fechaIngreso; }
    public function setFechaIngreso(?\DateTimeInterface $d): static { $this->fechaIngreso = $d; return $this; }
    public function getFechaSalida(): ?\DateTimeInterface { return $this->fechaSalida; }
    public function setFechaSalida(?\DateTimeInterface $d): static { $this->fechaSalida = $d; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $u): static { $this->user = $u; return $this; }
    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }
    public function getActualizadoEn(): ?\DateTimeImmutable { return $this->actualizadoEn; }
    /** @return Collection<int, Turno> */
    public function getTurnos(): Collection { return $this->turnos; }
    /** @return Collection<int, DisponibilidadTrabajador> */
    public function getDisponibilidades(): Collection { return $this->disponibilidades; }
    public function __toString(): string { return $this->getNombreCompleto(); }
}
