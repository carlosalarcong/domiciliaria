<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Enum\TipoConcepto;
use App\Repository\Tenant\TarifaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TarifaRepository::class)]
#[ORM\Table(name: 'tarifas')]
#[ORM\Index(columns: ['mandante_id', 'tipo_concepto', 'vigencia_desde'], name: 'idx_tarifa_lookup')]
#[Assert\Expression(
    expression: 'this.getVigenciaHasta() === null or this.getVigenciaHasta() >= this.getVigenciaDesde()',
    message: 'La fecha de fin de vigencia debe ser igual o posterior a la fecha de inicio.',
)]
class Tarifa
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** null = tarifa general (aplica a todos los mandantes) */
    #[ORM\ManyToOne(targetEntity: Mandante::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Mandante $mandante = null;

    #[ORM\Column(enumType: TipoConcepto::class)]
    #[Assert\NotNull]
    private TipoConcepto $tipoConcepto;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $valorUnitario;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private \DateTimeImmutable $vigenciaDesde;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $vigenciaHasta = null;

    #[ORM\Column(type: 'boolean')]
    private bool $activa = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descripcion = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getMandante(): ?Mandante { return $this->mandante; }
    public function setMandante(?Mandante $mandante): static { $this->mandante = $mandante; return $this; }

    public function getTipoConcepto(): TipoConcepto { return $this->tipoConcepto; }
    public function setTipoConcepto(TipoConcepto $tipoConcepto): static { $this->tipoConcepto = $tipoConcepto; return $this; }

    public function getValorUnitario(): string { return $this->valorUnitario; }
    public function setValorUnitario(string $valorUnitario): static { $this->valorUnitario = $valorUnitario; return $this; }

    public function getVigenciaDesde(): \DateTimeImmutable { return $this->vigenciaDesde; }
    public function setVigenciaDesde(\DateTimeImmutable $vigenciaDesde): static { $this->vigenciaDesde = $vigenciaDesde; return $this; }

    public function getVigenciaHasta(): ?\DateTimeImmutable { return $this->vigenciaHasta; }
    public function setVigenciaHasta(?\DateTimeImmutable $vigenciaHasta): static { $this->vigenciaHasta = $vigenciaHasta; return $this; }

    public function isActiva(): bool { return $this->activa; }
    public function setActiva(bool $activa): static { $this->activa = $activa; return $this; }

    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): static { $this->descripcion = $descripcion; return $this; }

    public function esVigenteEn(\DateTimeInterface $fecha): bool
    {
        $d = \DateTimeImmutable::createFromInterface($fecha);
        if ($d < $this->vigenciaDesde) {
            return false;
        }
        if ($this->vigenciaHasta !== null && $d > $this->vigenciaHasta) {
            return false;
        }
        return true;
    }

    public function getNombreCompleto(): string
    {
        $base = $this->tipoConcepto->etiqueta() . ' — $' . number_format((float) $this->valorUnitario, 0, ',', '.');
        if ($this->mandante !== null) {
            $base .= ' (' . $this->mandante->getNombre() . ')';
        }
        return $base;
    }
}
