<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant\Factura;
use App\Entity\Tenant\LiquidacionMensual;
use App\Enum\EstadoFactura;
use App\Enum\EstadoLiquidacion;
use App\Enum\EstadoTurno;
use App\Enum\TipoTurno;
use App\Form\FacturaType;
use App\Form\LiquidacionType;
use App\Repository\FacturaRepository;
use App\Repository\LiquidacionMensualRepository;
use App\Repository\TurnoRepository;
use App\Service\ExportService;
use App\Service\FinanzasService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/finanzas', name: 'app_finanzas_')]
#[IsGranted('FINANZAS_VER')]
class FinanzasController extends AbstractController
{
    public function __construct(
        private readonly FinanzasService $finanzasService,
        private readonly LiquidacionMensualRepository $liquidacionRepository,
        private readonly FacturaRepository $facturaRepository,
        private readonly TurnoRepository $turnoRepository,
        private readonly PaginatorInterface $paginator,
        private readonly ExportService $exportService,
    ) {}

    // ─── Reportes ─────────────────────────────────────────────────────────────

    #[Route('/reportes', name: 'reportes', methods: ['GET'])]
    public function reportes(Request $request): Response
    {
        $anio = $request->query->getInt('anio', (int) date('Y'));
        $tipo = $request->query->get('tipo', 'mandante');

        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                      'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $reporteMandante  = [];
        $reporteTrabajador = [];
        $reportePaciente  = [];
        $flujoIngresos    = array_fill(1, 12, 0.0);
        $flujoEgresos     = array_fill(1, 12, 0.0);

        if ($tipo === 'mandante') {
            $rows = $this->facturaRepository->reportePorMandante($anio);
            foreach ($rows as $row) {
                $id = (string) $row['id'];
                if (!isset($reporteMandante[$id])) {
                    $reporteMandante[$id] = [
                        'nombre'           => $row['nombre'],
                        'total_facturas'   => 0,
                        'suma_neto'        => 0.0,
                        'suma_iva'         => 0.0,
                        'suma_total'       => 0.0,
                        'suma_pagado'      => 0.0,
                        'suma_pendiente'   => 0.0,
                    ];
                }
                $reporteMandante[$id]['total_facturas'] += (int) $row['total_facturas'];
                $reporteMandante[$id]['suma_neto']      += (float) $row['suma_neto'];
                $reporteMandante[$id]['suma_iva']       += (float) $row['suma_iva'];
                $reporteMandante[$id]['suma_total']     += (float) $row['suma_total'];
                if ($row['estado']->value === 'PAGADA') {
                    $reporteMandante[$id]['suma_pagado'] += (float) $row['suma_total'];
                } else {
                    $reporteMandante[$id]['suma_pendiente'] += (float) $row['suma_total'];
                }
            }
            usort($reporteMandante, fn($a, $b) => $b['suma_total'] <=> $a['suma_total']);
        }

        if ($tipo === 'trabajador') {
            $rows = $this->liquidacionRepository->reportePorTrabajador($anio);
            foreach ($rows as $row) {
                $id = (string) $row['id'];
                if (!isset($reporteTrabajador[$id])) {
                    $reporteTrabajador[$id] = [
                        'nombre'              => trim($row['nombres'] . ' ' . $row['apellidos']),
                        'total_liquidaciones' => 0,
                        'suma_total'          => 0.0,
                        'suma_pagado'         => 0.0,
                        'suma_pendiente'      => 0.0,
                    ];
                }
                $reporteTrabajador[$id]['total_liquidaciones'] += (int) $row['total_liquidaciones'];
                $reporteTrabajador[$id]['suma_total']          += (float) $row['suma_total'];
                if ($row['estado']->value === 'PAGADA') {
                    $reporteTrabajador[$id]['suma_pagado'] += (float) $row['suma_total'];
                } else {
                    $reporteTrabajador[$id]['suma_pendiente'] += (float) $row['suma_total'];
                }
            }
            usort($reporteTrabajador, fn($a, $b) => $b['suma_total'] <=> $a['suma_total']);
        }

        if ($tipo === 'flujo') {
            foreach ($this->facturaRepository->reporteFlujoIngresos($anio) as $row) {
                $flujoIngresos[(int) $row['mes']] = (float) $row['suma_total'];
            }
            foreach ($this->liquidacionRepository->reporteFlujoEgresos($anio) as $row) {
                $flujoEgresos[(int) $row['mes']] = (float) $row['suma_total'];
            }
        }

        if ($tipo === 'paciente') {
            $tipoKeys = array_map(fn(TipoTurno $t) => $t->value, TipoTurno::cases());
            $rows = $this->turnoRepository->reportePorPaciente($anio);
            foreach ($rows as $row) {
                $id = (string) $row['paciente_id'];
                if (!isset($reportePaciente[$id])) {
                    $reportePaciente[$id] = [
                        'nombre'      => trim($row['nombres'] . ' ' . $row['apellidos']),
                        'total'       => 0,
                        'por_tipo'    => array_fill_keys($tipoKeys, 0),
                        'por_estado'  => [],
                    ];
                }
                $count = (int) $row['total'];
                $tipo_turno = $row['tipo_turno'] instanceof TipoTurno
                    ? $row['tipo_turno']->value
                    : (string) $row['tipo_turno'];
                $estado = $row['estado'] instanceof EstadoTurno
                    ? $row['estado']->value
                    : (string) $row['estado'];

                $reportePaciente[$id]['total'] += $count;
                $reportePaciente[$id]['por_tipo'][$tipo_turno] = ($reportePaciente[$id]['por_tipo'][$tipo_turno] ?? 0) + $count;
                $reportePaciente[$id]['por_estado'][$estado]   = ($reportePaciente[$id]['por_estado'][$estado] ?? 0) + $count;
            }
            usort($reportePaciente, fn($a, $b) => $b['total'] <=> $a['total']);
        }

        return $this->render('finanzas/reportes.html.twig', [
            'anio'              => $anio,
            'tipo'              => $tipo,
            'meses'             => $meses,
            'reporteMandante'   => $reporteMandante,
            'reporteTrabajador' => $reporteTrabajador,
            'reportePaciente'   => $reportePaciente,
            'tiposTurno'        => TipoTurno::cases(),
            'flujoIngresos'     => $flujoIngresos,
            'flujoEgresos'      => $flujoEgresos,
            'aniosDisponibles'  => range((int) date('Y'), (int) date('Y') - 3),
        ]);
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $anioActual = (int) date('Y');

        return $this->render('finanzas/index.html.twig', [
            'montosLiquidacion' => $this->liquidacionRepository->sumMontosByEstado(),
            'montosFactura'     => $this->facturaRepository->sumMontosByEstado(),
            'estadosLiq'        => EstadoLiquidacion::cases(),
            'estadosFac'        => EstadoFactura::cases(),
            'anioActual'        => $anioActual,
        ]);
    }

