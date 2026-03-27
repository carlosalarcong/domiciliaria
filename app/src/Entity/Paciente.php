<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Log\LogEntry;
use App\Enum\EstadoPaciente;
use App\Enum\TipoServicio;
use App\Repository\PacienteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PacienteRepository::class)]
#[ORM\Table(name: 'pacientes')]
#[UniqueEntity(fields: ['rut'], message: 'Este RUT ya está registrado.')]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]
class Paciente
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

    #[ORM\Column(type: 'date', nullable: true)]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $fechaNacimiento = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $direccion = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $comuna = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $region = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $telefono = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $telefonoEmergencia = null;

    #[ORM\ManyToOne(targetEntity: Mandante::class, inversedBy: 'pacientes')]
    #[ORM\JoinColumn(nullable: true)]
    #[Gedmo\Versioned]
    private ?Mandante $mandante = null;

    #[ORM\Column(type: 'string', enumType: TipoServicio::class)]
    #[Gedmo\Versioned]
    private TipoServicio $tipoServicio = TipoServicio::TURNO_12H;

    #[ORM\Column(type: 'string', enumType: EstadoPaciente::class)]
    #[Gedmo\Versioned]
    private EstadoPaciente $estado = EstadoPaciente::ACTIVO;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $fechaIngreso = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $fechaTermino = null;

    // Datos del tutor/responsable
    #[ORM\Column(length: 200, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $tutorNombre = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $tutorTelefono = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $tutorRelacion = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Gedmo\Versioned]
    private ?string $observaciones = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $actualizadoEn = null;

    #[ORM\OneToOne(targetEntity: CondicionDomicilio::class, mappedBy: 'paciente', cascade: ['persist', 'remove'])]
    private ?CondicionDomicilio $condicionDomicilio = null;

    #[ORM\OneToMany(targetEntity: BitacoraOperativa::class, mappedBy: 'paciente', cascade: ['remove'])]
    #[ORM\OrderBy(['fecha' => 'DESC'])]
    private Collection $bitacoras;

    #[ORM\OneToMany(targetEntity: HistorialComunicacion::class, mappedBy: 'paciente', cascade: ['remove'])]
    #[ORM\OrderBy(['creadoEn' => 'DESC'])]
    private Collection $comunicaciones;

    public function __construct()
    {
        $this->bitacoras     = new ArrayCollection();
        $this->comunicaciones = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getNombres(): ?string { return $this->nombres; }
    public function setNombres(string $nombres): static { $this->nombres = $nombres; return $this; }

    public function getApellidos(): ?string { return $this->apellidos; }
    public function setApellidos(string $apellidos): static { $this->apellidos = $apellidos; return $this; }

    public function getNombreCompleto(): string { return trim($this->nombres . ' ' . $this->apellidos); }

    public function getRut(): ?string { return $this->rut; }
    public function setRut(string $rut): static { $this->rut = $rut; return $this; }

    public function getFechaNacimiento(): ?\DateTimeInterface { return $this->fechaNacimiento; }
    public function setFechaNacimiento(?\DateTimeInterface $d): static { $this->fechaNacimiento = $d; return $this; }

    public function getEdad(): ?int
    {
        if ($this->fechaNacimiento === null) return null;
        return (new \DateTime())->diff($this->fechaNacimiento)->y;
    }

    public function getDireccion(): ?string { return $this->direccion; }
    public function setDireccion(?string $d): static { $this->direccion = $d; return $this; }

    public function getComuna(): ?string { return $this->comuna; }
    public function setComuna(?string $c): static { $this->comuna = $c; return $this; }

    public function getRegion(): ?string { return $this->region; }
    public function setRegion(?string $r): static { $this->region = $r; return $this; }

    public function getTelefono(): ?string { return $this->telefono; }
    public function setTelefono(?string $t): static { $this->telefono = $t; return $this; }

    public function getTelefonoEmergencia(): ?string { return $this->telefonoEmergencia; }
    public function setTelefonoEmergencia(?string $t): static { $this->telefonoEmergencia = $t; return $this; }

    public function getMandante(): ?Mandante { return $this->mandante; }
    public function setMandante(?Mandante $m): static { $this->mandante = $m; return $this; }

    public function getTipoServicio(): TipoServicio { return $this->tipoServicio; }
    public function setTipoServicio(TipoServicio $t): static { $this->tipoServicio = $t; return $this; }

    public function getEstado(): EstadoPaciente { return $this->estado; }
    public function setEstado(EstadoPaciente $e): static { $this->estado = $e; return $this; }

    public function getFechaIngreso(): ?\DateTimeInterface { return $this->fechaIngreso; }
    public function setFechaIngreso(?\DateTimeInterface $d): static { $this->fechaIngreso = $d; return $this; }

    public function getFechaTermino(): ?\DateTimeInterface { return $this->fechaTermino; }
    public function setFechaTermino(?\DateTimeInterface $d): static { $this->fechaTermino = $d; return $this; }

    public function getTutorNombre(): ?string { return $this->tutorNombre; }
    public function setTutorNombre(?string $t): static { $this->tutorNombre = $t; return $this; }

    public function getTutorTelefono(): ?string { return $this->tutorTelefono; }
    public function setTutorTelefono(?string $t): static { $this->tutorTelefono = $t; return $this; }

    public function getTutorRelacion(): ?string { return $this->tutorRelacion; }
    public function setTutorRelacion(?string $t): static { $this->tutorRelacion = $t; return $this; }

    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $o): static { $this->observaciones = $o; return $this; }

    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }
    public function getActualizadoEn(): ?\DateTimeImmutable { return $this->actualizadoEn; }

    public function getCondicionDomicilio(): ?CondicionDomicilio { return $this->condicionDomicilio; }
    public function setCondicionDomicilio(?CondicionDomicilio $c): static
    {
        if ($c !== null) $c->setPaciente($this);
        $this->condicionDomicilio = $c;
        return $this;
    }

    /** @return Collection<int, BitacoraOperativa> */
    public function getBitacoras(): Collection { return $this->bitacoras; }

    /** @return Collection<int, HistorialComunicacion> */
    public function getComunicaciones(): Collection { return $this->comunicaciones; }
}
