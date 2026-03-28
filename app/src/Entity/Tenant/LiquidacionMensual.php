<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Enum\EstadoLiquidacion;
use App\Repository\LiquidacionMensualRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: LiquidacionMensualRepository::class)]
#[ORM\Table(name: 'liquidaciones_mensuales')]
#[ORM\UniqueConstraint(columns: ['trabajador_id', 'anio', 'mes'])]
class LiquidacionMensual
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Trabajador::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trabajador $trabajador = null;

    #[ORM\Column(type: 'smallint')]
    private int $anio;

    #[ORM\Column(type: 'smallint')]
    private int $mes;

    #[ORM\Column(type: 'integer')]
    private int $totalTurnos = 0;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $totalHoras = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $montoTotal = '0.00';

    #[ORM\Column(type: 'string', enumType: EstadoLiquidacion::class)]
    private EstadoLiquidacion $estado = EstadoLiquidacion::BORRADOR;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observaciones = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaPago = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $creadoPor = null;

    #[ORM\OneToMany(targetEntity: ItemLiquidacion::class, mappedBy: 'liquidacion', cascade: ['persist', 'remove'])]
    private Collection $items;

    public function __construct(int $anio, int $mes)
    {
        $this->anio  = $anio;
        $this->mes   = $mes;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getTrabajador(): ?Trabajador { return $this->trabajador; }
    public function setTrabajador(?Trabajador $t): static { $this->trabajador = $t; return $this; }

    public function getAnio(): int { return $this->anio; }
    public function getMes(): int { return $this->mes; }

    public function getPeriodoLabel(): string
    {
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $meses[$this->mes] . ' ' . $this->anio;
    }

    public function getTotalTurnos(): int { return $this->totalTurnos; }
    public function setTotalTurnos(int $t): static { $this->totalTurnos = $t; return $this; }

    public function getTotalHoras(): string { return $this->totalHoras; }
    public function setTotalHoras(string $h): static { $this->totalHoras = $h; return $this; }

    public function getMontoTotal(): string { return $this->montoTotal; }
    public function setMontoTotal(string $m): static { $this->montoTotal = $m; return $this; }

    public function getEstado(): EstadoLiquidacion { return $this->estado; }
    public function setEstado(EstadoLiquidacion $e): static { $this->estado = $e; return $this; }

    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $o): static { $this->observaciones = $o; return $this; }

    public function getFechaPago(): ?\DateTimeInterface { return $this->fechaPago; }
    public function setFechaPago(?\DateTimeInterface $f): static { $this->fechaPago = $f; return $this; }

    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }
    public function getCreadoPor(): ?User { return $this->creadoPor; }
    public function setCreadoPor(?User $u): static { $this->creadoPor = $u; return $this; }

    /** @return Collection<int, ItemLiquidacion> */
    public function getItems(): Collection { return $this->items; }
}
