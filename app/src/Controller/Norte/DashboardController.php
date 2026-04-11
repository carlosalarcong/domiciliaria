<?php

declare(strict_types=1);

namespace App\Controller\Norte;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Override del DashboardController para el tenant "norte".
 * Solo existe para verificar que el sistema de resolucion dinamica funciona.
 */
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        return new Response(
            '<h1>[NORTE] Dashboard override activo</h1><p>Este controller es especifico del tenant norte.</p>',
            Response::HTTP_OK,
            ['Content-Type' => 'text/html'],
        );
    }
}
