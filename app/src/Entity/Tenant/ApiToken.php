<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Repository\Tenant\ApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ORM\Table(name: 'api_tokens')]
class ApiToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Nombre descriptivo: "Sistema ERP Mandante", "RRHH Externo", etc. */
    #[ORM\Column(length: 120)]
    private string $nombre = '';

    /** SHA-256 del token en texto plano — nunca se almacena el plano */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash = '';

    /**
     * Recursos permitidos: pacientes, turnos, trabajadores, liquidaciones, facturas.
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    private array $permisos = [];

    #[ORM\Column(type: 'boolean')]
    private bool $activo = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ultimoUso = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creadoEn;

    public function __construct()
    {
        $this->creadoEn = new \DateTimeImmutable();
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return $this->activo && !$this->isExpired();
    }

    public function hasPermiso(string $recurso): bool
    {
        return in_array($recurso, $this->permisos, true) || in_array('*', $this->permisos, true);
    }

    public function marcarUso(): void
    {
        $this->ultimoUso = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $n): static { $this->nombre = $n; return $this; }

    public function getTokenHash(): string { return $this->tokenHash; }
    public function setTokenHash(string $h): static { $this->tokenHash = $h; return $this; }

    public function getPermisos(): array { return $this->permisos; }
    public function setPermisos(array $p): static { $this->permisos = $p; return $this; }

    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $a): static { $this->activo = $a; return $this; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $d): static { $this->expiresAt = $d; return $this; }

    public function getUltimoUso(): ?\DateTimeImmutable { return $this->ultimoUso; }
    public function getCreadoEn(): \DateTimeImmutable { return $this->creadoEn; }
}
