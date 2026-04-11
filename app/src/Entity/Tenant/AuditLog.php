<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Repository\Tenant\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(columns: ['ip_address'], name: 'idx_audit_ip')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_created')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $evento = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailIntentado = null;

    #[ORM\Column(length: 45)]
    private string $ipAddress = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $tenant = null;

    #[ORM\Column(type: 'json')]
    private array $detalles = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEvento(): string { return $this->evento; }
    public function setEvento(string $e): static { $this->evento = $e; return $this; }
    public function getEmailIntentado(): ?string { return $this->emailIntentado; }
    public function setEmailIntentado(?string $e): static { $this->emailIntentado = $e; return $this; }
    public function getIpAddress(): string { return $this->ipAddress; }
    public function setIpAddress(string $ip): static { $this->ipAddress = $ip; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $ua): static { $this->userAgent = $ua; return $this; }
    public function getTenant(): ?string { return $this->tenant; }
    public function setTenant(?string $t): static { $this->tenant = $t; return $this; }
    public function getDetalles(): array { return $this->detalles; }
    public function setDetalles(array $d): static { $this->detalles = $d; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
