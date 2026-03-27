<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DisponibilidadTrabajador;
use App\Entity\DocumentoTrabajador;
use App\Entity\Trabajador;
use App\Form\DisponibilidadTrabajadorType;
use App\Form\DocumentoTrabajadorType;
use App\Form\TrabajadorType;
use App\Repository\DisponibilidadTrabajadorRepository;
use App\Repository\DocumentoTrabajadorRepository;
use App\Repository\TrabajadorRepository;
use App\Service\TrabajadorService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/trabajadores', name: 'app_trabajador_')]
#[IsGranted('ROLE_COORDINADOR')]
class TrabajadorController extends AbstractController
{
    public function __construct(
        private readonly TrabajadorRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
        private readonly TrabajadorService $trabajadorService,
        private readonly DocumentoTrabajadorRepository $documentoRepository,
        private readonly DisponibilidadTrabajadorRepository $disponibilidadRepository,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $qb = $this->repository->findQueryBuilder();
        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 20);

        return $this->render('trabajador/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/nuevo', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $trabajador = new Trabajador();
        $form = $this->createForm(TrabajadorType::class, $trabajador);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($trabajador);
            $this->em->flush();
            $this->addFlash('success', 'Trabajador registrado correctamente.');

            return $this->redirectToRoute('app_trabajador_show', ['id' => $trabajador->getId()]);
        }

        return $this->render('trabajador/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Trabajador $trabajador, Request $request): Response
    {
        $tab          = $request->query->get('tab', 'turnos');
        $documentos   = $this->documentoRepository->findByTrabajador($trabajador);
        $disponibilidades = $this->disponibilidadRepository->findByTrabajadorOrdenado($trabajador);

        $formDoc   = $this->createForm(DocumentoTrabajadorType::class, null, [
            'action' => $this->generateUrl('app_trabajador_documento_subir', ['id' => $trabajador->getId()]),
        ]);
        $formDisp  = $this->createForm(DisponibilidadTrabajadorType::class, new DisponibilidadTrabajador(), [
            'action' => $this->generateUrl('app_trabajador_disponibilidad_nueva', ['id' => $trabajador->getId()]),
        ]);

        return $this->render('trabajador/show.html.twig', [
            'trabajador'       => $trabajador,
            'tab'              => $tab,
            'documentos'       => $documentos,
            'disponibilidades' => $disponibilidades,
            'formDoc'          => $formDoc,
            'formDisp'         => $formDisp,
        ]);
    }

    #[Route('/{id}/editar', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Trabajador $trabajador): Response
    {
        $form = $this->createForm(TrabajadorType::class, $trabajador);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Trabajador actualizado correctamente.');

            return $this->redirectToRoute('app_trabajador_show', ['id' => $trabajador->getId()]);
        }

        return $this->render('trabajador/edit.html.twig', [
            'trabajador' => $trabajador,
            'form'       => $form,
        ]);
    }

