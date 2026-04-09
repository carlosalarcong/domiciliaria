<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/help', name: 'app_help_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class HelpController extends AbstractController
{
    private const MODULOS_VALIDOS = ['dashboard', 'pacientes', 'turnos', 'personal', 'finanzas'];

    #[Route('/tour/{modulo}/completar', name: 'tour_completar', methods: ['POST'])]
    public function tourCompletar(string $modulo, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!in_array($modulo, self::MODULOS_VALIDOS, true)) {
            return $this->json(['ok' => false, 'error' => 'Módulo inválido'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($request->getContent(), true);
        $token = is_array($payload) ? ($payload['_token'] ?? null) : null;

        if (!$this->isCsrfTokenValid('help_tour', is_string($token) ? $token : null)) {
            return $this->json(['ok' => false, 'error' => 'CSRF inválido'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Tenant\User $user */
        $user = $this->getUser();
        $user->marcarTourCompletado($modulo);

        $em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/tours/reset', name: 'tours_reset', methods: ['POST'])]
    public function toursReset(Request $request, EntityManagerInterface $em): Response
    {
        $token = $request->query->get('_token', $request->request->get('_token'));

        if (!$this->isCsrfTokenValid('help_reset', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException();
        }

        /** @var \App\Entity\Tenant\User $user */
        $user = $this->getUser();
        $user->resetearTours();

        $em->flush();

        $this->addFlash('success', 'Tours de ayuda reiniciados. Verás los tours nuevamente al visitar cada sección.');

        return $this->redirectToRoute('app_dashboard');
    }
}
