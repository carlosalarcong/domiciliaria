<?php

declare(strict_types=1);

namespace App\Entity\Tenant\Log;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Entity\Repository\LogEntryRepository;

/**
 * Entidad LogEntry personalizada que usa tipo json en lugar del array obsoleto de Doctrine 4.
 */
#[ORM\Entity(repositoryClass: LogEntryRepository::class)]
#[ORM\Table(name: 'ext_log_entries')]
#[ORM\Index(name: 'log_class_lookup_idx', columns: ['object_class'])]
#[ORM\Index(name: 'log_date_lookup_idx', columns: ['logged_at'])]
#[ORM\Index(name: 'log_user_lookup_idx', columns: ['username'])]
#[ORM\Index(name: 'log_version_lookup_idx', columns: ['object_id', 'object_class', 'version'])]
class LogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private ?string $action = null;

    #[ORM\Column(name: 'logged_at', type: 'datetime')]
    private ?\DateTime $loggedAt = null;

    #[ORM\Column(name: 'object_id', length: 64, nullable: true)]
    private ?string $objectId = null;

    #[ORM\Column(name: 'object_class', length: 255)]
    private ?string $objectClass = null;

    #[ORM\Column(type: 'integer')]
    private ?int $version = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    public function getId(): ?int { return $this->id; }
    public function getAction(): ?string { return $this->action; }
    public function setAction(string $action): void { $this->action = $action; }
    public function getLoggedAt(): ?\DateTime { return $this->loggedAt; }
    /** Gedmo llama este método sin argumentos; la fecha se asigna internamente. */
    public function setLoggedAt(): void { $this->loggedAt = new \DateTime(); }
    public function getObjectId(): ?string { return $this->objectId; }
    public function setObjectId(?string $objectId): void { $this->objectId = $objectId; }
    public function getObjectClass(): ?string { return $this->objectClass; }
    public function setObjectClass(string $objectClass): void { $this->objectClass = $objectClass; }
    public function getVersion(): ?int { return $this->version; }
    public function setVersion(int $version): void { $this->version = $version; }
    public function getData(): ?array { return $this->data; }
    public function setData(?array $data): void { $this->data = $data; }
    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $username): void { $this->username = $username; }
}
