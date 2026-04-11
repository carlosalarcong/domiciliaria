<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Entity\Tenant\Log\LogEntry;
use App\Enum\EstadoTurno;
use App\Enum\MotivoReemplazo;
use App\Enum\TipoTurno;
use App\Repository\TurnoRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TurnoRepository::class)]
#[ORM\Table(name: 'turnos')]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]
#[Assert\Expression(
    expression: 'this.getHoraTermino() === null or this.getHoraInicio() === null or this.getHoraTermino() > this.getHoraInicio()',
    message: 'La hora de término debe ser posterior a la hora de inicio.',
)]
class Turno
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

    #[ORM\ManyToOne(targetEntity: Trabajador::class, inversedBy: 'turnos')]
    #[ORM\JoinColumn(nullable: true)]
    #[Gedmo\Versioned]
    private ?Trabajador $trabajador = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $fecha = null;

    #[ORM\Column(type: 'time')]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $horaInicio = null;

    #[ORM\Column(type: 'time')]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $horaTermino = null;

    #[ORM\Column(type: 'string', enumType: TipoTurno::class)]
    #[Gedmo\Versioned]
    private TipoTurno $tipoTurno = TipoTurno::T12H_DIA;

    #[ORM\Column(type: 'string', enumType: EstadoTurno::class)]
    #[Gedmo\Versioned]
    private EstadoTurno $estado = EstadoTurno::DESCUBIERTO;

    #[ORM\Column(type: 'boolean')]
    private bool $esReemplazo = false;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Turno $turnoOriginal = null;

    #[ORM\Column(type: 'string', enumType: MotivoReemplazo::class, nullable: true)]
    #[Gedmo\Versioned]
    private ?MotivoReemplazo $motivoReemplazo = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $registroInicio = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $registroTermino = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ultimaAlertaDescubiertoEn = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Gedmo\Versioned]
    private ?string $observaciones = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $actualizadoEn = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $creadoPor = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getPaciente(): ?Paciente { return $this->paciente; }
    public function setPaciente(?Paciente $p): static { $this->paciente = $p; return $this; }

    public function getTrabajador(): ?Trabajador { return $this->trabajador; }
    public function setTrabajador(?Trabajador $t): static { $this->trabajador = $t; return $this; }

    public function getFecha(): ?\DateTimeInterface { return $this->fecha; }
    public function setFecha(\DateTimeInterface $f): static { $this->fecha = $f; return $this; }

    public function getHoraInicio(): ?\DateTimeInterface { return $this->horaInicio; }
    public function setHoraInicio(\DateTimeInterface $h): static { $this->horaInicio = $h; return $this; }

    public function getHoraTermino(): ?\DateTimeInterface { return $this->horaTermino; }
    public function setHoraTermino(\DateTimeInterface $h): static { $this->horaTermino = $h; return $this; }

    public function getTipoTurno(): TipoTurno { return $this->tipoTurno; }
    public function setTipoTurno(TipoTurno $t): static { $this->tipoTurno = $t; return $this; }

    public function getEstado(): EstadoTurno { return $this->estado; }
    public function setEstado(EstadoTurno $e): static { $this->estado = $e; return $this; }

    public function isEsReemplazo(): bool { return $this->esReemplazo; }
    public function setEsReemplazo(bool $r): static { $this->esReemplazo = $r; return $this; }

    public function getTurnoOriginal(): ?Turno { return $this->turnoOriginal; }
    public function setTurnoOriginal(?Turno $t): static { $this->turnoOriginal = $t; return $this; }

    public function getMotivoReemplazo(): ?MotivoReemplazo { return $this->motivoReemplazo; }
    public function setMotivoReemplazo(?MotivoReemplazo $m): static { $this->motivoReemplazo = $m; return $this; }

    public function getRegistroInicio(): ?\DateTimeInterface { return $this->registroInicio; }
    public function setRegistroInicio(?\DateTimeInterface $r): static { $this->registroInicio = $r; return $this; }

    public function getRegistroTermino(): ?\DateTimeInterface { return $this->registroTermino; }
    public function setRegistroTermino(?\DateTimeInterface $r): static { $this->registroTermino = $r; return $this; }

    public function getUltimaAlertaDescubiertoEn(): ?\DateTimeImmutable { return $this->ultimaAlertaDescubiertoEn; }
    public function setUltimaAlertaDescubiertoEn(?\DateTimeImmutable $dt): static { $this->ultimaAlertaDescubiertoEn = $dt; return $this; }

    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $o): static { $this->observaciones = $o; return $this; }

    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }
    public function getActualizadoEn(): ?\DateTimeImmutable { return $this->actualizadoEn; }
    public function getCreadoPor(): ?User { return $this->creadoPor; }
    public function setCreadoPor(?User $u): static { $this->creadoPor = $u; return $this; }

    public function getTituloCalendario(): string
    {
        $paciente = $this->paciente?->getNombreCompleto() ?? 'Sin paciente';
        $trabajador = $this->trabajador?->getNombreCompleto() ?? 'Sin trabajador';
        return "{$paciente} — {$trabajador}";
    }

    public function estaEnProceso(): bool
    {
        return $this->registroInicio !== null && $this->registroTermino === null;
    }
}
