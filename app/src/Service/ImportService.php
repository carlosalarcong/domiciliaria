<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\Mandante;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Enum\EstadoPaciente;
use App\Enum\EstadoTrabajador;
use App\Enum\PerfilTrabajador;
use App\Enum\TipoServicio;
use App\Repository\MandanteRepository;
use App\Repository\PacienteRepository;
use App\Repository\TrabajadorRepository;
use Doctrine\ORM\EntityManagerInterface;

class ImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PacienteRepository    $pacienteRepository,
        private readonly TrabajadorRepository  $trabajadorRepository,
        private readonly MandanteRepository    $mandanteRepository,
    ) {}

    // ─── Plantillas CSV ───────────────────────────────────────────────────────

    public function plantillaPacientesCsv(): string
    {
        $header = implode(';', [
            'nombres', 'apellidos', 'rut',
            'fecha_nacimiento', 'tipo_servicio', 'estado',
            'mandante', 'fecha_ingreso', 'fecha_termino',
            'direccion', 'comuna', 'region', 'telefono',
            'tutor_nombre', 'tutor_telefono', 'tutor_relacion',
        ]);

        $ejemplo = implode(';', [
            'Carmen Rosa', 'Vásquez Muñoz', '8.234.567-K',
            '12/03/1945', 'TURNO_24H', 'ACTIVO',
            'FONASA Región Metropolitana', '15/01/2024', '',
            'Av. Providencia 1234', 'Providencia', 'Metropolitana', '+56 9 1111 2222',
            'Pedro Vásquez', '+56 9 3333 4444', 'Hijo',
        ]);

        return "\xEF\xBB\xBF" . $header . "\r\n" . $ejemplo . "\r\n";
    }

    public function plantillaTrabajadoresCsv(): string
    {
        $header = implode(';', [
            'nombres', 'apellidos', 'rut',
            'perfil', 'email', 'telefono',
            'direccion', 'estado', 'fecha_ingreso',
        ]);

        $ejemplo = implode(';', [
            'María Paz', 'González López', '12.345.678-9',
            'ENFERMERA', 'mgonzalez@clinica.cl', '+56 9 1234 5678',
            'Av. Las Condes 456', 'ACTIVO', '10/01/2024',
        ]);

        return "\xEF\xBB\xBF" . $header . "\r\n" . $ejemplo . "\r\n";
    }

    // ─── Importar Pacientes ───────────────────────────────────────────────────

    /**
     * @return array{importados: int, omitidos: int, errores: array}
     */
    public function importarPacientes(string $csvContent): array
    {
        $rows = $this->parseCsv($csvContent);

        $importados = 0;
        $omitidos   = 0;
        $errores    = [];

        // Cache mandantes por nombre normalizado
        $mandanteMap = [];
        foreach ($this->mandanteRepository->findAll() as $m) {
            $mandanteMap[$this->normalizar($m->getNombre())] = $m;
        }

        foreach ($rows as $i => $row) {
            $linea = $i + 2; // +1 header +1 base-1

            // Campos obligatorios
            $nombres   = trim($row['nombres']   ?? '');
            $apellidos = trim($row['apellidos'] ?? '');
            $rut       = trim($row['rut']       ?? '');

            if ($nombres === '' || $apellidos === '' || $rut === '') {
                $errores[] = ['linea' => $linea, 'rut' => $rut ?: '-', 'motivo' => 'Faltan campos obligatorios: nombres, apellidos o rut.'];
                continue;
            }

            // Verificar duplicado
            if ($this->pacienteRepository->findOneBy(['rut' => $rut]) !== null) {
                $omitidos++;
                continue;
            }

            // Tipo servicio
            $tipoServicio = TipoServicio::tryFrom(strtoupper(trim($row['tipo_servicio'] ?? '')));
            if ($tipoServicio === null) {
                $errores[] = ['linea' => $linea, 'rut' => $rut, 'motivo' => "Tipo de servicio inválido: '{$row['tipo_servicio']}'. Válidos: TURNO_12H, TURNO_24H, VISITA."];
                continue;
            }

            // Estado
            $estadoVal = strtoupper(trim($row['estado'] ?? 'ACTIVO'));
            $estado    = EstadoPaciente::tryFrom($estadoVal) ?? EstadoPaciente::ACTIVO;

            // Mandante (opcional)
            $mandante = null;
            $mandanteNombre = trim($row['mandante'] ?? '');
            if ($mandanteNombre !== '') {
                $mandante = $mandanteMap[$this->normalizar($mandanteNombre)] ?? null;
                if ($mandante === null) {
                    $errores[] = ['linea' => $linea, 'rut' => $rut, 'motivo' => "Mandante no encontrado: '{$mandanteNombre}'. Debe existir previamente en el sistema."];
                    continue;
                }
            }

            $paciente = new Paciente();
            $paciente->setNombres($nombres)
                     ->setApellidos($apellidos)
                     ->setRut($rut)
                     ->setTipoServicio($tipoServicio)
                     ->setEstado($estado);

            if ($mandante !== null) {
                $paciente->setMandante($mandante);
            }

            if ($fn = $this->parseDate($row['fecha_nacimiento'] ?? '')) {
                $paciente->setFechaNacimiento($fn);
            }
            if ($fi = $this->parseDate($row['fecha_ingreso'] ?? '')) {
                $paciente->setFechaIngreso($fi);
            }
            if ($ft = $this->parseDate($row['fecha_termino'] ?? '')) {
                $paciente->setFechaTermino($ft);
            }

            $paciente->setDireccion($this->str($row['direccion']  ?? null))
                     ->setComuna   ($this->str($row['comuna']     ?? null))
                     ->setRegion   ($this->str($row['region']     ?? null))
                     ->setTelefono ($this->str($row['telefono']   ?? null));

            if ($tn = $this->str($row['tutor_nombre']   ?? null)) $paciente->setTutorNombre($tn);
            if ($tt = $this->str($row['tutor_telefono'] ?? null)) $paciente->setTutorTelefono($tt);
            if ($tr = $this->str($row['tutor_relacion'] ?? null)) $paciente->setTutorRelacion($tr);

            $this->em->persist($paciente);
            $importados++;
        }

        $this->em->flush();

        return compact('importados', 'omitidos', 'errores');
    }

    // ─── Importar Trabajadores ────────────────────────────────────────────────

    /**
     * @return array{importados: int, omitidos: int, errores: array}
     */
    public function importarTrabajadores(string $csvContent): array
    {
        $rows = $this->parseCsv($csvContent);

        $importados = 0;
        $omitidos   = 0;
        $errores    = [];

        foreach ($rows as $i => $row) {
            $linea = $i + 2;

            $nombres   = trim($row['nombres']   ?? '');
            $apellidos = trim($row['apellidos'] ?? '');
            $rut       = trim($row['rut']       ?? '');

            if ($nombres === '' || $apellidos === '' || $rut === '') {
                $errores[] = ['linea' => $linea, 'rut' => $rut ?: '-', 'motivo' => 'Faltan campos obligatorios: nombres, apellidos o rut.'];
                continue;
            }

            if ($this->trabajadorRepository->findOneBy(['rut' => $rut]) !== null) {
                $omitidos++;
                continue;
            }

            $perfil = PerfilTrabajador::tryFrom(strtoupper(trim($row['perfil'] ?? '')));
            if ($perfil === null) {
                $errores[] = ['linea' => $linea, 'rut' => $rut, 'motivo' => "Perfil inválido: '{$row['perfil']}'. Válidos: TENS, CUIDADOR, ENFERMERA, OTRO."];
                continue;
            }

            $estadoVal = strtoupper(trim($row['estado'] ?? 'ACTIVO'));
            $estado    = EstadoTrabajador::tryFrom($estadoVal) ?? EstadoTrabajador::ACTIVO;

            $trabajador = new Trabajador();
            $trabajador->setNombres($nombres)
                       ->setApellidos($apellidos)
                       ->setRut($rut)
                       ->setPerfil($perfil)
                       ->setEstado($estado);

            if ($email = $this->str($row['email']    ?? null)) $trabajador->setEmail($email);
            if ($tel   = $this->str($row['telefono'] ?? null)) $trabajador->setTelefono($tel);
            if ($dir   = $this->str($row['direccion']?? null)) $trabajador->setDireccion($dir);

            if ($fi = $this->parseDate($row['fecha_ingreso'] ?? '')) {
                $trabajador->setFechaIngreso($fi);
            }

            $this->em->persist($trabajador);
            $importados++;
        }

        $this->em->flush();

        return compact('importados', 'omitidos', 'errores');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function parseCsv(string $content): array
    {
        // Eliminar BOM UTF-8 si existe
        $content = ltrim($content, "\xEF\xBB\xBF");
        $lines   = preg_split('/\r\n|\r|\n/', trim($content));

        if (empty($lines)) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines), ';');
        $headers = array_map('trim', $headers);

        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $values = str_getcsv($line, ';');
            $row    = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $values[$idx] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function parseDate(string $value): ?\DateTime
    {
        $value = trim($value);
        if ($value === '') return null;

        // Intentar dd/mm/yyyy primero, luego yyyy-mm-dd
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt !== false) {
                $dt->setTime(0, 0, 0);
                return $dt;
            }
        }

        return null;
    }

    private function str(?string $value): ?string
    {
        if ($value === null || trim($value) === '') return null;
        return trim($value);
    }

    private function normalizar(string $texto): string
    {
        return mb_strtolower(trim($texto));
    }
}
