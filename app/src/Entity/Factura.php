<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EstadoFactura;
use App\Repository\FacturaRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FacturaRepository::class)]
#[ORM\Table(name: 'facturas')]
class Factura
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Mandante::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Mandante $mandante = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $numeroFactura = null;

    #[ORM\Column(type: 'smallint')]
    private int $anio;

    #[ORM\Column(type: 'smallint')]
    private int $mes;

    #[ORM\Column(type: 'integer')]
    private int $totalTurnos = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $montoNeto = '0.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $porcentajeIva = '19.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $montoIva = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $montoTotal = '0.00';

    #[ORM\Column(type: 'string', enumType: EstadoFactura::class)]
    private EstadoFactura $estado = EstadoFactura::BORRADOR;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaEmision = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaVencimiento = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaPago = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observaciones = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $creadoPor = null;

    public function __construct(int $anio, int $mes)
    {
        $this->anio = $anio;
        $this->mes  = $mes;
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getMandante(): ?Mandante { return $this->mandante; }
    public function setMandante(?Mandante $m): static { $this->mandante = $m; return $this; }

    public function getNumeroFactura(): ?string { return $this->numeroFactura; }
    public function setNumeroFactura(?string $n): static { $this->numeroFactura = $n; return $this; }

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

    public function getMontoNeto(): string { return $this->montoNeto; }
    public function setMontoNeto(string $m): static { $this->montoNeto = $m; return $this; }

    public function getPorcentajeIva(): string { return $this->porcentajeIva; }
    public function setPorcentajeIva(string $p): static { $this->porcentajeIva = $p; return $this; }

    public function getMontoIva(): string { return $this->montoIva; }
    public function setMontoIva(string $m): static { $this->montoIva = $m; return $this; }

    public function getMontoTotal(): string { return $this->montoTotal; }
    public function setMontoTotal(string $m): static { $this->montoTotal = $m; return $this; }

    public function getEstado(): EstadoFactura { return $this->estado; }
    public function setEstado(EstadoFactura $e): static { $this->estado = $e; return $this; }

    public function getFechaEmision(): ?\DateTimeInterface { return $this->fechaEmision; }
    public function setFechaEmision(?\DateTimeInterface $f): static { $this->fechaEmision = $f; return $this; }

    public function getFechaVencimiento(): ?\DateTimeInterface { return $this->fechaVencimiento; }
    public function setFechaVencimiento(?\DateTimeInterface $f): static { $this->fechaVencimiento = $f; return $this; }

    public function getFechaPago(): ?\DateTimeInterface { return $this->fechaPago; }
    public function setFechaPago(?\DateTimeInterface $f): static { $this->fechaPago = $f; return $this; }

    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $o): static { $this->observaciones = $o; return $this; }

    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }
    public function getCreadoPor(): ?User { return $this->creadoPor; }
    public function setCreadoPor(?User $u): static { $this->creadoPor = $u; return $this; }

    public function recalcularIva(): void
    {
        $neto  = (float) $this->montoNeto;
        $iva   = round($neto * ((float) $this->porcentajeIva / 100), 2);
        $this->montoIva   = number_format($iva, 2, '.', '');
        $this->montoTotal = number_format($neto + $iva, 2, '.', '');
    }
}
