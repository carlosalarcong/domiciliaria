<?php

declare(strict_types=1);

namespace App\Controller\Default;

use App\Entity\Tenant\Trabajador;
use App\Entity\Tenant\Turno;
use App\Repository\Tenant\TrabajadorRepository;
use App\Repository\Tenant\TurnoRepository;
use App\Service\TurnoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/campo', name: 'app_campo_')]
#[IsGranted('ROLE_USER')]
class CampoController extends AbstractController
{
    public function __construct(
        private readonly TrabajadorRepository $trabajadorRepository,
        private readonly TurnoRepository $turnoRepository,
        private readonly TurnoService $turnoService,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $trabajador = $this->resolverTrabajador();

        $hoy   = new \DateTime('today');
        $turnos = $trabajador
            ? $this->turnoRepository->findByTrabajadorYFecha($trabajador, $hoy)
            : [];

        $completados = array_filter($turnos, fn(Turno $t) => $t->getRegistroTermino() !== null);
        $enCurso     = array_filter($turnos, fn(Turno $t) => $t->estaEnProceso());

        return $this->render('campo/index.html.twig', [
            'trabajador'  => $trabajador,
            'turnos'      => $turnos,
            'completados' => count($completados),
            'enCurso'     => count($enCurso),
            'hoy'         => $hoy,
        ]);
    }

    #[Route('/turno/{id}', name: 'turno', methods: ['GET'])]
    public function turno(Turno $turno): Response
    {
        $this->denegarSiNoEsMiTurno($turno);

        return $this->render('campo/turno.html.twig', [
            'turno' => $turno,
        ]);
    }

    #[Route('/turno/{id}/iniciar', name: 'turno_iniciar', methods: ['POST'])]
    public function turnoIniciar(Request $request, Turno $turno): Response
    {
        $this->denegarSiNoEsMiTurno($turno);

        if (!$this->isCsrfTokenValid('campo_' . $turno->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($turno->getRegistroInicio() === null) {
            $this->turnoService->registrarAsistencia($turno, 'inicio');
            $this->addFlash('success', 'Turno iniciado. ¡Buen turno!');
        }

        return $this->redirectToRoute('app_campo_turno', ['id' => $turno->getId()]);
    }

    #[Route('/turno/{id}/terminar', name: 'turno_terminar', methods: ['POST'])]
    public function turnoTerminar(Request $request, Turno $turno): Response
    {
        $this->denegarSiNoEsMiTurno($turno);

        if (!$this->isCsrfTokenValid('campo_' . $turno->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($turno->getRegistroInicio() !== null && $turno->getRegistroTermino() === null) {
            $this->turnoService->registrarAsistencia($turno, 'termino');
            $this->addFlash('success', 'Turno finalizado. ¡Gracias por tu trabajo!');
        }

        return $this->redirectToRoute('app_campo_index');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function resolverTrabajador(): ?Trabajador
    {
        $user = $this->getUser();
        if ($user === null) {
            return null;
        }

        // ADMIN y COORDINADOR pueden ver la vista pero sin turnos propios
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COORDINADOR')) {
            return $this->trabajadorRepository->findOneBy(['user' => $user]);
        }

        return $this->trabajadorRepository->findOneBy(['user' => $user]);
    }

    private function denegarSiNoEsMiTurno(Turno $turno): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COORDINADOR')) {
            return;
        }

        $trabajador = $this->resolverTrabajador();
        if ($trabajador === null || $turno->getTrabajador()?->getId() !== $trabajador->getId()) {
            throw $this->createAccessDeniedException('Este turno no te pertenece.');
        }
    }
}
