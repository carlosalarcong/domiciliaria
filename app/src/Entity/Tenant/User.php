<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Entity\Tenant\Log\LogEntry;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TotpTwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'Este email ya está registrado.')]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TotpTwoFactorInterface, BackupCodeInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Gedmo\Versioned]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Gedmo\Versioned]
    private ?string $nombre = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Gedmo\Versioned]
    private ?string $apellido = null;

    #[ORM\Column(type: 'json')]
    #[Gedmo\Versioned]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean')]
    #[Gedmo\Versioned]
    private bool $activo = true;

    /** TOTP secret para Google Authenticator / Authy */
    #[ORM\Column(nullable: true)]
    private ?string $totpSecret = null;

    /** JSON array de códigos de respaldo de un solo uso */
    #[ORM\Column(type: 'json')]
    private array $backupCodes = [];

    /** Si el usuario ha activado 2FA (siempre true para ADMIN/COORDINADOR) */
    #[ORM\Column(type: 'boolean')]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $toursCompletados = [];

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $actualizadoEn = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void {}

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getApellido(): ?string
    {
        return $this->apellido;
    }

    public function setApellido(string $apellido): static
    {
        $this->apellido = $apellido;
        return $this;
    }

    public function getNombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;
        return $this;
    }

    public function getCreadoEn(): ?\DateTimeImmutable
    {
        return $this->creadoEn;
    }

    public function getActualizadoEn(): ?\DateTimeImmutable
    {
        return $this->actualizadoEn;
    }

    public function getRolPrincipal(): string
    {
        $rolesOrden = ['ROLE_ADMIN', 'ROLE_COORDINADOR', 'ROLE_ENFERMERA', 'ROLE_TENS', 'ROLE_VISUALIZADOR'];
        foreach ($rolesOrden as $rol) {
            if (in_array($rol, $this->roles)) {
                return $rol;
            }
        }
        return 'ROLE_USER';
    }

    public function getRolEtiqueta(): string
    {
        return match ($this->getRolPrincipal()) {
            'ROLE_ADMIN' => 'Administrador',
            'ROLE_COORDINADOR' => 'Coordinador',
            'ROLE_ENFERMERA' => 'Enfermera',
            'ROLE_TENS' => 'TENS',
            'ROLE_VISUALIZADOR' => 'Visualizador',
            default => 'Usuario',
        };
    }

    public function getToursCompletados(): array
    {
        return $this->toursCompletados;
    }

    public function marcarTourCompletado(string $modulo): void
    {
        if (!in_array($modulo, $this->toursCompletados, true)) {
            $this->toursCompletados[] = $modulo;
        }
    }

    public function resetearTours(): void
    {
        $this->toursCompletados = [];
    }

    public function tieneTourCompletado(string $modulo): bool
    {
        return in_array($modulo, $this->toursCompletados, true);
    }

    // ─── TOTP 2FA ────────────────────────────────────────────────────────────

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->totpSecret !== null && ($this->twoFactorEnabled || $this->is2FAForced());
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email ?? '';
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if ($this->totpSecret === null) {
            return null;
        }
        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getTotpSecret(): ?string { return $this->totpSecret; }
    public function setTotpSecret(?string $secret): static { $this->totpSecret = $secret; return $this; }

    public function isTwoFactorEnabled(): bool { return $this->twoFactorEnabled; }
    public function setTwoFactorEnabled(bool $enabled): static { $this->twoFactorEnabled = $enabled; return $this; }

    /** 2FA obligatorio para ADMIN y COORDINADOR */
    public function is2FAForced(): bool
    {
        return in_array($this->getRolPrincipal(), ['ROLE_ADMIN', 'ROLE_COORDINADOR']);
    }

    // ─── Backup codes ─────────────────────────────────────────────────────────

    public function isBackupCode(string $code): bool
    {
        return in_array($code, $this->backupCodes, true);
    }

    public function invalidateBackupCode(string $code): void
    {
        $this->backupCodes = array_values(array_filter($this->backupCodes, fn($c) => $c !== $code));
    }

    public function getBackupCodes(): array { return $this->backupCodes; }

    public function setBackupCodes(array $codes): static { $this->backupCodes = $codes; return $this; }

    public function generateBackupCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        $this->backupCodes = $codes;
        return $codes;
    }
}
