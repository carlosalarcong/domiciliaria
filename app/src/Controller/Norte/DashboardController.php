<?php

declare(strict_types=1);

namespace App\Controller\Norte;

use App\Repository\Tenant\FacturaRepository;
use App\Repository\Tenant\LiquidacionMensualRepository;
use App\Repository\Tenant\PacienteRepository;
use App\Repository\Tenant\TrabajadorRepository;
use App\Repository\Tenant\TurnoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dashboard ejecutivo para el tenant "norte".
 * Muestra métricas de gestión mensual/anual en vez del panel operativo diario.
 * No tiene #[Route] — el DynamicControllerSubscriber lo inyecta en runtime.
 */
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly PacienteRepository           $pacienteRepository,
        private readonly TrabajadorRepository         $trabajadorRepository,
        private readonly TurnoRepository              $turnoRepository,
        private readonly FacturaRepository            $facturaRepository,
        private readonly LiquidacionMensualRepository $liquidacionRepository,
    ) {}

    public function index(): Response
    {
        $anio  = (int) date('Y');
        $desde = new \DateTime('first day of this month');
        $hasta = new \DateTime('last day of this month');

        // ── KPIs ────────────────────────────────────────────────────────────────
        $pacientesActivos    = count($this->pacienteRepository->findActivos());
        $trabajadoresActivos = count($this->trabajadorRepository->findActivos());
        $turnosCompletadosMes = $this->turnoRepository->countCompletadosMesActual();

        $turnosMes = $this->turnoRepository->findByRangoFecha($desde, $hasta);
        $totalTurnosMes = count($turnosMes);
        $porcentajeCobertura = $totalTurnosMes > 0
            ? round($turnosCompletadosMes / $totalTurnosMes * 100)
            : 0;

        // ── Flujo financiero del año (ingresos vs egresos por mes) ───────────────
        $rawIngresos = $this->facturaRepository->reporteFlujoIngresos($anio);
        $rawEgresos  = $this->liquidacionRepository->reporteFlujoEgresos($anio);

        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                       'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $ingresosPorMes = array_fill(1, 12, 0.0);
        $egresosPorMes  = array_fill(1, 12, 0.0);

        foreach ($rawIngresos as $row) {
            $ingresosPorMes[(int) $row['mes']] = (float) $row['suma_total'];
        }
        foreach ($rawEgresos as $row) {
            $egresosPorMes[(int) $row['mes']] = (float) $row['suma_total'];
        }

        // ── Top 5 trabajadores por monto liquidado en el año ────────────────────
        $reporteTrabajadores = $this->liquidacionRepository->reportePorTrabajador($anio);

        // Agrupar por trabajador (el repo devuelve una fila por trabajador+estado)
        $porTrabajador = [];
        foreach ($reporteTrabajadores as $row) {
            $id = (string) $row['id'];
            if (!isset($porTrabajador[$id])) {
                $porTrabajador[$id] = [
                    'nombre'      => $row['apellidos'] . ', ' . $row['nombres'],
                    'suma_total'  => 0.0,
                    'liquidaciones' => 0,
                ];
            }
            $porTrabajador[$id]['suma_total']    += (float) $row['suma_total'];
            $porTrabajador[$id]['liquidaciones'] += (int) $row['total_liquidaciones'];
        }

        usort($porTrabajador, fn($a, $b) => $b['suma_total'] <=> $a['suma_total']);
        $topTrabajadores = array_slice($porTrabajador, 0, 5);

        return $this->render('dashboard/prueba/index.html.twig', [
            'pacientesActivos'        => $pacientesActivos,
            'trabajadoresActivos'     => $trabajadoresActivos,
            'turnosCompletadosMes'    => $turnosCompletadosMes,
            'totalTurnosMes'          => $totalTurnosMes,
            'porcentajeCobertura'     => $porcentajeCobertura,
            'meses'                   => $meses,
            'ingresosPorMes'          => $ingresosPorMes,
            'egresosPorMes'           => $egresosPorMes,
            'topTrabajadores'         => $topTrabajadores,
            'anio'                    => $anio,
        ]);
    }
}
