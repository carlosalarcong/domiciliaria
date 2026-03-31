<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant\EventoAdverso;
use App\Enum\EstadoEvento;
use App\Enum\GravedadEvento;
use App\Form\EventoAdversoType;
use App\Form\SeguimientoEventoType;
use App\Repository\EventoAdversoRepository;
use App\Repository\UserRepository;
use App\Service\EventoAdversoService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/eventos-adversos', name: 'app_evento_')]
#[IsGranted('ROLE_COORDINADOR')]
class EventoAdversoController extends AbstractController
{
    public function __construct(
        private readonly EventoAdversoRepository $repository,
        private readonly EventoAdversoService $service,
        private readonly PaginatorInterface $paginator,
        private readonly UserRepository $userRepository,
    ) {}

    private function responsables(): array
    {
        return $this->userRepository->findActivosByRoles(['ROLE_ADMIN', 'ROLE_COORDINADOR']);
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $estadoVal   = $request->query->get('estado');
        $gravedadVal = $request->query->get('gravedad');

        $estado   = $estadoVal   ? EstadoEvento::tryFrom($estadoVal)   : null;
        $gravedad = $gravedadVal ? GravedadEvento::tryFrom($gravedadVal) : null;

        $qb = $this->repository->findQueryBuilder($estado, $gravedad);
        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 20);
        $conteos    = $this->repository->countByEstado();

        return $this->render('evento/index.html.twig', [
            'pagination'      => $pagination,
            'conteos'         => $conteos,
            'filtroEstado'    => $estadoVal,
            'filtroGravedad'  => $gravedadVal,
            'estados'         => EstadoEvento::cases(),
            'gravedades'      => GravedadEvento::cases(),
        ]);
    }

    #[Route('/nuevo', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $evento = new EventoAdverso();
        $form   = $this->createForm(EventoAdversoType::class, $evento, ['responsables' => $this->responsables()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->registrar($evento, $this->getUser());
            $this->addFlash('success', 'Evento adverso registrado correctamente.');

            return $this->redirectToRoute('app_evento_show', ['id' => $evento->getId()]);
        }

        return $this->render('evento/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET', 'POST'])]
    public function show(Request $request, EventoAdverso $evento): Response
    {
        $formSeg = $this->createForm(SeguimientoEventoType::class);
        $formSeg->handleRequest($request);

        if ($formSeg->isSubmitted() && $formSeg->isValid()) {
            $this->service->agregarSeguimiento(
                $evento,
                $formSeg->get('nota')->getData(),
                $this->getUser(),
            );
            $this->addFlash('success', 'Seguimiento agregado.');

            return $this->redirectToRoute('app_evento_show', ['id' => $evento->getId()]);
        }

        return $this->render('evento/show.html.twig', [
            'evento'  => $evento,
            'formSeg' => $formSeg,
        ]);
    }

    #[Route('/{id}/editar', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EventoAdverso $evento): Response
    {
        if ($evento->getEstado() === EstadoEvento::CERRADO) {
            $this->addFlash('warning', 'No se puede editar un evento cerrado.');

            return $this->redirectToRoute('app_evento_show', ['id' => $evento->getId()]);
        }

        $form = $this->createForm(EventoAdversoType::class, $evento);
        $responsableAnterior = $evento->getResponsable();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->actualizar($evento, $responsableAnterior);
            $this->addFlash('success', 'Evento actualizado correctamente.');

            return $this->redirectToRoute('app_evento_show', ['id' => $evento->getId()]);
        }

        return $this->render('evento/edit.html.twig', [
            'evento' => $evento,
            'form'   => $form,
        ]);
    }

    #[Route('/{id}/en-revision', name: 'en_revision', methods: ['POST'])]
    public function enRevision(Request $request, EventoAdverso $evento): Response
    {
        if (!$this->isCsrfTokenValid('en_revision_' . $evento->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$evento->getEstado()->puedePonerEnRevision()) {
            $this->addFlash('warning', 'El evento no puede ponerse en revisión en su estado actual.');
            return $this->redirectToRoute('app_evento_show', ['id' => $evento->getId()]);
        }

        $this->service->ponerEnRevision($evento, $this->getUser());
        $this->addFlash('success', 'Evento puesto en revisión.');

        return $this->redirectToRoute('app_evento_show', ['id' => $evento->getId()]);
    }

    #[Route('/{id}/cerrar', name: 'cerrar', methods: ['POST'])]
    public function cerrar(Request $request, EventoAdverso $evento): Response
    {
        if (!$this->isCsrfTokenValid('cerrar_' . $evento->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $observacion = trim($request->request->get('observacion', ''));
        if ($observacion === '') {
            $this->addFlash('danger', 'Debe ingresar una observación de cierre.');

            return $this->redirectToRoute('app_evento_show', ['id' => $evento->getId()]);
        }

        $this->service->cerrar($evento, $observacion, $this->getUser());
        $this->addFlash('success', 'Evento cerrado correctamente.');

        return $this->redirectToRoute('app_evento_show', ['id' => $evento->getId()]);
    }
}
