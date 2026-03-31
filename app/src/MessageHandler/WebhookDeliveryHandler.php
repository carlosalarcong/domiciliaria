<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Tenant\WebhookDelivery;
use App\Message\WebhookDeliveryMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class WebhookDeliveryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function __invoke(WebhookDeliveryMessage $message): void
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = $this->em->getRepository(WebhookDelivery::class)
            ->find($message->deliveryId);

        if ($delivery === null || $delivery->getEstado() === WebhookDelivery::ESTADO_ENTREGADO) {
            return;
        }

        $suscripcion = $delivery->getSuscripcion();
        $payloadJson = json_encode($delivery->getPayload(), JSON_UNESCAPED_UNICODE);
        $firma       = hash_hmac('sha256', $payloadJson, $suscripcion->getSecret());

        try {
            $response = $this->httpClient->request('POST', $suscripcion->getUrl(), [
                'headers' => [
                    'Content-Type'       => 'application/json',
                    'X-Webhook-Event'    => $delivery->getEvento(),
                    'X-Webhook-Delivery' => (string) $delivery->getId(),
                    'X-Signature'        => 'sha256=' . $firma,
                ],
                'body'    => $payloadJson,
                'timeout' => 10,
            ]);

            $codigo = $response->getStatusCode();
            $body   = null;
            try { $body = $response->getContent(false); } catch (\Throwable) {}

            $delivery->registrarIntento($codigo, $body);

        } catch (\Throwable $e) {
            $delivery->marcarFallido($e->getMessage());

            // Si aún quedan reintentos, relanzar para que Messenger reintente con backoff
            if ($delivery->getEstado() === WebhookDelivery::ESTADO_PENDIENTE) {
                $this->em->flush();
                throw new RecoverableMessageHandlingException($e->getMessage(), previous: $e);
            }
        }

        $this->em->flush();
    }
}
