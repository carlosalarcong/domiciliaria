<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Turno;
use App\Form\ReemplazoType;
use App\Form\TurnoType;
use App\Repository\TurnoRepository;
use App\Service\TurnoService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/turnos', name: 'app_turno_')]
#[IsGranted('ROLE_COORDINADOR')]
class TurnoController extends AbstractController
{
    public function __construct(
        private readonly TurnoRepository $repository,
        private readonly TurnoService $turnoService,
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('turno/index.html.twig');
    }

    #[Route('/nuevo', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $turno = new Turno();
        $form  = $this->createForm(TurnoType::class, $turno);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->turnoService->crear($turno, $this->getUser());
                $this->addFlash('success', 'Turno creado correctamente.');

                return $this->redirectToRoute('app_turno_show', ['id' => $turno->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('turno/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Turno $turno): Response
    {
        return $this->render('turno/show.html.twig', ['turno' => $turno]);
    }

    #[Route('/{id}/detalle', name: 'detalle', methods: ['GET'])]
    public function detalle(Turno $turno): Response
    {
        return $this->render('turno/_detalle.html.twig', ['turno' => $turno]);
    }

    #[Route('/{id}/editar', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Turno $turno): Response
    {
        $form = $this->createForm(TurnoType::class, $turno);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($turno->getTrabajador() !== null) {
                    $this->turnoService->verificarDisponibilidad(
                        $turno->getTrabajador(),
                        $turno->getFecha(),
                        $turno->getHoraInicio(),
                        $turno->getHoraTermino(),
                    );
                }
                $this->em->flush();
                $this->addFlash('success', 'Turno actualizado correctamente.');

                return $this->redirectToRoute('app_turno_show', ['id' => $turno->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('turno/edit.html.twig', [
            'turno' => $turno,
            'form'  => $form,
        ]);
    }

    #[Route('/{id}/reemplazo', name: 'reemplazo', methods: ['GET', 'POST'])]
    public function reemplazo(Request $request, Turno $turno): Response
    {
        $form = $this->createForm(ReemplazoType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->turnoService->asignarReemplazo(
                    $turno,
                    $form->get('reemplazo')->getData(),
                    $form->get('motivo')->getData(),
                );
                $this->addFlash('success', 'Reemplazo asignado correctamente.');

                return $this->redirectToRoute('app_turno_show', ['id' => $turno->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('turno/reemplazo.html.twig', [
            'turno' => $turno,
            'form'  => $form,
        ]);
    }

    #[Route('/{id}/asistencia/{tipo}', name: 'asistencia', methods: ['POST'],
        requirements: ['tipo' => 'inicio|termino'])]
    public function asistencia(Request $request, Turno $turno, string $tipo): Response
    {
        if (!$this->isCsrfTokenValid('asistencia_' . $turno->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->turnoService->registrarAsistencia($turno, $tipo);
            $this->addFlash('success', $tipo === 'inicio' ? 'Turno iniciado.' : 'Turno finalizado.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_turno_show', ['id' => $turno->getId()]);
    }

    // ─── API para FullCalendar ────────────────────────────────────────────────

    #[Route('/api/eventos', name: 'api_eventos', methods: ['GET'])]
    public function apiEventos(Request $request): JsonResponse
    {
        $desde = new \DateTime($request->query->get('start', 'today'));
        $hasta = new \DateTime($request->query->get('end', '+30 days'));

        return $this->json($this->repository->findEventosCalendario($desde, $hasta));
    }

    // ─── API disponibilidad de trabajador ─────────────────────────────────────

    #[Route('/api/disponibilidad', name: 'api_disponibilidad', methods: ['GET'])]
    public function apiDisponibilidad(Request $request): JsonResponse
    {
        $trabajadorId = $request->query->get('trabajador');
        $fecha        = $request->query->get('fecha');
        $horaInicio   = $request->query->get('horaInicio');
        $horaTermino  = $request->query->get('horaTermino');
        $excludeId    = $request->query->get('exclude');

        if (!$trabajadorId || !$fecha || !$horaInicio || !$horaTermino) {
            return $this->json(['disponible' => true]);
        }

        $trabajador = $this->em->find(\App\Entity\Trabajador::class, $trabajadorId);
        if (!$trabajador) {
            return $this->json(['disponible' => true]);
        }

        try {
            $fechaDt  = new \DateTime($fecha);
            $inicioDt = new \DateTime($horaInicio);
            $terminoDt = new \DateTime($horaTermino);

            $turnos = $this->repository->findByTrabajadorYFecha($trabajador, $fechaDt);
            foreach ($turnos as $t) {
                if ($excludeId && (string) $t->getId() === $excludeId) {
                    continue;
                }
                if ($t->getEstado() === \App\Enum\EstadoTurno::DESCUBIERTO) {
                    continue;
                }
                if ($inicioDt < $t->getHoraTermino() && $terminoDt > $t->getHoraInicio()) {
                    return $this->json([
                        'disponible' => false,
                        'mensaje'    => sprintf(
                            'El trabajador ya tiene turno entre %s y %s.',
                            $t->getHoraInicio()->format('H:i'),
                            $t->getHoraTermino()->format('H:i'),
                        ),
                    ]);
                }
            }
        } catch (\Throwable) {
            return $this->json(['disponible' => true]);
        }

        return $this->json(['disponible' => true]);
    }
}
