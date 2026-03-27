<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Trabajador;
use App\Form\TrabajadorType;
use App\Repository\TrabajadorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function show(Trabajador $trabajador): Response
    {
        return $this->render('trabajador/show.html.twig', ['trabajador' => $trabajador]);
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
}
