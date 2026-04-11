<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\WebhookDelivery;
use App\Enum\WebhookEvento;
use App\Message\WebhookDeliveryMessage;
use App\Repository\Tenant\WebhookSuscripcionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class WebhookDispatcher
{
    public function __construct(
        private readonly WebhookSuscripcionRepository $suscripcionRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    /**
     * Dispara un evento a todas las suscripciones activas que lo escuchen.
     *
     * @param array<string, mixed> $datos  Datos específicos del evento
     */
    public function dispatch(WebhookEvento $evento, array $datos, string $tenant = ''): void
    {
        $suscripciones = $this->suscripcionRepository->findActivasPorEvento($evento);
        if (empty($suscripciones)) {
            return;
        }

        $payload = [
            'evento'    => $evento->value,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'tenant'    => $tenant,
            'version'   => '1',
            'datos'     => $datos,
        ];

        foreach ($suscripciones as $suscripcion) {
            $delivery = new WebhookDelivery($suscripcion, $evento, $payload);
            $this->em->persist($delivery);
            $this->em->flush();

            $this->bus->dispatch(new WebhookDeliveryMessage((string) $delivery->getId()));
        }
    }
}
