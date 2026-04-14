<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Repository\Tenant\ConfiguracionClinicaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConfiguracionClinicaRepository::class)]
#[ORM\Table(name: 'configuracion_clinica')]
class ConfiguracionClinica
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 150, options: ['default' => 'Mi Clínica'])]
    #[Assert\NotBlank]
    private string $nombreClinica = 'Mi Clínica';

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $razonSocial = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $rutEmpresa = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $giro = null;

    #[ORM\Column(length: 250, nullable: true)]
    private ?string $direccionFiscal = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefonoContacto = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $emailContacto = null;

    #[ORM\Column(length: 5, options: ['default' => '19.00'])]
    private string $porcentajeIva = '19.00';

    #[ORM\Column(type: 'integer', options: ['default' => 30])]
    #[Assert\Range(min: 1, max: 90)]
    private int $diasVencimientoFactura = 30;

    #[ORM\Column(length: 10, options: ['default' => ''])]
    private string $prefijoFactura = '';

    #[ORM\Column(type: 'integer', options: ['default' => 2])]
    #[Assert\Range(min: 1, max: 7)]
    private int $diasAnticipacionAlertas = 2;

    #[ORM\Column(length: 5, options: ['default' => '08:00'])]
    #[Assert\Regex(pattern: '/^\d{1,2}:\d{2}$/')]
    private string $horaRevisionTurnos = '08:00';

    #[ORM\Column(type: 'integer', options: ['default' => 10])]
    #[Assert\Range(min: 1, max: 100)]
    private int $limiteDocumentosMB = 10;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $notifTurnoDescubierto = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $notifEventoGrave = true;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $emailNotificaciones = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $moduloFinanzasActivo = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $moduloEventosActivo = true;

    // ── Integraciones ─────────────────────────────────────────────────────────

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $integracionApiActiva = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $integracionWebhooksActiva = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $integracionSincronizacionActiva = false;

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $extras = [];

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNombreClinica(): string
    {
        return $this->nombreClinica;
    }

    public function setNombreClinica(string $nombreClinica): static
    {
        $this->nombreClinica = $nombreClinica;

        return $this;
    }

    public function getRazonSocial(): ?string
    {
        return $this->razonSocial;
    }

    public function setRazonSocial(?string $razonSocial): static
    {
        $this->razonSocial = $razonSocial;

        return $this;
    }

    public function getRutEmpresa(): ?string
    {
        return $this->rutEmpresa;
    }

    public function setRutEmpresa(?string $rutEmpresa): static
    {
        $this->rutEmpresa = $rutEmpresa;

        return $this;
    }

    public function getGiro(): ?string
    {
        return $this->giro;
    }

    public function setGiro(?string $giro): static
    {
        $this->giro = $giro;

        return $this;
    }

    public function getDireccionFiscal(): ?string
    {
        return $this->direccionFiscal;
    }

    public function setDireccionFiscal(?string $direccionFiscal): static
    {
        $this->direccionFiscal = $direccionFiscal;

        return $this;
    }

    public function getTelefonoContacto(): ?string
    {
        return $this->telefonoContacto;
    }

    public function setTelefonoContacto(?string $telefonoContacto): static
    {
        $this->telefonoContacto = $telefonoContacto;

        return $this;
    }

    public function getEmailContacto(): ?string
    {
        return $this->emailContacto;
    }

    public function setEmailContacto(?string $emailContacto): static
    {
        $this->emailContacto = $emailContacto;

        return $this;
    }

    public function getPorcentajeIva(): string
    {
        return $this->porcentajeIva;
    }

    public function setPorcentajeIva(string $porcentajeIva): static
    {
        $this->porcentajeIva = $porcentajeIva;

        return $this;
    }

    public function getDiasVencimientoFactura(): int
    {
        return $this->diasVencimientoFactura;
    }

    public function setDiasVencimientoFactura(int $diasVencimientoFactura): static
    {
        $this->diasVencimientoFactura = $diasVencimientoFactura;

        return $this;
    }

    public function getPrefijoFactura(): string
    {
        return $this->prefijoFactura;
    }

    public function setPrefijoFactura(string $prefijoFactura): static
    {
        $this->prefijoFactura = $prefijoFactura;

        return $this;
    }

    public function getDiasAnticipacionAlertas(): int
    {
        return $this->diasAnticipacionAlertas;
    }

    public function setDiasAnticipacionAlertas(int $diasAnticipacionAlertas): static
    {
        $this->diasAnticipacionAlertas = $diasAnticipacionAlertas;

        return $this;
    }

    public function getHoraRevisionTurnos(): string
    {
        return $this->horaRevisionTurnos;
    }

    public function setHoraRevisionTurnos(string $horaRevisionTurnos): static
    {
        $this->horaRevisionTurnos = $horaRevisionTurnos;

        return $this;
    }

    public function getLimiteDocumentosMB(): int
    {
        return $this->limiteDocumentosMB;
    }

    public function setLimiteDocumentosMB(int $limiteDocumentosMB): static
    {
        $this->limiteDocumentosMB = $limiteDocumentosMB;

        return $this;
    }

    public function isNotifTurnoDescubierto(): bool
    {
        return $this->notifTurnoDescubierto;
    }

    public function setNotifTurnoDescubierto(bool $notifTurnoDescubierto): static
    {
        $this->notifTurnoDescubierto = $notifTurnoDescubierto;

        return $this;
    }

    public function isNotifEventoGrave(): bool
    {
        return $this->notifEventoGrave;
    }

    public function setNotifEventoGrave(bool $notifEventoGrave): static
    {
        $this->notifEventoGrave = $notifEventoGrave;

        return $this;
    }

    public function getEmailNotificaciones(): ?string
    {
        return $this->emailNotificaciones;
    }

    public function setEmailNotificaciones(?string $emailNotificaciones): static
    {
        $this->emailNotificaciones = $emailNotificaciones;

        return $this;
    }

    public function isModuloFinanzasActivo(): bool
    {
        return $this->moduloFinanzasActivo;
    }

    public function setModuloFinanzasActivo(bool $moduloFinanzasActivo): static
    {
        $this->moduloFinanzasActivo = $moduloFinanzasActivo;

        return $this;
    }

    public function isModuloEventosActivo(): bool
    {
        return $this->moduloEventosActivo;
    }

    public function setModuloEventosActivo(bool $moduloEventosActivo): static
    {
        $this->moduloEventosActivo = $moduloEventosActivo;

        return $this;
    }

    public function isIntegracionApiActiva(): bool
    {
        return $this->integracionApiActiva;
    }

    public function setIntegracionApiActiva(bool $value): static
    {
        $this->integracionApiActiva = $value;

        return $this;
    }

    public function isIntegracionWebhooksActiva(): bool
    {
        return $this->integracionWebhooksActiva;
    }

    public function setIntegracionWebhooksActiva(bool $value): static
    {
        $this->integracionWebhooksActiva = $value;

        return $this;
    }

    public function isIntegracionSincronizacionActiva(): bool
    {
        return $this->integracionSincronizacionActiva;
    }

    public function setIntegracionSincronizacionActiva(bool $value): static
    {
        $this->integracionSincronizacionActiva = $value;

        return $this;
    }

    public function getExtra(string $clave, mixed $default = null): mixed
    {
        return $this->extras[$clave] ?? $default;
    }

    public function setExtra(string $clave, mixed $valor): static
    {
        $this->extras[$clave] = $valor;

        return $this;
    }

    public function getExtras(): array
    {
        return $this->extras;
    }
}
