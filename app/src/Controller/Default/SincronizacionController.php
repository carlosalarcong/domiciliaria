<?php

declare(strict_types=1);

namespace App\Controller\Default;

use App\Entity\Tenant\SincronizacionExterna;
use App\Form\SincronizacionExternaType;
use App\Message\EjecutarSincronizacionMessage;
use App\MessageHandler\EjecutarSincronizacionHandler;
use App\Repository\Tenant\SincronizacionExternaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/integracion/sincronizaciones', name: 'app_sincronizacion_')]
#[IsGranted('ROLE_ADMIN')]
class SincronizacionController extends AbstractController
{
    public function __construct(
        private readonly SincronizacionExternaRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
        private readonly EjecutarSincronizacionHandler $ejecutarSincronizacionHandler,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pagination = $this->paginator->paginate(
            $this->repository->findQueryBuilder(),
            $request->query->getInt('page', 1),
            20,
        );

        return $this->render('sincronizacion/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/nueva', name: 'nueva', methods: ['GET', 'POST'])]
    public function nueva(Request $request): Response
    {
        $sincronizacion = new SincronizacionExterna();
        $form = $this->createForm(SincronizacionExternaType::class, $sincronizacion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $usuario = $this->getUser();
            if ($usuario instanceof \App\Entity\Tenant\User) {
                $sincronizacion->setCreadoPor($usuario);
            }

            $this->em->persist($sincronizacion);
            $this->em->flush();

            $this->addFlash('success', 'Sincronización creada correctamente.');

            return $this->redirectToRoute('app_sincronizacion_index');
        }

        return $this->render('sincronizacion/nueva.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/editar', name: 'editar', methods: ['GET', 'POST'])]
    public function editar(Request $request, SincronizacionExterna $sincronizacion): Response
    {
        $form = $this->createForm(SincronizacionExternaType::class, $sincronizacion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Sincronización actualizada.');

            return $this->redirectToRoute('app_sincronizacion_index');
        }

        return $this->render('sincronizacion/editar.html.twig', [
            'form' => $form,
            'sincronizacion' => $sincronizacion,
        ]);
    }

    #[Route('/{id}/ejecutar', name: 'ejecutar_manual', methods: ['POST'])]
    public function ejecutarManual(Request $request, SincronizacionExterna $sincronizacion): Response
    {
        if (!$this->isCsrfTokenValid('sync_ejecutar_' . $sincronizacion->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($sincronizacion->getId() !== null) {
            $this->ejecutarSincronizacionHandler->__invoke(
                new EjecutarSincronizacionMessage($sincronizacion->getId()),
            );
            $this->addFlash('success', 'Sincronización ejecutada manualmente.');
        }

        return $this->redirectToRoute('app_sincronizacion_index');
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, SincronizacionExterna $sincronizacion): Response
    {
        if (!$this->isCsrfTokenValid('sync_toggle_' . $sincronizacion->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $sincronizacion->setActiva(!$sincronizacion->isActiva());
        $this->em->flush();

        $this->addFlash(
            'success',
            $sincronizacion->isActiva() ? 'Sincronización activada.' : 'Sincronización desactivada.',
        );

        return $this->redirectToRoute('app_sincronizacion_index');
    }
}
