<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Repository\Tenant\CondicionDomicilioRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: CondicionDomicilioRepository::class)]
#[ORM\Table(name: 'condicion_domicilio')]
class CondicionDomicilio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Paciente::class, inversedBy: 'condicionDomicilio')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Paciente $paciente = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $accesoDescripcion = null;

    #[ORM\Column(type: 'boolean')]
    private bool $tieneMascotas = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mascotasDetalle = null;

    #[ORM\Column(type: 'boolean')]
    private bool $tieneBarrerasArquitectonicas = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $barrerasDetalle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacionesSeguridad = null;

    #[ORM\Column(type: 'boolean')]
    private bool $requiereAscensor = false;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codigoAcceso = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $actualizadoEn = null;

    public function getId(): ?int { return $this->id; }
    public function getPaciente(): ?Paciente { return $this->paciente; }
    public function setPaciente(?Paciente $p): static { $this->paciente = $p; return $this; }
    public function getAccesoDescripcion(): ?string { return $this->accesoDescripcion; }
    public function setAccesoDescripcion(?string $a): static { $this->accesoDescripcion = $a; return $this; }
    public function isTieneMascotas(): bool { return $this->tieneMascotas; }
    public function setTieneMascotas(bool $t): static { $this->tieneMascotas = $t; return $this; }
    public function getMascotasDetalle(): ?string { return $this->mascotasDetalle; }
    public function setMascotasDetalle(?string $m): static { $this->mascotasDetalle = $m; return $this; }
    public function isTieneBarrerasArquitectonicas(): bool { return $this->tieneBarrerasArquitectonicas; }
    public function setTieneBarrerasArquitectonicas(bool $t): static { $this->tieneBarrerasArquitectonicas = $t; return $this; }
    public function getBarrerasDetalle(): ?string { return $this->barrerasDetalle; }
    public function setBarrerasDetalle(?string $b): static { $this->barrerasDetalle = $b; return $this; }
    public function getObservacionesSeguridad(): ?string { return $this->observacionesSeguridad; }
    public function setObservacionesSeguridad(?string $o): static { $this->observacionesSeguridad = $o; return $this; }
    public function isRequiereAscensor(): bool { return $this->requiereAscensor; }
    public function setRequiereAscensor(bool $r): static { $this->requiereAscensor = $r; return $this; }
    public function getCodigoAcceso(): ?string { return $this->codigoAcceso; }
    public function setCodigoAcceso(?string $c): static { $this->codigoAcceso = $c; return $this; }
    public function getActualizadoEn(): ?\DateTimeImmutable { return $this->actualizadoEn; }
}
