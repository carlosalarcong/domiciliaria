<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\Factura;
use App\Entity\Tenant\LiquidacionMensual;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportService
{
    public function exportarPacientesExcel(array $pacientes): StreamedResponse
    {
        $headers = [
            'Apellidos',
            'Nombres',
            'RUT',
            'Fecha nacimiento',
            'Edad',
            'Tipo servicio',
            'Estado',
            'Mandante',
            'Dirección',
            'Comuna',
            'Región',
            'Teléfono',
            'Tutor',
            'Teléfono tutor',
            'Relación tutor',
            'Fecha ingreso',
            'Fecha término',
        ];

        $rows = array_map(
            static fn(Paciente $p): array => [
                $p->getApellidos() ?? '',
                $p->getNombres() ?? '',
                $p->getRut() ?? '',
                $p->getFechaNacimiento()?->format('d/m/Y') ?? '',
                (string) ($p->getEdad() ?? ''),
                $p->getTipoServicio()->etiqueta(),
                $p->getEstado()->etiqueta(),
                $p->getMandante()?->getNombre() ?? '',
                $p->getDireccion() ?? '',
                $p->getComuna() ?? '',
                $p->getRegion() ?? '',
                $p->getTelefono() ?? '',
                $p->getTutorNombre() ?? '',
                $p->getTutorTelefono() ?? '',
                $p->getTutorRelacion() ?? '',
                $p->getFechaIngreso()?->format('d/m/Y') ?? '',
                $p->getFechaTermino()?->format('d/m/Y') ?? '',
            ],
            $pacientes,
        );

        return $this->buildResponse(
            'pacientes_' . date('Ymd') . '.xlsx',
            $headers,
            $rows,
        );
    }

    public function exportarTrabajadoresExcel(array $trabajadores): StreamedResponse
    {
        $headers = [
            'Apellidos',
            'Nombres',
            'RUT',
            'Perfil',
            'Estado',
            'Teléfono',
            'Email',
            'Dirección',
            'Fecha ingreso',
            'Fecha salida',
        ];

        $rows = array_map(
            static fn(Trabajador $t): array => [
                $t->getApellidos() ?? '',
                $t->getNombres() ?? '',
                $t->getRut() ?? '',
                $t->getPerfil()->etiqueta(),
                $t->getEstado()->etiqueta(),
                $t->getTelefono() ?? '',
                $t->getEmail() ?? '',
                $t->getDireccion() ?? '',
                $t->getFechaIngreso()?->format('d/m/Y') ?? '',
                $t->getFechaSalida()?->format('d/m/Y') ?? '',
            ],
            $trabajadores,
        );

        return $this->buildResponse(
            'trabajadores_' . date('Ymd') . '.xlsx',
            $headers,
            $rows,
        );
    }

    public function exportarLiquidacionesExcel(array $liquidaciones): StreamedResponse
    {
        $headers = [
            'RUT_TRABAJADOR',
            'NOMBRES',
            'APELLIDO_PATERNO',
            'APELLIDO_MATERNO',
            'CONCEPTO',
            'CANTIDAD',
            'MONTO_UNITARIO',
            'MONTO_TOTAL',
            'PERIODO',
        ];

        $rows = [];

        foreach ($liquidaciones as $liquidacion) {
            if (!$liquidacion instanceof LiquidacionMensual || $liquidacion->getTrabajador() === null) {
                continue;
            }

            $trabajador = $liquidacion->getTrabajador();
            $apellidos = preg_split('/\s+/', trim((string) $trabajador->getApellidos())) ?: [];
            $apellidoPaterno = $apellidos[0] ?? '';
            $apellidoMaterno = count($apellidos) > 1 ? implode(' ', array_slice($apellidos, 1)) : '';
            $periodo = sprintf('%04d-%02d', $liquidacion->getAnio(), $liquidacion->getMes());

            foreach ($liquidacion->getItems() as $item) {
                $rows[] = [
                    $trabajador->getRut() ?? '',
                    $trabajador->getNombres() ?? '',
                    $apellidoPaterno,
                    $apellidoMaterno,
                    $item->getConcepto()->value,
                    number_format((float) $item->getCantidad(), 2, '.', ''),
                    (string) (int) round((float) $item->getValorUnitario()),
                    (string) (int) round((float) $item->getSubtotal()),
                    $periodo,
                ];
            }
        }

        return $this->buildResponse(
            'liquidaciones_' . date('Ymd') . '.xlsx',
            $headers,
            $rows,
        );
    }

    public function exportarFacturasExcel(array $facturas): StreamedResponse
    {
        $headers = [
            'N° Factura',
            'Período',
            'Mandante',
            'Estado',
            'Total turnos',
            'Monto neto',
            'IVA (%)',
            'Monto IVA',
            'Monto total',
            'Fecha emisión',
            'Fecha vencimiento',
            'Fecha pago',
        ];

        $rows = array_map(
            static fn(Factura $f): array => [
                $f->getNumeroFactura() ?? '',
                $f->getPeriodoLabel(),
                $f->getMandante()?->getNombre() ?? '',
                $f->getEstado()->etiqueta(),
                (string) $f->getTotalTurnos(),
                number_format((float) $f->getMontoNeto(), 2, ',', '.'),
                number_format((float) $f->getPorcentajeIva(), 2, ',', ''),
                number_format((float) $f->getMontoIva(), 2, ',', '.'),
                number_format((float) $f->getMontoTotal(), 2, ',', '.'),
                $f->getFechaEmision()?->format('d/m/Y') ?? '',
                $f->getFechaVencimiento()?->format('d/m/Y') ?? '',
                $f->getFechaPago()?->format('d/m/Y') ?? '',
            ],
            $facturas,
        );

        return $this->buildResponse(
            'facturas_' . date('Ymd') . '.xlsx',
            $headers,
            $rows,
        );
    }

    private function buildResponse(string $filename, array $headers, array $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows): void {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->fromArray($headers, null, 'A1');

                if ($rows !== []) {
                    $sheet->fromArray($rows, null, 'A2');
                }

                $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

                $sheet->getStyle(sprintf('A1:%s1', $lastColumn))
                    ->getFont()
                    ->setBold(true)
                    ->getColor()
                    ->setRGB('FFFFFF');

                $sheet->getStyle(sprintf('A1:%s1', $lastColumn))
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('4472C4');

                foreach (range(1, count($headers)) as $index) {
                    $column = Coordinate::stringFromColumnIndex($index);
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        });

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
        ));

        return $response;
    }
}
