<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant\Factura;
use App\Entity\Tenant\LiquidacionMensual;
use App\Enum\EstadoFactura;
use App\Enum\EstadoLiquidacion;
use App\Enum\TipoConcepto;
use App\Form\FacturaType;
use App\Form\LiquidacionType;
use App\Repository\FacturaRepository;
use App\Repository\LiquidacionMensualRepository;
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
        private readonly PaginatorInterface $paginator,
        private readonly ExportService $exportService,
    ) {}

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

            $tarifas = [];
            foreach (TipoConcepto::cases() as $concepto) {
                $campo = 'tarifa_' . $concepto->value;
                if ($form->has($campo)) {
                    $tarifas[$concepto->value] = (float) ($form->get($campo)->getData() ?? 0);
                }
            }

            $liquidacion = $this->finanzasService->generarLiquidacion(
                $trabajador, $anio, $mes, $tarifas, $this->getUser()
            );

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
        $this->finanzasService->aprobarLiquidacion($liquidacion);
        $this->addFlash('success', 'Liquidación aprobada.');

        return $this->redirectToRoute('app_finanzas_liquidacion_show', ['id' => $liquidacion->getId()]);
    }

    #[Route('/liquidaciones/{id}/pagar', name: 'liquidacion_pagar', methods: ['POST'])]
    #[IsGranted('FINANZAS_EDITAR')]
    public function liquidacionPagar(Request $request, LiquidacionMensual $liquidacion): Response
    {
        if (!$this->isCsrfTokenValid('liq_' . $liquidacion->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $fecha = new \DateTime($request->request->get('fecha_pago', 'today'));
        $this->finanzasService->marcarPagadaLiquidacion($liquidacion, $fecha);
        $this->addFlash('success', 'Liquidación marcada como pagada.');

        return $this->redirectToRoute('app_finanzas_liquidacion_show', ['id' => $liquidacion->getId()]);
    }

    #[Route('/liquidaciones/{id}/exportar', name: 'liquidacion_exportar', methods: ['GET'])]
    public function liquidacionExportar(LiquidacionMensual $liquidacion): Response
    {
        $csv      = $this->finanzasService->exportarLiquidacionCsv($liquidacion);
        $filename = sprintf(
            'liquidacion_%s_%s_%02d.csv',
            strtolower(str_replace(' ', '_', $liquidacion->getTrabajador()?->getApellidos() ?? 'trabajador')),
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
                mandante:      $form->get('mandante')->getData(),
                anio:          $form->get('anio')->getData(),
                mes:           $form->get('mes')->getData(),
                montoNeto:     (float) $form->get('montoNeto')->getData(),
                creadoPor:     $this->getUser(),
                numeroFactura: $form->get('numeroFactura')->getData(),
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
        $this->finanzasService->emitirFactura($factura, new \DateTime());
        $this->addFlash('success', 'Factura emitida.');

        return $this->redirectToRoute('app_finanzas_factura_show', ['id' => $factura->getId()]);
    }

    #[Route('/facturas/{id}/pagar', name: 'factura_pagar', methods: ['POST'])]
    #[IsGranted('FINANZAS_EDITAR')]
    public function facturaPagar(Request $request, Factura $factura): Response
    {
        if (!$this->isCsrfTokenValid('fac_' . $factura->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $fecha = new \DateTime($request->request->get('fecha_pago', 'today'));
        $this->finanzasService->marcarPagadaFactura($factura, $fecha);
        $this->addFlash('success', 'Factura marcada como pagada.');

        return $this->redirectToRoute('app_finanzas_factura_show', ['id' => $factura->getId()]);
    }
}
