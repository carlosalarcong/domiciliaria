<?php

declare(strict_types=1);

namespace App\Controller\Default;

use App\Form\ConfiguracionType;
use App\Service\ConfiguracionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/configuracion', name: 'app_configuracion_')]
#[IsGranted('ROLE_ADMIN')]
class ConfiguracionController extends AbstractController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService,
        #[Autowire(service: 'doctrine.orm.tenant_entity_manager')]
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $config = $this->configuracionService->get();
        $form = $this->createForm(ConfiguracionType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Configuración guardada correctamente.');

            return $this->redirectToRoute('app_configuracion_index');
        }

        return $this->render('configuracion/index.html.twig', [
            'form'   => $form,
            'config' => $config,
        ]);
    }
}
