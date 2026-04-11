<?php

declare(strict_types=1);

namespace App\Controller\Default;

use App\Entity\Tenant\Log\LogEntry;
use App\Repository\Tenant\AuditLogRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/auditoria', name: 'app_auditoria_')]
#[IsGranted('ROLE_ADMIN')]
class AuditoriaController extends AbstractController
{
    private const ENTITY_LABELS = [
        'App\\Entity\\Tenant\\Paciente'       => 'Paciente',
        'App\\Entity\\Tenant\\Trabajador'     => 'Trabajador',
        'App\\Entity\\Tenant\\Turno'          => 'Turno',
        'App\\Entity\\Tenant\\LiquidacionMensual' => 'Liquidación',
        'App\\Entity\\Tenant\\Mandante'       => 'Mandante',
        'App\\Entity\\Tenant\\EventoAdverso'  => 'Evento Adverso',
        'App\\Entity\\Tenant\\User'           => 'Usuario',
    ];

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly PaginatorInterface $paginator,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filtroEntidad  = $request->query->get('entidad', '');
        $filtroUsuario  = $request->query->get('usuario', '');
        $filtroAccion   = $request->query->get('accion', '');
        $filtroDesde    = $request->query->get('desde', '');
        $filtroHasta    = $request->query->get('hasta', '');

        $em   = $this->registry->getManager('tenant');
        $repo = $em->getRepository(LogEntry::class);

        $qb = $repo->createQueryBuilder('l')
            ->orderBy('l.loggedAt', 'DESC');

        if ($filtroEntidad) {
            $qb->andWhere('l.objectClass = :clase')
               ->setParameter('clase', $filtroEntidad);
        }
        if ($filtroUsuario) {
            $qb->andWhere('l.username LIKE :usuario')
               ->setParameter('usuario', '%' . $filtroUsuario . '%');
        }
        if ($filtroAccion) {
            $qb->andWhere('l.action = :accion')
               ->setParameter('accion', $filtroAccion);
        }
        if ($filtroDesde) {
            $qb->andWhere('l.loggedAt >= :desde')
               ->setParameter('desde', new \DateTime($filtroDesde . ' 00:00:00'));
        }
        if ($filtroHasta) {
            $qb->andWhere('l.loggedAt <= :hasta')
               ->setParameter('hasta', new \DateTime($filtroHasta . ' 23:59:59'));
        }

        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 30);

        return $this->render('auditoria/index.html.twig', [
            'pagination'     => $pagination,
            'entityLabels'   => self::ENTITY_LABELS,
            'filtroEntidad'  => $filtroEntidad,
            'filtroUsuario'  => $filtroUsuario,
            'filtroAccion'   => $filtroAccion,
            'filtroDesde'    => $filtroDesde,
            'filtroHasta'    => $filtroHasta,
        ]);
    }

    #[Route('/accesos', name: 'accesos', methods: ['GET'])]
    public function accesos(Request $request): Response
    {
        $filtroEvento = $request->query->get('evento', '');
        $filtroIp     = $request->query->get('ip', '');
        $filtroEmail  = $request->query->get('email', '');

        $qb = $this->auditLogRepository->findQueryBuilder(
            $filtroEvento ?: null,
            $filtroIp ?: null,
            $filtroEmail ?: null,
        );

        $pagination = $this->paginator->paginate($qb, $request->query->getInt('page', 1), 30);

        return $this->render('auditoria/accesos.html.twig', [
            'pagination'   => $pagination,
            'filtroEvento' => $filtroEvento,
            'filtroIp'     => $filtroIp,
            'filtroEmail'  => $filtroEmail,
        ]);
    }
}
