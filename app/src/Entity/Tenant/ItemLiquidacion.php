<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Enum\TipoConcepto;
use App\Repository\Tenant\ItemLiquidacionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemLiquidacionRepository::class)]
#[ORM\Table(name: 'items_liquidacion')]
class ItemLiquidacion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LiquidacionMensual::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LiquidacionMensual $liquidacion = null;

    #[ORM\Column(type: 'string', enumType: TipoConcepto::class)]
    private TipoConcepto $concepto = TipoConcepto::TURNO_DIA;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $cantidad = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $valorUnitario = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $subtotal = '0.00';

    #[ORM\ManyToOne(targetEntity: Turno::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Turno $turno = null;

    public function getId(): ?int { return $this->id; }

    public function getLiquidacion(): ?LiquidacionMensual { return $this->liquidacion; }
    public function setLiquidacion(?LiquidacionMensual $l): static { $this->liquidacion = $l; return $this; }

    public function getConcepto(): TipoConcepto { return $this->concepto; }
    public function setConcepto(TipoConcepto $c): static { $this->concepto = $c; return $this; }

    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $d): static { $this->descripcion = $d; return $this; }

    public function getCantidad(): string { return $this->cantidad; }
    public function setCantidad(string $c): static { $this->cantidad = $c; return $this; }

    public function getValorUnitario(): string { return $this->valorUnitario; }
    public function setValorUnitario(string $v): static { $this->valorUnitario = $v; return $this; }

    public function getSubtotal(): string { return $this->subtotal; }
    public function setSubtotal(string $s): static { $this->subtotal = $s; return $this; }

    public function getTurno(): ?Turno { return $this->turno; }
    public function setTurno(?Turno $t): static { $this->turno = $t; return $this; }
}
