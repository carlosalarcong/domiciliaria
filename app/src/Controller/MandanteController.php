<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant\Mandante;
use App\Form\MandanteType;
use App\Repository\MandanteRepository;
use App\Service\MandanteService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mandantes')]
#[IsGranted('ROLE_COORDINADOR')]
class MandanteController extends AbstractController
{
    public function __construct(
        private readonly MandanteService $mandanteService,
        private readonly MandanteRepository $mandanteRepository,
        private readonly PaginatorInterface $paginator,
    ) {}

    #[Route('', name: 'app_mandante_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $this->mandanteRepository->findQueryBuilder()->getQuery();
        $mandantes = $this->paginator->paginate($query, $request->query->getInt('page', 1), 20);

        return $this->render('mandante/index.html.twig', ['mandantes' => $mandantes]);
    }

    #[Route('/nuevo', name: 'app_mandante_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $mandante = new Mandante();
        $form = $this->createForm(MandanteType::class, $mandante);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mandanteService->crear($mandante);
            $this->addFlash('success', 'Mandante creado correctamente.');
            return $this->redirectToRoute('app_mandante_index');
        }

        return $this->render('mandante/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_mandante_show', methods: ['GET'])]
    public function show(Mandante $mandante): Response
    {
        return $this->render('mandante/show.html.twig', ['mandante' => $mandante]);
    }

    #[Route('/{id}/editar', name: 'app_mandante_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Mandante $mandante): Response
    {
        $form = $this->createForm(MandanteType::class, $mandante);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mandanteService->actualizar($mandante);
            $this->addFlash('success', 'Mandante actualizado correctamente.');
            return $this->redirectToRoute('app_mandante_show', ['id' => $mandante->getId()]);
        }

        return $this->render('mandante/edit.html.twig', ['form' => $form, 'mandante' => $mandante]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_mandante_toggle_activo', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleActivo(Request $request, Mandante $mandante): Response
    {
        if (!$this->isCsrfTokenValid('toggle-' . $mandante->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');
            return $this->redirectToRoute('app_mandante_index');
        }

        try {
            $this->mandanteService->toggleActivo($mandante);
            $this->addFlash('success', 'Estado del mandante actualizado.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_mandante_index');
    }
}
