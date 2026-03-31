<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant\Notificacion;
use App\Repository\NotificacionRepository;
use App\Service\NotificacionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notificaciones', name: 'app_notificacion_')]
#[IsGranted('ROLE_USER')]
class NotificacionController extends AbstractController
{
    public function __construct(
        private readonly NotificacionRepository $repository,
        private readonly NotificacionService $service,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var \App\Entity\Tenant\User $user */
        $user = $this->getUser();

        return $this->render('notificacion/index.html.twig', [
            'notificaciones' => $this->repository->findRecientesByUser($user),
            'totalNoLeidas'  => $this->repository->countNoLeidasByUser($user),
        ]);
    }

    #[Route('/{id}/leer', name: 'leer', methods: ['POST'])]
    public function leer(Request $request, Notificacion $notificacion): Response
    {
        if (!$this->isCsrfTokenValid('notif_leer', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($notificacion->getDestinatario() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$notificacion->isLeida()) {
            $notificacion->marcarLeida();
            $this->em->flush();
        }

        if ($notificacion->getUrl()) {
            return $this->redirect($notificacion->getUrl());
        }

        return $this->redirectToRoute('app_notificacion_index');
    }

    #[Route('/leer-todas', name: 'leer_todas', methods: ['POST'])]
    public function leerTodas(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('notif_leer_todas', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var \App\Entity\Tenant\User $user */
        $user = $this->getUser();
        $this->repository->marcarTodasLeidasByUser($user);

        $this->addFlash('success', 'Todas las notificaciones marcadas como leídas.');

        return $this->redirectToRoute('app_notificacion_index');
    }

    /** Endpoint JSON para el badge del header (cuenta no leídas) */
    #[Route('/count', name: 'count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        try {
            /** @var \App\Entity\Tenant\User $user */
            $user = $this->getUser();
            return $this->json(['count' => $this->repository->countNoLeidasByUser($user)]);
        } catch (\Throwable) {
            return $this->json(['count' => 0]);
        }
    }

    /** Endpoint JSON para el dropdown del header (últimas 5 no leídas) */
    #[Route('/recientes', name: 'recientes', methods: ['GET'])]
    public function recientes(): JsonResponse
    {
        /** @var \App\Entity\Tenant\User $user */
        $user = $this->getUser();
        $notificaciones = $this->repository->findNoLeidasByUser($user, 5);

        $data = array_map(fn($n) => [
            'id'        => (string) $n->getId(),
            'tipo'      => $n->getTipo(),
            'titulo'    => $n->getTitulo(),
            'cuerpo'    => $n->getCuerpo(),
            'url'       => $n->getUrl(),
            'creadoEn'  => $n->getCreadoEn()->format('d/m H:i'),
        ], $notificaciones);

        return $this->json([
            'count'          => $this->repository->countNoLeidasByUser($user),
            'notificaciones' => $data,
        ]);
    }
}
