<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Repository\Tenant\NotificacionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificacionRepository::class)]
#[ORM\Table(name: 'notificaciones')]
#[ORM\Index(columns: ['destinatario_id', 'leida_en'], name: 'idx_notif_dest_leida')]
class Notificacion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $destinatario;

    #[ORM\Column(type: 'string', length: 50)]
    private string $tipo = 'info';

    #[ORM\Column(type: 'string', length: 200)]
    private string $titulo = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cuerpo = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $leidaEn = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creadoEn;

    public function __construct()
    {
        $this->creadoEn = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getDestinatario(): User { return $this->destinatario; }
    public function setDestinatario(User $u): static { $this->destinatario = $u; return $this; }

    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $t): static { $this->tipo = $t; return $this; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $t): static { $this->titulo = $t; return $this; }

    public function getCuerpo(): ?string { return $this->cuerpo; }
    public function setCuerpo(?string $c): static { $this->cuerpo = $c; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $u): static { $this->url = $u; return $this; }

    public function getLeidaEn(): ?\DateTimeImmutable { return $this->leidaEn; }
    public function isLeida(): bool { return $this->leidaEn !== null; }
    public function marcarLeida(): static { $this->leidaEn = new \DateTimeImmutable(); return $this; }

    public function getCreadoEn(): \DateTimeImmutable { return $this->creadoEn; }

    /** Icono Bootstrap Icons según tipo */
    public function icono(): string
    {
        return match ($this->tipo) {
            'turno_descubierto'   => 'bi-calendar-x text-danger',
            'evento_grave'        => 'bi-exclamation-triangle-fill text-warning',
            'paciente_estado'     => 'bi-person-fill text-info',
            'responsable_asignado' => 'bi-person-check-fill text-primary',
            'documento_vencimiento'=> 'bi-file-earmark-x-fill text-danger',
            default                => 'bi-bell-fill text-secondary',
        };
    }
}
