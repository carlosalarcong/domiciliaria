<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant\User;
use App\Form\ChangePasswordType;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\UserService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/usuarios')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly UserRepository $userRepository,
        private readonly PaginatorInterface $paginator,
    ) {}

    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.apellido', 'ASC')
            ->getQuery();

        $usuarios = $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('user/index.html.twig', [
            'usuarios' => $usuarios,
        ]);
    }

    #[Route('/nuevo', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $this->userService->crear($user, $plainPassword);
            $this->addFlash('success', 'Usuario creado correctamente.');

            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'usuario' => $user,
        ]);
    }

    #[Route('/{id}/editar', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userService->actualizar($user);
            $this->addFlash('success', 'Usuario actualizado correctamente.');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
            'usuario' => $user,
        ]);
    }

    #[Route('/{id}/cambiar-password', name: 'app_user_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, User $user): Response
    {
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $this->userService->actualizar($user, $plainPassword);
            $this->addFlash('success', 'Contraseña actualizada correctamente.');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/change_password.html.twig', [
            'form' => $form,
            'usuario' => $user,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_user_toggle_activo', methods: ['POST'])]
    public function toggleActivo(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('toggle-activo-' . $user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');
            return $this->redirectToRoute('app_user_index');
        }

        $this->userService->toggleActivo($user);
        $estado = $user->isActivo() ? 'activado' : 'desactivado';
        $this->addFlash('success', "Usuario {$estado} correctamente.");

        return $this->redirectToRoute('app_user_index');
    }
}
