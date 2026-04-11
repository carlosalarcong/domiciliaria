<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Enum\WebhookEvento;
use App\Repository\Tenant\WebhookDeliveryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WebhookDeliveryRepository::class)]
#[ORM\Table(name: 'webhook_deliveries')]
#[ORM\Index(columns: ['suscripcion_id', 'creado_en'], name: 'idx_wh_delivery_suscripcion')]
#[ORM\Index(columns: ['estado'], name: 'idx_wh_delivery_estado')]
class WebhookDelivery
{
    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_ENTREGADO  = 'entregado';
    public const ESTADO_FALLIDO    = 'fallido';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: WebhookSuscripcion::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WebhookSuscripcion $suscripcion;

    #[ORM\Column(length: 60)]
    private string $evento = '';

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(length: 20)]
    private string $estado = self::ESTADO_PENDIENTE;

    #[ORM\Column]
    private int $intentos = 0;

    #[ORM\Column(nullable: true)]
    private ?int $codigoRespuesta = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $respuestaBody = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ultimoIntento = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creadoEn;

    public function __construct(WebhookSuscripcion $suscripcion, WebhookEvento $evento, array $payload)
    {
        $this->id           = Uuid::v7();
        $this->suscripcion  = $suscripcion;
        $this->evento       = $evento->value;
        $this->payload      = $payload;
        $this->creadoEn     = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getSuscripcion(): WebhookSuscripcion { return $this->suscripcion; }
    public function getEvento(): string { return $this->evento; }
    public function getWebhookEvento(): WebhookEvento { return WebhookEvento::from($this->evento); }
    public function getPayload(): array { return $this->payload; }
    public function getEstado(): string { return $this->estado; }
    public function getIntentos(): int { return $this->intentos; }
    public function getCodigoRespuesta(): ?int { return $this->codigoRespuesta; }
    public function getRespuestaBody(): ?string { return $this->respuestaBody; }
    public function getUltimoIntento(): ?\DateTimeImmutable { return $this->ultimoIntento; }
    public function getCreadoEn(): \DateTimeImmutable { return $this->creadoEn; }

    public function registrarIntento(int $codigo, ?string $body): void
    {
        $this->intentos++;
        $this->codigoRespuesta = $codigo;
        $this->respuestaBody   = $body ? substr($body, 0, 1000) : null;
        $this->ultimoIntento   = new \DateTimeImmutable();
        $this->estado          = ($codigo >= 200 && $codigo < 300)
            ? self::ESTADO_ENTREGADO
            : ($this->intentos >= 3 ? self::ESTADO_FALLIDO : self::ESTADO_PENDIENTE);
    }

    public function marcarFallido(string $motivo): void
    {
        $this->intentos++;
        $this->respuestaBody = substr($motivo, 0, 1000);
        $this->ultimoIntento = new \DateTimeImmutable();
        $this->estado        = $this->intentos >= 3 ? self::ESTADO_FALLIDO : self::ESTADO_PENDIENTE;
    }

    public function resetearParaReintento(): void
    {
        $this->estado   = self::ESTADO_PENDIENTE;
        $this->intentos = 0;
    }
}
