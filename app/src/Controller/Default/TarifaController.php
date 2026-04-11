<?php

declare(strict_types=1);

namespace App\Controller\Default;

use App\Entity\Tenant\Tarifa;
use App\Enum\TipoConcepto;
use App\Form\TarifaType;
use App\Repository\Tenant\TarifaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/configuracion/tarifas', name: 'app_tarifa_')]
#[IsGranted('FINANZAS_VER')]
class TarifaController extends AbstractController
{
    public function __construct(
        private readonly TarifaRepository $tarifaRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $tarifas = $this->tarifaRepository->findAll();

        // Agrupar por mandante para el listado
        $agrupadas = [];
        foreach ($tarifas as $tarifa) {
            $key = $tarifa->getMandante()?->getId()?->toRfc4122() ?? '__general__';
            $label = $tarifa->getMandante()?->getNombre() ?? 'General (todos los mandantes)';
            if (!isset($agrupadas[$key])) {
                $agrupadas[$key] = ['label' => $label, 'tarifas' => []];
            }
            $agrupadas[$key]['tarifas'][] = $tarifa;
        }

        return $this->render('tarifa/index.html.twig', [
            'agrupadas'     => $agrupadas,
            'tiposConcepto' => array_filter(TipoConcepto::cases(), fn(TipoConcepto $c) => !in_array($c, [TipoConcepto::BONO, TipoConcepto::DESCUENTO])),
        ]);
    }

    #[Route('/nueva', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('FINANZAS_GESTIONAR')]
    public function new(Request $request): Response
    {
        $tarifa = new Tarifa();
        $tarifa->setVigenciaDesde(new \DateTimeImmutable('first day of january this year'));

        $form = $this->createForm(TarifaType::class, $tarifa);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($tarifa->getVigenciaHasta() !== null && $tarifa->getVigenciaHasta() < $tarifa->getVigenciaDesde()) {
                $this->addFlash('danger', 'La fecha de fin de vigencia debe ser igual o posterior a la fecha de inicio.');
                return $this->render('tarifa/new.html.twig', ['form' => $form]);
            }

            $solapada = $this->tarifaRepository->findSolapada(
                concepto:      $tarifa->getTipoConcepto(),
                vigenciaDesde: $tarifa->getVigenciaDesde(),
                vigenciaHasta: $tarifa->getVigenciaHasta(),
                mandante:      $tarifa->getMandante(),
            );

            if ($solapada !== null) {
                $this->addFlash('danger', sprintf(
                    'Ya existe una tarifa activa para "%s"%s que se solapa con el período indicado (desde %s).',
                    $tarifa->getTipoConcepto()->etiqueta(),
                    $tarifa->getMandante() !== null ? ' del mandante ' . $tarifa->getMandante()->getNombre() : ' general',
                    $solapada->getVigenciaDesde()->format('d/m/Y'),
                ));

                return $this->render('tarifa/new.html.twig', ['form' => $form]);
            }

            $this->em->persist($tarifa);
            $this->em->flush();

            $this->addFlash('success', 'Tarifa creada correctamente.');

            return $this->redirectToRoute('app_tarifa_index');
        }

        return $this->render('tarifa/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/editar', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('FINANZAS_GESTIONAR')]
    public function edit(Request $request, Tarifa $tarifa): Response
    {
        $form = $this->createForm(TarifaType::class, $tarifa);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($tarifa->getVigenciaHasta() !== null && $tarifa->getVigenciaHasta() < $tarifa->getVigenciaDesde()) {
                $this->addFlash('danger', 'La fecha de fin de vigencia debe ser igual o posterior a la fecha de inicio.');
                return $this->render('tarifa/edit.html.twig', [
                    'form' => $form,
                    'tarifa' => $tarifa,
                ]);
            }

            $solapada = $this->tarifaRepository->findSolapada(
                concepto:      $tarifa->getTipoConcepto(),
                vigenciaDesde: $tarifa->getVigenciaDesde(),
                vigenciaHasta: $tarifa->getVigenciaHasta(),
                mandante:      $tarifa->getMandante(),
                excludeId:     $tarifa->getId(),
            );

            if ($solapada !== null) {
                $this->addFlash('danger', sprintf(
                    'Ya existe otra tarifa activa para "%s"%s que se solapa con el período indicado (desde %s).',
                    $tarifa->getTipoConcepto()->etiqueta(),
                    $tarifa->getMandante() !== null ? ' del mandante ' . $tarifa->getMandante()->getNombre() : ' general',
                    $solapada->getVigenciaDesde()->format('d/m/Y'),
                ));

                return $this->render('tarifa/edit.html.twig', [
                    'form'   => $form,
                    'tarifa' => $tarifa,
                ]);
            }

            $this->em->flush();

            $this->addFlash('success', 'Tarifa actualizada correctamente.');

            return $this->redirectToRoute('app_tarifa_index');
        }

        return $this->render('tarifa/edit.html.twig', [
            'form'   => $form,
            'tarifa' => $tarifa,
        ]);
    }

    #[Route('/{id}/desactivar', name: 'desactivar', methods: ['POST'])]
    #[IsGranted('FINANZAS_GESTIONAR')]
    public function desactivar(Request $request, Tarifa $tarifa): Response
    {
        if (!$this->isCsrfTokenValid('tarifa_' . $tarifa->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $tarifa->setActiva(false);
        $this->em->flush();

        $this->addFlash('warning', 'Tarifa desactivada. El historial se mantiene.');

        return $this->redirectToRoute('app_tarifa_index');
    }
}
