<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Entity\Tenant\Log\LogEntry;
use App\Enum\EstadoEvento;
use App\Enum\GravedadEvento;
use App\Enum\TipoEventoAdverso;
use App\Repository\EventoAdversoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventoAdversoRepository::class)]
#[ORM\Table(name: 'eventos_adversos')]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]
class EventoAdverso
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Paciente::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Paciente $paciente = null;

    #[ORM\ManyToOne(targetEntity: Trabajador::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Trabajador $trabajador = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $fechaEvento = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $horaEvento = null;

    #[ORM\Column(type: 'string', enumType: TipoEventoAdverso::class)]
    #[Gedmo\Versioned]
    private TipoEventoAdverso $tipo = TipoEventoAdverso::OTRO;

    #[ORM\Column(type: 'string', enumType: GravedadEvento::class)]
    #[Gedmo\Versioned]
    private GravedadEvento $gravedad = GravedadEvento::LEVE;

    #[ORM\Column(type: 'string', enumType: EstadoEvento::class)]
    #[Gedmo\Versioned]
    private EstadoEvento $estado = EstadoEvento::ABIERTO;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Gedmo\Versioned]
    private string $descripcion = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Gedmo\Versioned]
    private ?string $accionesTomadas = null;

    #[ORM\Column(type: 'boolean')]
    private bool $notificadoFamilia = false;

    #[ORM\Column(type: 'boolean')]
    private bool $notificadoMedico = false;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaCierre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacionCierre = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $actualizadoEn = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $creadoPor = null;

    #[ORM\OneToMany(targetEntity: SeguimientoEvento::class, mappedBy: 'evento', cascade: ['persist', 'remove'])]
    private Collection $seguimientos;

    public function __construct()
    {
        $this->seguimientos = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getPaciente(): ?Paciente { return $this->paciente; }
    public function setPaciente(?Paciente $p): static { $this->paciente = $p; return $this; }

    public function getTrabajador(): ?Trabajador { return $this->trabajador; }
    public function setTrabajador(?Trabajador $t): static { $this->trabajador = $t; return $this; }

    public function getFechaEvento(): ?\DateTimeInterface { return $this->fechaEvento; }
    public function setFechaEvento(\DateTimeInterface $f): static { $this->fechaEvento = $f; return $this; }

    public function getHoraEvento(): ?\DateTimeInterface { return $this->horaEvento; }
    public function setHoraEvento(?\DateTimeInterface $h): static { $this->horaEvento = $h; return $this; }

    public function getTipo(): TipoEventoAdverso { return $this->tipo; }
    public function setTipo(TipoEventoAdverso $t): static { $this->tipo = $t; return $this; }

    public function getGravedad(): GravedadEvento { return $this->gravedad; }
    public function setGravedad(GravedadEvento $g): static { $this->gravedad = $g; return $this; }

    public function getEstado(): EstadoEvento { return $this->estado; }
    public function setEstado(EstadoEvento $e): static { $this->estado = $e; return $this; }

    public function getDescripcion(): string { return $this->descripcion; }
    public function setDescripcion(string $d): static { $this->descripcion = $d; return $this; }

    public function getAccionesTomadas(): ?string { return $this->accionesTomadas; }
    public function setAccionesTomadas(?string $a): static { $this->accionesTomadas = $a; return $this; }

    public function isNotificadoFamilia(): bool { return $this->notificadoFamilia; }
    public function setNotificadoFamilia(bool $n): static { $this->notificadoFamilia = $n; return $this; }

    public function isNotificadoMedico(): bool { return $this->notificadoMedico; }
    public function setNotificadoMedico(bool $n): static { $this->notificadoMedico = $n; return $this; }

    public function getFechaCierre(): ?\DateTimeInterface { return $this->fechaCierre; }
    public function setFechaCierre(?\DateTimeInterface $f): static { $this->fechaCierre = $f; return $this; }

    public function getObservacionCierre(): ?string { return $this->observacionCierre; }
    public function setObservacionCierre(?string $o): static { $this->observacionCierre = $o; return $this; }

    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }
    public function getActualizadoEn(): ?\DateTimeImmutable { return $this->actualizadoEn; }
    public function getCreadoPor(): ?User { return $this->creadoPor; }
    public function setCreadoPor(?User $u): static { $this->creadoPor = $u; return $this; }

    /** @return Collection<int, SeguimientoEvento> */
    public function getSeguimientos(): Collection { return $this->seguimientos; }
}
