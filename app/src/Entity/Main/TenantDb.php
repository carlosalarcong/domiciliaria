<?php

declare(strict_types=1);

namespace App\Entity\Main;

use App\Repository\Main\TenantDbRepository;
use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;

#[ORM\Entity(repositoryClass: TenantDbRepository::class)]
#[ORM\Table(name: 'tenant_db')]
class TenantDb implements TenantDbConfigurationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $databaseName;

    #[ORM\Column(length: 50, options: ['default' => 'DATABASE_MIGRATED'])]
    private string $databaseStatus = 'DATABASE_MIGRATED';

    #[ORM\Column(length: 100, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    // ─── TenantDbConfigurationInterface ──────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifierValue(): mixed
    {
        return $this->id;
    }

    public function getDbName(): string
    {
        return $this->databaseName;
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function setDatabaseName(string $databaseName): static
    {
        $this->databaseName = $databaseName;
        return $this;
    }

    public function getDbUsername(): ?string
    {
        return $_ENV['TENANT_DB_USER'] ?? null;
    }

    public function getDbPassword(): ?string
    {
        return $_ENV['TENANT_DB_PASSWORD'] ?? null;
    }

    public function getDbHost(): ?string
    {
        return $_ENV['TENANT_DB_HOST'] ?? 'postgres';
    }

    public function getDbPort(): ?int
    {
        return 5432;
    }

    public function getDatabaseStatus(): DatabaseStatusEnum
    {
        return DatabaseStatusEnum::from($this->databaseStatus);
    }

    public function setDatabaseStatus(DatabaseStatusEnum $databaseStatus): static
    {
        $this->databaseStatus = $databaseStatus->value;
        return $this;
    }

    public function getDsnUrl(): string
    {
        $user     = $this->getDbUsername();
        $password = $this->getDbPassword();
        $host     = $this->getDbHost();
        $port     = $this->getDbPort();
        $dbname   = $this->databaseName;

        return sprintf('postgresql://%s:%s@%s:%d/%s', $user, $password, $host, $port, $dbname);
    }

    public function getDriverType(): DriverTypeEnum
    {
        return DriverTypeEnum::POSTGRES;
    }

    // ─── Getters / Setters ────────────────────────────────────────────────────

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
}
