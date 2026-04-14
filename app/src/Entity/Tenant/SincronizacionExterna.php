<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Repository\Tenant\SincronizacionExternaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SincronizacionExternaRepository::class)]
#[ORM\Table(name: 'sincronizaciones_externas')]
class SincronizacionExterna
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 150)]
    private string $nombre = '';

    #[ORM\Column(length: 500)]
    private string $urlEndpoint = '';

    #[ORM\Column(length: 10)]
    private string $metodo = 'GET';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $headers = null;

    #[ORM\Column(length: 100)]
    private string $expresionCron = '';

    #[ORM\Column(type: 'boolean')]
    private bool $activa = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ultimaEjecucion = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ultimoResultado = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $ultimoEstado = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creadoEn;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $creadoPor = null;

    public function __construct()
    {
        $this->creadoEn = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }

    public function getUrlEndpoint(): string { return $this->urlEndpoint; }
    public function setUrlEndpoint(string $urlEndpoint): static { $this->urlEndpoint = $urlEndpoint; return $this; }

    public function getMetodo(): string { return $this->metodo; }
    public function setMetodo(string $metodo): static { $this->metodo = strtoupper($metodo); return $this; }

    public function getHeaders(): ?array { return $this->headers; }
    public function setHeaders(?array $headers): static { $this->headers = $headers; return $this; }

    public function getExpresionCron(): string { return $this->expresionCron; }
    public function setExpresionCron(string $expresionCron): static { $this->expresionCron = $expresionCron; return $this; }

    public function isActiva(): bool { return $this->activa; }
    public function setActiva(bool $activa): static { $this->activa = $activa; return $this; }

    public function getUltimaEjecucion(): ?\DateTimeImmutable { return $this->ultimaEjecucion; }
    public function setUltimaEjecucion(?\DateTimeImmutable $ultimaEjecucion): static { $this->ultimaEjecucion = $ultimaEjecucion; return $this; }

    public function getUltimoResultado(): ?string { return $this->ultimoResultado; }
    public function setUltimoResultado(?string $ultimoResultado): static { $this->ultimoResultado = $ultimoResultado; return $this; }

    public function getUltimoEstado(): ?string { return $this->ultimoEstado; }
    public function setUltimoEstado(?string $ultimoEstado): static { $this->ultimoEstado = $ultimoEstado; return $this; }

    public function getCreadoEn(): \DateTimeImmutable { return $this->creadoEn; }

    public function getCreadoPor(): ?User { return $this->creadoPor; }
    public function setCreadoPor(?User $creadoPor): static { $this->creadoPor = $creadoPor; return $this; }
}
