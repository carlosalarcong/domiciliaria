<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tenant\Paciente;
use App\Enum\EstadoPaciente;
use App\Enum\TipoBitacora;
use App\Enum\TipoComunicacion;
use App\Form\BitacoraType;
use App\Form\CondicionDomicilioType;
use App\Form\ComunicacionType;
use App\Form\PacienteType;
use App\Repository\MandanteRepository;
use App\Repository\PacienteRepository;
use App\Service\PacienteService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pacientes')]
#[IsGranted('ROLE_USER')]
class PacienteController extends AbstractController
{
    public function __construct(
        private readonly PacienteService $pacienteService,
        private readonly PacienteRepository $pacienteRepository,
        private readonly MandanteRepository $mandanteRepository,
        private readonly PaginatorInterface $paginator,
    ) {}

    #[Route('', name: 'app_paciente_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $estado      = $request->query->getString('estado', '');
        $mandanteId  = $request->query->getString('mandante', '');
        $tipoServicio = $request->query->getString('tipo', '');

        $query = $this->pacienteRepository
            ->findQueryBuilder($estado ?: null, $mandanteId ?: null, $tipoServicio ?: null)
            ->getQuery();

        $pacientes  = $this->paginator->paginate($query, $request->query->getInt('page', 1), 20);
        $mandantes  = $this->mandanteRepository->findActivos();

        return $this->render('paciente/index.html.twig', [
            'pacientes'  => $pacientes,
            'mandantes'  => $mandantes,
            'filtros'    => compact('estado', 'mandanteId', 'tipoServicio'),
            'estados'    => EstadoPaciente::cases(),
        ]);
    }

    #[Route('/nuevo', name: 'app_paciente_new', methods: ['GET', 'POST'])]
    #[IsGranted('PACIENTE_CREAR')]
    public function new(Request $request): Response
    {
        $paciente = new Paciente();
        $form = $this->createForm(PacienteType::class, $paciente);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->pacienteService->registrar($paciente);
            $this->addFlash('success', 'Paciente registrado correctamente.');
            return $this->redirectToRoute('app_paciente_show', ['id' => $paciente->getId()]);
        }

        return $this->render('paciente/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_paciente_show', methods: ['GET'])]
    public function show(Paciente $paciente): Response
    {
        $this->denyAccessUnlessGranted('PACIENTE_VER_OPERATIVO');

        $bitacoraForm    = $this->createForm(BitacoraType::class, null, ['action' => $this->generateUrl('app_paciente_bitacora_add', ['id' => $paciente->getId()])]);
        $comunicacionForm = $this->createForm(ComunicacionType::class, null, ['action' => $this->generateUrl('app_paciente_comunicacion_add', ['id' => $paciente->getId()])]);
        $condicionForm   = $this->createForm(CondicionDomicilioType::class, $paciente->getCondicionDomicilio(), ['action' => $this->generateUrl('app_paciente_condicion_edit', ['id' => $paciente->getId()])]);

        return $this->render('paciente/show.html.twig', [
            'paciente'         => $paciente,
            'bitacoraForm'     => $bitacoraForm,
            'comunicacionForm' => $comunicacionForm,
            'condicionForm'    => $condicionForm,
            'puedeVerClinico'  => $this->isGranted('PACIENTE_VER_CLINICO'),
            'puedeEditar'      => $this->isGranted('PACIENTE_EDITAR'),
        ]);
    }

    #[Route('/{id}/editar', name: 'app_paciente_edit', methods: ['GET', 'POST'])]
    #[IsGranted('PACIENTE_EDITAR')]
    public function edit(Request $request, Paciente $paciente): Response
    {
        $form = $this->createForm(PacienteType::class, $paciente);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->pacienteService->actualizar($paciente);
            $this->addFlash('success', 'Paciente actualizado correctamente.');
            return $this->redirectToRoute('app_paciente_show', ['id' => $paciente->getId()]);
        }

        return $this->render('paciente/edit.html.twig', ['form' => $form, 'paciente' => $paciente]);
    }

    #[Route('/{id}/condicion', name: 'app_paciente_condicion_edit', methods: ['POST'])]
    #[IsGranted('PACIENTE_EDITAR')]
    public function editCondicion(Request $request, Paciente $paciente): Response
    {
        $form = $this->createForm(CondicionDomicilioType::class, $paciente->getCondicionDomicilio());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->pacienteService->actualizarCondicion($paciente, $paciente->getCondicionDomicilio());
            $this->addFlash('success', 'Condición del domicilio actualizada.');
        }

        return $this->redirectToRoute('app_paciente_show', ['id' => $paciente->getId(), '_fragment' => 'tab-domicilio']);
    }

    #[Route('/{id}/bitacora', name: 'app_paciente_bitacora_add', methods: ['POST'])]
    public function addBitacora(Request $request, Paciente $paciente): Response
    {
        $form = $this->createForm(BitacoraType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->pacienteService->agregarBitacora(
                $paciente,
                TipoBitacora::from($data['tipo']),
                $data['descripcion'],
                $this->getUser(),
            );
        }

        // Turbo Frame: renderizar solo el listado de bitácora
        $bitacoraForm = $this->createForm(BitacoraType::class, null, [
            'action' => $this->generateUrl('app_paciente_bitacora_add', ['id' => $paciente->getId()]),
        ]);

        return $this->render('paciente/_bitacora.html.twig', [
            'paciente'     => $paciente,
            'bitacoraForm' => $bitacoraForm,
        ]);
    }

    #[Route('/{id}/comunicacion', name: 'app_paciente_comunicacion_add', methods: ['POST'])]
    public function addComunicacion(Request $request, Paciente $paciente): Response
    {
        $form = $this->createForm(ComunicacionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->pacienteService->agregarComunicacion(
                $paciente,
                TipoComunicacion::from($data['tipo']),
                $data['descripcion'],
                $this->getUser(),
                $data['contacto'] ?? null,
            );
        }

        $comunicacionForm = $this->createForm(ComunicacionType::class, null, [
            'action' => $this->generateUrl('app_paciente_comunicacion_add', ['id' => $paciente->getId()]),
        ]);

        return $this->render('paciente/_comunicaciones.html.twig', [
            'paciente'         => $paciente,
            'comunicacionForm' => $comunicacionForm,
        ]);
    }

    #[Route('/{id}/dar-de-baja', name: 'app_paciente_dar_de_baja', methods: ['POST'])]
    #[IsGranted('PACIENTE_EDITAR')]
    public function darDeBaja(Request $request, Paciente $paciente): Response
    {
        if (!$this->isCsrfTokenValid('baja-' . $paciente->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');
            return $this->redirectToRoute('app_paciente_show', ['id' => $paciente->getId()]);
        }

        $motivo = $request->getPayload()->getString('motivo', 'Sin especificar');

        try {
            $this->pacienteService->darDeBaja($paciente, $this->getUser(), $motivo);
            $this->addFlash('success', 'Paciente dado de baja correctamente.');
        } catch (\LogicException $e) {
            $this->addFlash('warning', $e->getMessage());
        }

        return $this->redirectToRoute('app_paciente_show', ['id' => $paciente->getId()]);
    }
}
