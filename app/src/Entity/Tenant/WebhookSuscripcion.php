<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Enum\WebhookEvento;
use App\Repository\Tenant\WebhookSuscripcionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WebhookSuscripcionRepository::class)]
#[ORM\Table(name: 'webhook_suscripciones')]
class WebhookSuscripcion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $nombre = '';

    #[ORM\Column(length: 500)]
    private string $url = '';

    /** Clave secreta para firma HMAC-SHA256 */
    #[ORM\Column(length: 64)]
    private string $secret = '';

    /** @var WebhookEvento[] */
    #[ORM\Column(type: 'json')]
    private array $eventos = [];

    #[ORM\Column]
    private bool $activo = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creadoEn;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $actualizadoEn = null;

    public function __construct()
    {
        $this->id       = Uuid::v7();
        $this->creadoEn = new \DateTimeImmutable();
        $this->secret   = bin2hex(random_bytes(16));
    }

    public function getId(): Uuid { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $n): static { $this->nombre = $n; return $this; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $u): static { $this->url = $u; return $this; }
    public function getSecret(): string { return $this->secret; }
    public function setSecret(string $s): static { $this->secret = $s; return $this; }
    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $a): static { $this->activo = $a; return $this; }
    public function getCreadoEn(): \DateTimeImmutable { return $this->creadoEn; }
    public function getActualizadoEn(): ?\DateTimeImmutable { return $this->actualizadoEn; }
    public function setActualizadoEn(\DateTimeImmutable $d): static { $this->actualizadoEn = $d; return $this; }

    /** @return WebhookEvento[] */
    public function getEventos(): array
    {
        return array_map(
            fn(string $v) => WebhookEvento::from($v),
            $this->eventos,
        );
    }

    /** @param WebhookEvento[] $eventos */
    public function setEventos(array $eventos): static
    {
        $this->eventos = array_map(fn(WebhookEvento $e) => $e->value, $eventos);
        return $this;
    }

    public function escuchaEvento(WebhookEvento $evento): bool
    {
        return in_array($evento->value, $this->eventos, true);
    }
}
