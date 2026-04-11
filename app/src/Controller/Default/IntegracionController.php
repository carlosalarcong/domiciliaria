<?php

declare(strict_types=1);

namespace App\Controller\Default;

use App\Entity\Tenant\WebhookDelivery;
use App\Entity\Tenant\WebhookSuscripcion;
use App\Enum\WebhookEvento;
use App\Form\WebhookSuscripcionType;
use App\Message\WebhookDeliveryMessage;
use App\Repository\Tenant\WebhookDeliveryRepository;
use App\Repository\Tenant\WebhookSuscripcionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/integraciones', name: 'app_integracion_')]
#[IsGranted('ROLE_ADMIN')]
class IntegracionController extends AbstractController
{
    public function __construct(
        private readonly WebhookSuscripcionRepository $suscripcionRepository,
        private readonly WebhookDeliveryRepository $deliveryRepository,
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
        private readonly MessageBusInterface $bus,
        private readonly HttpClientInterface $httpClient,
    ) {}

    // ── Suscripciones ─────────────────────────────────────────────────────────

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $suscripciones = $this->suscripcionRepository->findAllOrderedByNombre();

        $stats = [];
        foreach ($suscripciones as $s) {
            $stats[(string) $s->getId()] = $this->deliveryRepository->countByEstado($s);
        }

        return $this->render('integracion/index.html.twig', [
            'suscripciones' => $suscripciones,
            'stats'         => $stats,
        ]);
    }

    #[Route('/nueva', name: 'nueva', methods: ['GET', 'POST'])]
    public function nueva(Request $request): Response
    {
        $suscripcion = new WebhookSuscripcion();
        $form = $this->createForm(WebhookSuscripcionType::class, $suscripcion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$form->get('secret')->getData()) {
                $suscripcion->setSecret(bin2hex(random_bytes(16)));
            }
            $this->em->persist($suscripcion);
            $this->em->flush();

            $this->addFlash('success', "Integración \"{$suscripcion->getNombre()}\" creada.");
            return $this->redirectToRoute('app_integracion_show', ['id' => $suscripcion->getId()]);
        }

        return $this->render('integracion/nueva.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(WebhookSuscripcion $suscripcion, Request $request): Response
    {
        $filtroEstado = $request->query->get('estado', '');

        $qb = $this->deliveryRepository->findQueryBuilder(
            $suscripcion,
            $filtroEstado ?: null,
        );
        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 20);

        return $this->render('integracion/show.html.twig', [
            'suscripcion'  => $suscripcion,
            'pagination'   => $pagination,
            'filtroEstado' => $filtroEstado,
            'estados'      => [
                WebhookDelivery::ESTADO_PENDIENTE,
                WebhookDelivery::ESTADO_ENTREGADO,
                WebhookDelivery::ESTADO_FALLIDO,
            ],
        ]);
    }

    #[Route('/{id}/editar', name: 'editar', methods: ['GET', 'POST'])]
    public function editar(Request $request, WebhookSuscripcion $suscripcion): Response
    {
        $form = $this->createForm(WebhookSuscripcionType::class, $suscripcion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $suscripcion->setActualizadoEn(new \DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', 'Integración actualizada.');
            return $this->redirectToRoute('app_integracion_show', ['id' => $suscripcion->getId()]);
        }

        return $this->render('integracion/editar.html.twig', [
            'form'         => $form,
            'suscripcion'  => $suscripcion,
        ]);
    }

    #[Route('/{id}/eliminar', name: 'eliminar', methods: ['POST'])]
    public function eliminar(Request $request, WebhookSuscripcion $suscripcion): Response
    {
        if (!$this->isCsrfTokenValid('wh_' . $suscripcion->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->em->remove($suscripcion);
        $this->em->flush();

        $this->addFlash('success', 'Integración eliminada.');
        return $this->redirectToRoute('app_integracion_index');
    }

    // ── Delivery actions ──────────────────────────────────────────────────────

    #[Route('/delivery/{id}/reintentar', name: 'delivery_reintentar', methods: ['POST'])]
    public function reintentar(Request $request, WebhookDelivery $delivery): Response
    {
        if (!$this->isCsrfTokenValid('wh_retry_' . $delivery->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $delivery->resetearParaReintento();
        $this->em->flush();

        $this->bus->dispatch(new WebhookDeliveryMessage((string) $delivery->getId()));

        $this->addFlash('success', 'Reintento programado.');
        return $this->redirectToRoute('app_integracion_show', [
            'id' => $delivery->getSuscripcion()->getId(),
        ]);
    }

    // ── Test ping ─────────────────────────────────────────────────────────────

    #[Route('/{id}/ping', name: 'ping', methods: ['POST'])]
    public function ping(Request $request, WebhookSuscripcion $suscripcion): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wh_ping_' . $suscripcion->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token inválido'], 403);
        }

        $payload = json_encode([
            'evento'    => 'ping',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'mensaje'   => 'Test de conexión desde Domiciliaria',
        ], JSON_UNESCAPED_UNICODE);

        $firma = hash_hmac('sha256', $payload, $suscripcion->getSecret());

        try {
            $resp = $this->httpClient->request('POST', $suscripcion->getUrl(), [
                'headers' => [
                    'Content-Type'    => 'application/json',
                    'X-Webhook-Event' => 'ping',
                    'X-Signature'     => 'sha256=' . $firma,
                ],
                'body'    => $payload,
                'timeout' => 5,
            ]);
            return new JsonResponse(['codigo' => $resp->getStatusCode()]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        }
    }
}
