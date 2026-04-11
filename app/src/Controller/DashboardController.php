<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\Tenant\EventoAdversoRepository;
use App\Repository\Tenant\FacturaRepository;
use App\Repository\Tenant\PacienteRepository;
use App\Repository\Tenant\TrabajadorRepository;
use App\Repository\Tenant\TurnoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly PacienteRepository      $pacienteRepository,
        private readonly TurnoRepository         $turnoRepository,
        private readonly TrabajadorRepository    $trabajadorRepository,
        private readonly EventoAdversoRepository $eventoRepository,
        private readonly FacturaRepository       $facturaRepository,
    ) {}

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        // ── KPIs ────────────────────────────────────────────────────────────
        $pacientesActivos    = count($this->pacienteRepository->findActivos());
        $trabajadoresActivos = count($this->trabajadorRepository->findActivos());
        $turnosHoy           = $this->turnoRepository->countHoyByEstado();
        $eventosAbiertos     = $this->eventoRepository->countByEstado();

        $totalTurnosHoy         = array_sum($turnosHoy);
        $turnosDescubiertosHoy  = $turnosHoy['DESCUBIERTO'] ?? 0;
        $turnosCubiertosHoy     = ($turnosHoy['CUBIERTO'] ?? 0) + ($turnosHoy['PARCIAL'] ?? 0) + ($turnosHoy['COMPLETADO'] ?? 0);
        $eventosAbiertosCnt     = ($eventosAbiertos['ABIERTO'] ?? 0) + ($eventosAbiertos['EN_PROCESO'] ?? 0);

        // ── Turnos de hoy (lista) ────────────────────────────────────────────
        $hoy         = new \DateTime('today');
        $turnosDeHoy = $this->turnoRepository->findByRangoFecha($hoy, $hoy);

        // DESCUBIERTO primero, luego PARCIAL, CUBIERTO, COMPLETADO
        usort($turnosDeHoy, function ($a, $b) {
            $orden = ['DESCUBIERTO' => 0, 'PARCIAL' => 1, 'CUBIERTO' => 2, 'COMPLETADO' => 3];
            return ($orden[$a->getEstado()->value] ?? 9) <=> ($orden[$b->getEstado()->value] ?? 9);
        });

        // ── Alertas ──────────────────────────────────────────────────────────
        $turnosDescubiertos7dias = $this->turnoRepository->findDescubiertosProximosDias(7);
        $eventosGraves           = $this->eventoRepository->findAbiertosGraves();

        $facturasVencidas = [];
        if ($this->isGranted('FINANZAS_VER')) {
            $facturasVencidas = $this->facturaRepository->findVencidas();
        }

        return $this->render('dashboard/index.html.twig', [
            'pacientesActivos'        => $pacientesActivos,
            'trabajadoresActivos'     => $trabajadoresActivos,
            'totalTurnosHoy'          => $totalTurnosHoy,
            'turnosCubiertosHoy'      => $turnosCubiertosHoy,
            'turnosDescubiertosHoy'   => $turnosDescubiertosHoy,
            'eventosAbiertosCnt'      => $eventosAbiertosCnt,
            'turnosDeHoy'             => $turnosDeHoy,
            'turnosDescubiertos7dias' => $turnosDescubiertos7dias,
            'eventosGraves'           => $eventosGraves,
            'facturasVencidas'        => $facturasVencidas,
        ]);
    }
}