    #[Route('/{id}/toggle-estado', name: 'toggle_estado', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleEstado(Request $request, Trabajador $trabajador): Response
    {
        if (!$this->isCsrfTokenValid('toggle_estado_' . $trabajador->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $nuevoEstado = $trabajador->getEstado() === \App\Enum\EstadoTrabajador::ACTIVO
            ? \App\Enum\EstadoTrabajador::INACTIVO
            : \App\Enum\EstadoTrabajador::ACTIVO;

        $trabajador->setEstado($nuevoEstado);
        $this->em->flush();
        $this->addFlash('success', 'Estado del trabajador actualizado.');

        return $this->redirectToRoute('app_trabajador_show', ['id' => $trabajador->getId()]);
    }

    // ─── Documentos ──────────────────────────────────────────────────────────

    #[Route('/{id}/documentos/subir', name: 'documento_subir', methods: ['POST'])]
    public function documentoSubir(Request $request, Trabajador $trabajador): Response
    {
        $form = $this->createForm(DocumentoTrabajadorType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->trabajadorService->subirDocumento(
                    $trabajador,
                    $form->get('archivo')->getData(),
                    $form->get('tipo')->getData(),
                    $form->get('descripcion')->getData(),
                    $this->getUser(),
                );
                $this->addFlash('success', 'Documento subido correctamente.');
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Error al subir el documento: ' . $e->getMessage());
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_trabajador_show', ['id' => $trabajador->getId(), 'tab' => 'documentos']);
    }

    #[Route('/{id}/documentos/{docId}/descargar', name: 'documento_descargar', methods: ['GET'])]
    public function documentoDescargar(Trabajador $trabajador, string $docId): Response
    {
        $doc = $this->documentoRepository->find($docId);
        if (!$doc || $doc->getTrabajador() !== $trabajador) {
            throw $this->createNotFoundException();
        }

        $ruta = $this->getParameter('app.upload_dir') . '/' . $doc->getRutaArchivo();
        if (!file_exists($ruta)) {
            throw $this->createNotFoundException('Archivo no encontrado.');
        }

        return new BinaryFileResponse(
            $ruta,
            200,
            ['Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $doc->getNombreOriginal(),
            )]
        );
    }

    #[Route('/{id}/documentos/{docId}/eliminar', name: 'documento_eliminar', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function documentoEliminar(Request $request, Trabajador $trabajador, string $docId): Response
    {
        if (!$this->isCsrfTokenValid('doc_eliminar_' . $docId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $doc = $this->documentoRepository->find($docId);
        if ($doc && $doc->getTrabajador() === $trabajador) {
            $this->trabajadorService->eliminarDocumento($doc);
            $this->addFlash('success', 'Documento eliminado.');
        }

        return $this->redirectToRoute('app_trabajador_show', ['id' => $trabajador->getId(), 'tab' => 'documentos']);
    }

    // ─── Disponibilidad ───────────────────────────────────────────────────────

    #[Route('/{id}/disponibilidad/nueva', name: 'disponibilidad_nueva', methods: ['POST'])]
    public function disponibilidadNueva(Request $request, Trabajador $trabajador): Response
    {
        $disp = new DisponibilidadTrabajador();
        $form = $this->createForm(DisponibilidadTrabajadorType::class, $disp);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->trabajadorService->registrarDisponibilidad($trabajador, $disp);
                $this->addFlash('success', 'Disponibilidad registrada.');
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Error: ' . $e->getMessage());
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_trabajador_show', ['id' => $trabajador->getId(), 'tab' => 'disponibilidad']);
    }

    #[Route('/{id}/disponibilidad/{dispId}/eliminar', name: 'disponibilidad_eliminar', methods: ['POST'])]
    public function disponibilidadEliminar(Request $request, Trabajador $trabajador, int $dispId): Response
    {
        if (!$this->isCsrfTokenValid('disp_eliminar_' . $dispId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $disp = $this->disponibilidadRepository->find($dispId);
        if ($disp && $disp->getTrabajador() === $trabajador) {
            $this->trabajadorService->eliminarDisponibilidad($disp);
            $this->addFlash('success', 'Disponibilidad eliminada.');
        }

        return $this->redirectToRoute('app_trabajador_show', ['id' => $trabajador->getId(), 'tab' => 'disponibilidad']);
    }

    // ─── Horas trabajadas / exportación ──────────────────────────────────────

    #[Route('/{id}/horas', name: 'horas', methods: ['GET'])]
    public function horas(Request $request, Trabajador $trabajador): Response
    {
        $desde = new \DateTime($request->query->get('desde', 'first day of this month'));
        $hasta = new \DateTime($request->query->get('hasta', 'last day of this month'));

        $resumen = $this->trabajadorService->calcularHorasTrabajadas($trabajador, $desde, $hasta);

        return $this->render('trabajador/horas.html.twig', [
            'trabajador' => $trabajador,
            'resumen'    => $resumen,
            'desde'      => $desde,
            'hasta'      => $hasta,
        ]);
    }

    #[Route('/{id}/horas/exportar', name: 'horas_exportar', methods: ['GET'])]
    public function horasExportar(Request $request, Trabajador $trabajador): Response
    {
        $desde = new \DateTime($request->query->get('desde', 'first day of this month'));
        $hasta = new \DateTime($request->query->get('hasta', 'last day of this month'));

        $resumen  = $this->trabajadorService->calcularHorasTrabajadas($trabajador, $desde, $hasta);
        $csv      = $this->trabajadorService->generarCsvHoras($trabajador, $resumen);
        $filename = sprintf(
            'horas_%s_%s_%s.csv',
            strtolower(str_replace(' ', '_', $trabajador->getApellidos())),
            $desde->format('Ym'),
            $hasta->format('Ym'),
        );

        $response = new StreamedResponse(function () use ($csv) {
            echo $csv;
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
        ));

        return $response;
    }
}
