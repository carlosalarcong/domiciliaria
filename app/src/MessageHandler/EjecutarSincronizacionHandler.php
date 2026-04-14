<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Tenant\SincronizacionExterna;
use App\Message\EjecutarSincronizacionMessage;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class EjecutarSincronizacionHandler
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function __invoke(EjecutarSincronizacionMessage $message): void
    {
        $em = $this->registry->getManager('tenant');
        $sincronizacion = $em->getRepository(SincronizacionExterna::class)->find($message->getSincronizacionId());

        if (!$sincronizacion instanceof SincronizacionExterna) {
            return;
        }

        $sincronizacion->setUltimaEjecucion(new \DateTimeImmutable());

        try {
            $response = $this->httpClient->request(
                $sincronizacion->getMetodo(),
                $sincronizacion->getUrlEndpoint(),
                [
                    'headers' => is_array($sincronizacion->getHeaders()) ? $sincronizacion->getHeaders() : [],
                ],
            );

            $statusCode = $response->getStatusCode();
            $contenido = mb_substr($response->getContent(false), 0, 500);

            if ($statusCode >= 200 && $statusCode < 300) {
                $sincronizacion->setUltimoEstado('ok');
                $sincronizacion->setUltimoResultado(sprintf('HTTP %d - %s', $statusCode, $contenido));
            } else {
                $sincronizacion->setUltimoEstado('error');
                $sincronizacion->setUltimoResultado(sprintf('HTTP %d - %s', $statusCode, $contenido));
            }
        } catch (\Throwable $e) {
            $sincronizacion->setUltimoEstado('error');
            $sincronizacion->setUltimoResultado(mb_substr($e->getMessage(), 0, 500));
        }

        $em->persist($sincronizacion);
        $em->flush();
    }
}
