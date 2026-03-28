<?php

declare(strict_types=1);

namespace App\Entity\Tenant;

use App\Enum\TipoDocumento;
use App\Repository\DocumentoTrabajadorRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DocumentoTrabajadorRepository::class)]
#[ORM\Table(name: 'documentos_trabajador')]
class DocumentoTrabajador
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Trabajador::class, inversedBy: 'documentos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Trabajador $trabajador = null;

    #[ORM\Column(type: 'string', enumType: TipoDocumento::class)]
    private TipoDocumento $tipo = TipoDocumento::OTRO;

    #[ORM\Column(length: 255)]
    private string $nombreOriginal = '';

    #[ORM\Column(length: 255)]
    private string $rutaArchivo = '';

    #[ORM\Column(length: 20)]
    private string $extension = '';

    #[ORM\Column(type: 'integer')]
    private int $tamanoBytes = 0;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $subidoPor = null;

    public function getId(): ?Uuid { return $this->id; }

    public function getTrabajador(): ?Trabajador { return $this->trabajador; }
    public function setTrabajador(?Trabajador $t): static { $this->trabajador = $t; return $this; }

    public function getTipo(): TipoDocumento { return $this->tipo; }
    public function setTipo(TipoDocumento $t): static { $this->tipo = $t; return $this; }

    public function getNombreOriginal(): string { return $this->nombreOriginal; }
    public function setNombreOriginal(string $n): static { $this->nombreOriginal = $n; return $this; }

    public function getRutaArchivo(): string { return $this->rutaArchivo; }
    public function setRutaArchivo(string $r): static { $this->rutaArchivo = $r; return $this; }

    public function getExtension(): string { return $this->extension; }
    public function setExtension(string $e): static { $this->extension = $e; return $this; }

    public function getTamanoBytes(): int { return $this->tamanoBytes; }
    public function setTamanoBytes(int $t): static { $this->tamanoBytes = $t; return $this; }

    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $d): static { $this->descripcion = $d; return $this; }

    public function getCreadoEn(): ?\DateTimeImmutable { return $this->creadoEn; }

    public function getSubidoPor(): ?User { return $this->subidoPor; }
    public function setSubidoPor(?User $u): static { $this->subidoPor = $u; return $this; }

    public function getTamanoFormateado(): string
    {
        if ($this->tamanoBytes < 1024) return $this->tamanoBytes . ' B';
        if ($this->tamanoBytes < 1048576) return round($this->tamanoBytes / 1024, 1) . ' KB';
        return round($this->tamanoBytes / 1048576, 1) . ' MB';
    }

    public function getIconoExtension(): string
    {
        return match (strtolower($this->extension)) {
            'pdf'              => 'bi-file-earmark-pdf text-danger',
            'doc', 'docx'      => 'bi-file-earmark-word text-primary',
            'xls', 'xlsx'      => 'bi-file-earmark-excel text-success',
            'jpg', 'jpeg', 'png' => 'bi-file-earmark-image text-warning',
            default            => 'bi-file-earmark text-secondary',
        };
    }
}
