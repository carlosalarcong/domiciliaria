<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DisponibilidadTrabajador;
use App\Entity\DocumentoTrabajador;
use App\Entity\Trabajador;
use App\Entity\User;
use App\Enum\EstadoTurno;
use App\Enum\TipoDocumento;
use App\Repository\TurnoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TrabajadorService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TurnoRepository $turnoRepository,
        private readonly string $uploadDir,
    ) {}

    // ─── Documentos ──────────────────────────────────────────────────────────

    public function subirDocumento(
        Trabajador $trabajador,
        UploadedFile $archivo,
        TipoDocumento $tipo,
        ?string $descripcion,
        User $subidoPor,
    ): DocumentoTrabajador {
        $extension     = $archivo->guessExtension() ?? $archivo->getClientOriginalExtension();
        $nombreSeguro  = bin2hex(random_bytes(16)) . '.' . $extension;
        $subdirectorio = (string) $trabajador->getId();

        $archivo->move($this->uploadDir . '/' . $subdirectorio, $nombreSeguro);

        $doc = new DocumentoTrabajador();
        $doc->setTrabajador($trabajador)
            ->setTipo($tipo)
            ->setNombreOriginal($archivo->getClientOriginalName())
            ->setRutaArchivo($subdirectorio . '/' . $nombreSeguro)
            ->setExtension($extension)
            ->setTamanoBytes($archivo->getSize() ?: 0)
            ->setDescripcion($descripcion)
            ->setSubidoPor($subidoPor);

        $this->em->persist($doc);
        $this->em->flush();

        return $doc;
    }

    public function eliminarDocumento(DocumentoTrabajador $doc): void
    {
        $ruta = $this->uploadDir . '/' . $doc->getRutaArchivo();
        if (file_exists($ruta)) {
            unlink($ruta);
        }

        $this->em->remove($doc);
        $this->em->flush();
    }

    // ─── Disponibilidad ───────────────────────────────────────────────────────

    public function registrarDisponibilidad(
        Trabajador $trabajador,
        DisponibilidadTrabajador $disponibilidad,
    ): DisponibilidadTrabajador {
        $disponibilidad->setTrabajador($trabajador);
        $this->em->persist($disponibilidad);
        $this->em->flush();

        return $disponibilidad;
    }

    public function eliminarDisponibilidad(DisponibilidadTrabajador $disponibilidad): void
    {
        $this->em->remove($disponibilidad);
        $this->em->flush();
    }

    // ─── Horas trabajadas ────────────────────────────────────────────────────

    /**
     * Calcula horas trabajadas por el trabajador en el rango de fechas,
     * basándose en los turnos con estado COMPLETADO.
     *
     * @return array{
     *     turnos: array,
     *     total_horas: float,
     *     total_turnos: int,
     *     desde: \DateTimeInterface,
     *     hasta: \DateTimeInterface,
     * }
     */
    public function calcularHorasTrabajadas(
        Trabajador $trabajador,
        \DateTimeInterface $desde,
        \DateTimeInterface $hasta,
    ): array {
        $turnos = $this->turnoRepository->findByTrabajadorYRango($trabajador, $desde, $hasta);

        $detalle     = [];
        $totalMinutos = 0;

        foreach ($turnos as $turno) {
            if ($turno->getEstado() !== EstadoTurno::COMPLETADO) {
                continue;
            }

            $inicio  = $turno->getRegistroInicio();
            $termino = $turno->getRegistroTermino();

            if ($inicio === null || $termino === null) {
                // Fallback: usar horas planificadas
                $inicio  = $turno->getHoraInicio();
                $termino = $turno->getHoraTermino();
            }

            $minutos = ($termino->getTimestamp() - $inicio->getTimestamp()) / 60;
            // Normalizar: horas de tiempo (sin fecha) pueden tener timestamp base 1970
            if ($minutos < 0) {
                $minutos += 1440; // +24h
            }

            $totalMinutos += $minutos;

            $detalle[] = [
                'fecha'        => $turno->getFecha(),
                'paciente'     => $turno->getPaciente()?->getNombreCompleto() ?? '—',
                'tipo'         => $turno->getTipoTurno()->etiqueta(),
                'hora_inicio'  => $inicio,
                'hora_termino' => $termino,
                'horas'        => round($minutos / 60, 2),
                'turno_id'     => $turno->getId(),
            ];
        }

        return [
            'turnos'       => $detalle,
            'total_horas'  => round($totalMinutos / 60, 2),
            'total_turnos' => count($detalle),
            'desde'        => $desde,
            'hasta'        => $hasta,
        ];
    }

    /**
     * Genera contenido CSV de horas trabajadas.
     */
    public function generarCsvHoras(Trabajador $trabajador, array $resumen): string
    {
        $lines = [];
        $lines[] = implode(';', ['Fecha', 'Paciente', 'Tipo turno', 'Hora inicio', 'Hora término', 'Horas']);

        foreach ($resumen['turnos'] as $fila) {
            $lines[] = implode(';', [
                $fila['fecha']->format('d/m/Y'),
                $fila['paciente'],
                $fila['tipo'],
                $fila['hora_inicio']->format('H:i'),
                $fila['hora_termino']->format('H:i'),
                number_format($fila['horas'], 2, ',', ''),
            ]);
        }

        $lines[] = '';
        $lines[] = implode(';', ['TOTAL', '', '', '', '', number_format($resumen['total_horas'], 2, ',', '')]);

        return "\xEF\xBB\xBF" . implode("\r\n", $lines); // UTF-8 BOM para Excel
    }
}