    // ─── Liquidaciones ────────────────────────────────────────────────────────

    #[Route('/liquidaciones', name: 'liquidaciones', methods: ['GET'])]
    public function liquidaciones(Request $request): Response
    {
        $anio   = $request->query->getInt('anio', (int) date('Y'));
        $estado = EstadoLiquidacion::tryFrom($request->query->get('estado', ''));

        $qb = $this->liquidacionRepository->findQueryBuilder($anio, $estado ?: null);
        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 25);

        return $this->render('finanzas/liquidaciones.html.twig', [
            'pagination' => $pagination,
            'anio'       => $anio,
            'estados'    => EstadoLiquidacion::cases(),
            'filtroEstado' => $request->query->get('estado', ''),
        ]);
    }

    #[Route('/liquidaciones/nueva', name: 'liquidacion_nueva', methods: ['GET', 'POST'])]
    #[IsGranted('FINANZAS_EDITAR')]
    public function liquidacionNueva(Request $request): Response
    {
        $form = $this->createForm(LiquidacionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trabajador = $form->get('trabajador')->getData();
            $anio       = $form->get('anio')->getData();
            $mes        = $form->get('mes')->getData();

            try {
                $liquidacion = $this->finanzasService->generarLiquidacion(
                    $trabajador, $anio, $mes, $this->getUser()
                );
            } catch (\RuntimeException $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->render('finanzas/liquidacion_nueva.html.twig', ['form' => $form]);
            }

            $this->addFlash('success', "Liquidación generada: {$liquidacion->getPeriodoLabel()}");

            return $this->redirectToRoute('app_finanzas_liquidacion_show', ['id' => $liquidacion->getId()]);
        }

        return $this->render('finanzas/liquidacion_nueva.html.twig', ['form' => $form]);
    }

    #[Route('/liquidaciones/{id}', name: 'liquidacion_show', methods: ['GET'])]
    public function liquidacionShow(LiquidacionMensual $liquidacion): Response
    {
        return $this->render('finanzas/liquidacion_show.html.twig', ['liquidacion' => $liquidacion]);
    }

    #[Route('/liquidaciones/{id}/aprobar', name: 'liquidacion_aprobar', methods: ['POST'])]
    #[IsGranted('FINANZAS_EDITAR')]
    public function liquidacionAprobar(Request $request, LiquidacionMensual $liquidacion): Response
    {
        if (!$this->isCsrfTokenValid('liq_' . $liquidacion->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $this->finanzasService->aprobarLiquidacion($liquidacion);
            $this->addFlash('success', 'Liquidación aprobada.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_finanzas_liquidacion_show', ['id' => $liquidacion->getId()]);
    }

    #[Route('/liquidaciones/{id}/pagar', name: 'liquidacion_pagar', methods: ['POST'])]
    #[IsGranted('FINANZAS_EDITAR')]
    public function liquidacionPagar(Request $request, LiquidacionMensual $liquidacion): Response
    {
        if (!$this->isCsrfTokenValid('liq_' . $liquidacion->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $fecha = new \DateTime($request->request->get('fecha_pago', 'today'));
            $this->finanzasService->marcarPagadaLiquidacion($liquidacion, $fecha);
            $this->addFlash('success', 'Liquidación marcada como pagada.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_finanzas_liquidacion_show', ['id' => $liquidacion->getId()]);
    }

    #[Route('/liquidaciones/{id}/exportar', name: 'liquidacion_exportar', methods: ['GET'])]
    public function liquidacionExportar(LiquidacionMensual $liquidacion): Response
    {
        $csv      = $this->finanzasService->exportarLiquidacionCsv($liquidacion);
        $apellido = $liquidacion->getTrabajador()?->getApellidos() ?? 'trabajador';
        $filename = sprintf(
            'liquidacion_%s_%s_%02d.csv',
            $this->toAsciiFilename($apellido),
            $liquidacion->getAnio(),
            $liquidacion->getMes(),
        );

        $response = new StreamedResponse(fn() => print($csv));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT, $filename
        ));

        return $response;
    }

    #[Route('/liquidaciones/exportar-buk', name: 'liquidaciones_exportar_buk', methods: ['GET'])]
    #[IsGranted('FINANZAS_EDITAR')]
    public function liquidacionesExportarBuk(Request $request): Response
    {
        $anio = $request->query->getInt('anio', (int) date('Y'));
        $mes  = $request->query->getInt('mes', (int) date('n'));

        $liquidaciones = $this->liquidacionRepository->findAprobadasPorPeriodo($anio, $mes);

        if ($liquidaciones === []) {
            $this->addFlash('warning', 'No hay liquidaciones aprobadas para ese período.');
            return $this->redirectToRoute('app_finanzas_liquidaciones');
        }

        $csv = $this->exportService->exportarLiquidacionesBuk($liquidaciones, $anio, $mes);
        $filename = sprintf('buk_%d_%02d.csv', $anio, $mes);

        $response = new StreamedResponse(fn() => print($csv));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
        ));

        return $response;
    }

    private function toAsciiFilename(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($normalized === false) {
            $normalized = $value;
        }

        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        return $normalized !== '' ? $normalized : 'trabajador';
    }

    // ─── Facturas ─────────────────────────────────────────────────────────────

    #[Route('/facturas', name: 'facturas', methods: ['GET'])]
    public function facturas(Request $request): Response
    {
        $anio   = $request->query->getInt('anio', (int) date('Y'));
        $estado = EstadoFactura::tryFrom($request->query->get('estado', ''));

        $qb = $this->facturaRepository->findQueryBuilder($anio, $estado ?: null);
        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 25);

        return $this->render('finanzas/facturas.html.twig', [
            'pagination'   => $pagination,
            'anio'         => $anio,
            'estados'      => EstadoFactura::cases(),
            'filtroEstado' => $request->query->get('estado', ''),
        ]);
    }

    #[Route('/facturas/exportar', name: 'facturas_exportar', methods: ['GET'])]
    public function facturasExportar(Request $request): Response
    {
        $anio   = $request->query->getInt('anio', (int) date('Y'));
        $estado = EstadoFactura::tryFrom($request->query->get('estado', ''));

        $facturas = $this->facturaRepository
            ->findQueryBuilder($anio, $estado ?: null)
            ->getQuery()
            ->getResult();

        $csv      = $this->exportService->exportarFacturasCsv($facturas);
        $filename = sprintf('facturas_%d.csv', $anio);

        $response = new StreamedResponse(fn() => print($csv));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT, $filename
        ));

        return $response;
    }

    #[Route('/facturas/nueva', name: 'factura_nueva', methods: ['GET', 'POST'])]
    #[IsGranted('FINANZAS_EDITAR')]
    public function facturaNueva(Request $request): Response
    {
        $form = $this->createForm(FacturaType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $factura = $this->finanzasService->generarFactura(
                mandante:                      $form->get('mandante')->getData(),
                anio:                          $form->get('anio')->getData(),
                mes:                           $form->get('mes')->getData(),
                montoNeto:                     (float) $form->get('montoNeto')->getData(),
                creadoPor:                     $this->getUser(),
                numeroFactura:                 $form->get('numeroFactura')->getData(),
                descuentoPorTurnoDescubierto:  (float) ($form->get('descuentoPorTurnoDescubierto')->getData() ?? 0),
            );

            if ($form->get('porcentajeIva')->getData() !== null) {
                $factura->setPorcentajeIva((string) $form->get('porcentajeIva')->getData());
                $factura->recalcularIva();
            }

            if ($form->get('observaciones')->getData()) {
                $factura->setObservaciones($form->get('observaciones')->getData());
            }

            $this->addFlash('success', "Factura creada: {$factura->getPeriodoLabel()}");

            return $this->redirectToRoute('app_finanzas_factura_show', ['id' => $factura->getId()]);
        }

        return $this->render('finanzas/factura_nueva.html.twig', ['form' => $form]);
    }

    #[Route('/facturas/{id}', name: 'factura_show', methods: ['GET'])]
    public function facturaShow(Factura $factura): Response
    {
        return $this->render('finanzas/factura_show.html.twig', ['factura' => $factura]);
    }

    #[Route('/facturas/{id}/emitir', name: 'factura_emitir', methods: ['POST'])]
    #[IsGranted('FINANZAS_EDITAR')]
    public function facturaEmitir(Request $request, Factura $factura): Response
    {
        if (!$this->isCsrfTokenValid('fac_' . $factura->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $this->finanzasService->emitirFactura($factura, new \DateTime());
            $this->addFlash('success', 'Factura emitida.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_finanzas_factura_show', ['id' => $factura->getId()]);
    }

    #[Route('/facturas/{id}/pagar', name: 'factura_pagar', methods: ['POST'])]
    #[IsGranted('FINANZAS_EDITAR')]
    public function facturaPagar(Request $request, Factura $factura): Response
    {
        if (!$this->isCsrfTokenValid('fac_' . $factura->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try {
            $fecha = new \DateTime($request->request->get('fecha_pago', 'today'));
            $this->finanzasService->marcarPagadaFactura($factura, $fecha);
            $this->addFlash('success', 'Factura marcada como pagada.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_finanzas_factura_show', ['id' => $factura->getId()]);
    }
}
