<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\KernelInterface;

#[Route('/help', name: 'app_help_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class HelpController extends AbstractController
{
    private const MODULOS_VALIDOS = ['dashboard', 'pacientes', 'turnos', 'personal', 'finanzas', 'configuracion'];

    public function __construct(
        #[Autowire(service: 'doctrine.orm.tenant_entity_manager')]
        private readonly EntityManagerInterface $tenantEm,
        private readonly KernelInterface $kernel,
    ) {}

    #[Route('/tour/{modulo}/completar', name: 'tour_completar', methods: ['POST'])]
    public function tourCompletar(string $modulo, Request $request): JsonResponse
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

        $this->tenantEm->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/tours/reset', name: 'tours_reset', methods: ['POST'])]
    public function toursReset(Request $request): Response
    {
        $token = $request->query->get('_token', $request->request->get('_token'));

        if (!$this->isCsrfTokenValid('help_reset', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException();
        }

        /** @var \App\Entity\Tenant\User $user */
        $user = $this->getUser();
        $user->resetearTours();

        $this->tenantEm->flush();

        $this->addFlash('success', 'Tours de ayuda reiniciados. Verás los tours nuevamente al visitar cada sección.');

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/ayuda', name: 'ayuda', methods: ['GET'])]
    public function ayuda(): Response
    {
        return $this->render('help/ayuda.html.twig');
    }

    #[Route('/manual-usuario', name: 'manual_usuario', methods: ['GET'])]
    public function manualUsuario(): Response
    {
        $path = $this->kernel->getProjectDir() . '/../docs/manual_usuario.html';
        $contenido = file_exists($path) ? file_get_contents($path) : '<p class="text-muted">Manual no disponible.</p>';

        return $this->render('help/manual_html.html.twig', [
            'titulo'   => 'Manual de usuario',
            'contenido' => $contenido,
        ]);
    }

    #[Route('/manual-flujo', name: 'manual_flujo', methods: ['GET'])]
    public function manualFlujo(): Response
    {
        $path = $this->kernel->getProjectDir() . '/../docs/manual_flujo_operativo.md';
        $markdown = file_exists($path) ? file_get_contents($path) : '> Manual no disponible.';

        return $this->render('help/manual_markdown.html.twig', [
            'titulo'   => 'Manual de flujo operativo',
            'markdown' => $markdown,
        ]);
    }

    #[Route('/manual-tecnico', name: 'manual_tecnico', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function manualTecnico(): Response
    {
        $path = $this->kernel->getProjectDir() . '/../docs/manual_tecnico.md';
        $markdown = file_exists($path) ? file_get_contents($path) : '> Manual no disponible.';

        return $this->render('help/manual_markdown.html.twig', [
            'titulo'   => 'Manual técnico',
            'markdown' => $markdown,
        ]);
    }
}
