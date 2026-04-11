<?php

declare(strict_types=1);

namespace App\Controller\Default;

use App\Service\ImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/import', name: 'app_import_')]
#[IsGranted('ROLE_ADMIN')]
class ImportController extends AbstractController
{
    public function __construct(
        private readonly ImportService $importService,
    ) {}

    // ─── Index ────────────────────────────────────────────────────────────────

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('import/index.html.twig');
    }

    // ─── Pacientes ────────────────────────────────────────────────────────────

    #[Route('/pacientes', name: 'pacientes', methods: ['GET', 'POST'])]
    public function pacientes(Request $request): Response
    {
        $resultado = null;

        if ($request->isMethod('POST')) {
            $archivo = $request->files->get('archivo');

            if ($archivo === null) {
                $this->addFlash('danger', 'Debes seleccionar un archivo CSV.');
            } else {
                $content   = file_get_contents($archivo->getPathname());
                $resultado = $this->importService->importarPacientes($content);
            }
        }

        return $this->render('import/pacientes.html.twig', [
            'resultado' => $resultado,
        ]);
    }

    #[Route('/pacientes/plantilla', name: 'pacientes_plantilla', methods: ['GET'])]
    public function pacientesPlantilla(): Response
    {
        $csv = $this->importService->plantillaPacientesCsv();

        $response = new StreamedResponse(fn() => print($csv));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT, 'plantilla_pacientes.csv'
        ));

        return $response;
    }

    // ─── Trabajadores ─────────────────────────────────────────────────────────

    #[Route('/trabajadores', name: 'trabajadores', methods: ['GET', 'POST'])]
    public function trabajadores(Request $request): Response
    {
        $resultado = null;

        if ($request->isMethod('POST')) {
            $archivo = $request->files->get('archivo');

            if ($archivo === null) {
                $this->addFlash('danger', 'Debes seleccionar un archivo CSV.');
            } else {
                $content   = file_get_contents($archivo->getPathname());
                $resultado = $this->importService->importarTrabajadores($content);
            }
        }

        return $this->render('import/trabajadores.html.twig', [
            'resultado' => $resultado,
        ]);
    }

    #[Route('/trabajadores/plantilla', name: 'trabajadores_plantilla', methods: ['GET'])]
    public function trabajadoresPlantilla(): Response
    {
        $csv = $this->importService->plantillaTrabajadoresCsv();

        $response = new StreamedResponse(fn() => print($csv));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT, 'plantilla_trabajadores.csv'
        ));

        return $response;
    }
}
