<?php

declare(strict_types=1);

namespace App\Controller\Default\Api;

use App\Enum\EstadoFactura;
use App\Repository\Tenant\FacturaRepository;
use App\Repository\Tenant\LiquidacionMensualRepository;
use App\Repository\Tenant\PacienteRepository;
use App\Repository\Tenant\TrabajadorRepository;
use App\Repository\Tenant\TurnoRepository;
use App\Service\ConfiguracionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1', name: 'api_v1_')]
#[IsGranted('ROLE_API')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly PacienteRepository           $pacienteRepository,
        private readonly TurnoRepository              $turnoRepository,
        private readonly TrabajadorRepository         $trabajadorRepository,
        private readonly LiquidacionMensualRepository $liquidacionRepository,
        private readonly FacturaRepository            $facturaRepository,
        private readonly ConfiguracionService         $configuracionService,
    ) {}

    private function checkApiActiva(): ?JsonResponse
    {
        $config = $this->configuracionService->get();
        if (!$config->isIntegracionApiActiva()) {
            return $this->json(['error' => 'API deshabilitada.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return null;
    }

    // ─── Pacientes ────────────────────────────────────────────────────────────

    #[Route('/pacientes', name: 'pacientes', methods: ['GET'])]
    #[IsGranted('ROLE_API_PACIENTES')]
    public function pacientes(Request $request): JsonResponse
    {
        if ($error = $this->checkApiActiva()) { return $error; }

        $estado   = $request->query->getString('estado', '');
        $mandante = $request->query->getString('mandante', '');
        $tipo     = $request->query->getString('tipo', '');
        $page     = max(1, $request->query->getInt('page', 1));
        $limit    = min(200, max(1, $request->query->getInt('limit', 50)));

        $qb = $this->pacienteRepository
            ->findQueryBuilder($estado ?: null, $mandante ?: null, $tipo ?: null);

        $total = $this->pacienteRepository
            ->findQueryBuilder($estado ?: null, $mandante ?: null, $tipo ?: null)
            ->select('COUNT(p.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $pacientes = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();

        $data = array_map(fn($p) => [
            'id'           => (string) $p->getId(),
            'nombres'      => $p->getNombres(),
            'apellidos'    => $p->getApellidos(),
            'tipo_servicio' => $p->getTipoServicio()->value,
            'estado'       => $p->getEstado()->value,
            'mandante'     => $p->getMandante()?->getNombre(),
            'mandante_id'  => $p->getMandante() ? (string) $p->getMandante()->getId() : null,
            'fecha_ingreso' => $p->getFechaIngreso()?->format('Y-m-d'),
            'fecha_termino' => $p->getFechaTermino()?->format('Y-m-d'),
            'comuna'       => $p->getComuna(),
            'region'       => $p->getRegion(),
        ], $pacientes);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => (int) $total, 'page' => $page, 'limit' => $limit],
        ]);
    }

    #[Route('/pacientes/{id}', name: 'paciente_show', methods: ['GET'])]
    #[IsGranted('ROLE_API_PACIENTES')]
    public function pacienteShow(string $id): JsonResponse
    {
        if ($error = $this->checkApiActiva()) { return $error; }

        $p = $this->pacienteRepository->find($id);

        if ($p === null) {
            return $this->json(['error' => 'Paciente no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id'              => (string) $p->getId(),
            'nombres'         => $p->getNombres(),
            'apellidos'       => $p->getApellidos(),
            'tipo_servicio'   => $p->getTipoServicio()->value,
            'estado'          => $p->getEstado()->value,
            'mandante'        => $p->getMandante()?->getNombre(),
            'mandante_id'     => $p->getMandante() ? (string) $p->getMandante()->getId() : null,
            'fecha_nacimiento' => $p->getFechaNacimiento()?->format('Y-m-d'),
            'edad'            => $p->getEdad(),
            'direccion'       => $p->getDireccion(),
            'comuna'          => $p->getComuna(),
            'region'          => $p->getRegion(),
            'telefono'        => $p->getTelefono(),
            'tutor_nombre'    => $p->getTutorNombre(),
            'tutor_telefono'  => $p->getTutorTelefono(),
            'tutor_relacion'  => $p->getTutorRelacion(),
            'fecha_ingreso'   => $p->getFechaIngreso()?->format('Y-m-d'),
            'fecha_termino'   => $p->getFechaTermino()?->format('Y-m-d'),
        ]);
    }

    // ─── Turnos ───────────────────────────────────────────────────────────────

    #[Route('/turnos', name: 'turnos', methods: ['GET'])]
    #[IsGranted('ROLE_API_TURNOS')]
    public function turnos(Request $request): JsonResponse
    {
        if ($error = $this->checkApiActiva()) { return $error; }

        $desde = new \DateTime($request->query->getString('desde', 'first day of this month'));
        $hasta = new \DateTime($request->query->getString('hasta', 'last day of this month'));

        $turnos = $this->turnoRepository->findByRangoFecha($desde, $hasta);

        $data = array_map(fn($t) => [
            'id'            => (string) $t->getId(),
            'fecha'         => $t->getFecha()?->format('Y-m-d'),
            'hora_inicio'   => $t->getHoraInicio()?->format('H:i'),
            'hora_termino'  => $t->getHoraTermino()?->format('H:i'),
            'tipo'          => $t->getTipoTurno()->value,
            'estado'        => $t->getEstado()->value,
            'paciente_id'   => $t->getPaciente() ? (string) $t->getPaciente()->getId() : null,
            'paciente'      => $t->getPaciente()?->getNombreCompleto(),
            'trabajador_id' => $t->getTrabajador() ? (string) $t->getTrabajador()->getId() : null,
            'trabajador'    => $t->getTrabajador()?->getNombreCompleto(),
            'es_reemplazo'  => $t->isEsReemplazo(),
            'registro_inicio'  => $t->getRegistroInicio()?->format('Y-m-d H:i:s'),
            'registro_termino' => $t->getRegistroTermino()?->format('Y-m-d H:i:s'),
        ], $turnos);

        return $this->json([
            'data'   => $data,
            'meta'   => [
                'desde' => $desde->format('Y-m-d'),
                'hasta' => $hasta->format('Y-m-d'),
                'total' => count($data),
            ],
        ]);
    }

    // ─── Trabajadores ─────────────────────────────────────────────────────────

    #[Route('/trabajadores', name: 'trabajadores', methods: ['GET'])]
    #[IsGranted('ROLE_API_TRABAJADORES')]
    public function trabajadores(Request $request): JsonResponse
    {
        if ($error = $this->checkApiActiva()) { return $error; }

        $soloActivos = $request->query->getBoolean('activos', true);

        $trabajadores = $soloActivos
            ? $this->trabajadorRepository->findActivos()
            : $this->trabajadorRepository->findQueryBuilder()->getQuery()->getResult();

        $data = array_map(fn($t) => [
            'id'          => (string) $t->getId(),
            'nombres'     => $t->getNombres(),
            'apellidos'   => $t->getApellidos(),
            'rut'         => $t->getRut(),
            'perfil'      => $t->getPerfil()->value,
            'email'       => $t->getEmail(),
            'telefono'    => $t->getTelefono(),
            'estado'      => $t->getEstado()->value,
            'fecha_ingreso' => $t->getFechaIngreso()?->format('Y-m-d'),
        ], $trabajadores);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    #[Route('/trabajadores/{id}', name: 'trabajador_show', methods: ['GET'])]
    #[IsGranted('ROLE_API_TRABAJADORES')]
    public function trabajadorShow(string $id): JsonResponse
    {
        if ($error = $this->checkApiActiva()) { return $error; }

        $t = $this->trabajadorRepository->find($id);

        if ($t === null) {
            return $this->json(['error' => 'Trabajador no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id'           => (string) $t->getId(),
            'nombres'      => $t->getNombres(),
            'apellidos'    => $t->getApellidos(),
            'rut'          => $t->getRut(),
            'perfil'       => $t->getPerfil()->value,
            'email'        => $t->getEmail(),
            'telefono'     => $t->getTelefono(),
            'direccion'    => $t->getDireccion(),
            'estado'       => $t->getEstado()->value,
            'fecha_ingreso' => $t->getFechaIngreso()?->format('Y-m-d'),
            'fecha_salida'  => $t->getFechaSalida()?->format('Y-m-d'),
        ]);
    }

    // ─── Liquidaciones ────────────────────────────────────────────────────────

    #[Route('/liquidaciones', name: 'liquidaciones', methods: ['GET'])]
    #[IsGranted('ROLE_API_LIQUIDACIONES')]
    public function liquidaciones(Request $request): JsonResponse
    {
        if ($error = $this->checkApiActiva()) { return $error; }

        $anio = $request->query->getInt('anio', (int) date('Y'));
        $mes  = $request->query->getInt('mes', 0);

        $qb = $this->liquidacionRepository->createQueryBuilder('l')
            ->leftJoin('l.trabajador', 't')->addSelect('t')
            ->where('l.anio = :anio')
            ->setParameter('anio', $anio)
            ->orderBy('l.mes', 'DESC')
            ->addOrderBy('t.apellidos', 'ASC');

        if ($mes > 0) {
            $qb->andWhere('l.mes = :mes')->setParameter('mes', $mes);
        }

        $liquidaciones = $qb->getQuery()->getResult();

        $data = array_map(fn($l) => [
            'id'            => (string) $l->getId(),
            'trabajador_id' => $l->getTrabajador() ? (string) $l->getTrabajador()->getId() : null,
            'trabajador'    => $l->getTrabajador()?->getNombreCompleto(),
            'rut_trabajador' => $l->getTrabajador()?->getRut(),
            'anio'          => $l->getAnio(),
            'mes'           => $l->getMes(),
            'periodo'       => $l->getPeriodoLabel(),
            'total_turnos'  => $l->getTotalTurnos(),
            'total_horas'   => $l->getTotalHoras(),
            'monto_total'   => (float) $l->getMontoTotal(),
            'estado'        => $l->getEstado()->value,
            'fecha_pago'    => $l->getFechaPago()?->format('Y-m-d'),
        ], $liquidaciones);

        return $this->json([
            'data' => $data,
            'meta' => ['anio' => $anio, 'total' => count($data)],
        ]);
    }

    // ─── Facturas ─────────────────────────────────────────────────────────────

    #[Route('/facturas', name: 'facturas', methods: ['GET'])]
    #[IsGranted('ROLE_API_FACTURAS')]
    public function facturas(Request $request): JsonResponse
    {
        if ($error = $this->checkApiActiva()) { return $error; }

        $anio         = $request->query->getInt('anio', (int) date('Y'));
        $estadoValue  = $request->query->getString('estado', '');
        $estadoEnum   = $estadoValue !== '' ? EstadoFactura::tryFrom($estadoValue) : null;

        $facturas = $this->facturaRepository
            ->findQueryBuilder($anio, $estadoEnum)
            ->getQuery()
            ->getResult();

        $data = array_map(fn($f) => [
            'id'               => (string) $f->getId(),
            'numero_factura'   => $f->getNumeroFactura(),
            'mandante_id'      => $f->getMandante() ? (string) $f->getMandante()->getId() : null,
            'mandante'         => $f->getMandante()?->getNombre(),
            'anio'             => $f->getAnio(),
            'mes'              => $f->getMes(),
            'periodo'          => $f->getPeriodoLabel(),
            'total_turnos'     => $f->getTotalTurnos(),
            'monto_neto'       => (float) $f->getMontoNeto(),
            'porcentaje_iva'   => (float) $f->getPorcentajeIva(),
            'monto_iva'        => (float) $f->getMontoIva(),
            'monto_total'      => (float) $f->getMontoTotal(),
            'estado'           => $f->getEstado()->value,
            'fecha_emision'    => $f->getFechaEmision()?->format('Y-m-d'),
            'fecha_vencimiento' => $f->getFechaVencimiento()?->format('Y-m-d'),
            'fecha_pago'       => $f->getFechaPago()?->format('Y-m-d'),
        ], $facturas);

        return $this->json([
            'data' => $data,
            'meta' => ['anio' => $anio, 'total' => count($data)],
        ]);
    }
}
