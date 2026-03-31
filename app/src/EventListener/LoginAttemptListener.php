<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Tenant\AuditLog;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Bloquea IPs tras 5 intentos fallidos de login durante 15 minutos.
 * Registra todos los intentos en audit_logs.
 */
class LoginAttemptListener
{
    private const MAX_ATTEMPTS = 5;
    private const BLOCK_TTL    = 900; // 15 min

    public function __construct(
        private readonly CacheInterface        $cache,
        private readonly EntityManagerInterface $em,
        private readonly TenantContext         $tenantContext,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()) {
            return;
        }
        if (!str_contains($request->getPathInfo(), '/login')) {
            return;
        }
        if ($request->getMethod() !== 'POST') {
            return;
        }

        $ip  = $request->getClientIp() ?? '0.0.0.0';
        $key = 'login_attempts_' . md5($ip);

        $attempts = $this->cache->get($key, fn() => 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $response = new RedirectResponse('/login?blocked=1');
            $event->setResponse($response);
        }
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $ip      = $request->getClientIp() ?? '0.0.0.0';
        $email   = $request->request->get('email', '');
        $key     = 'login_attempts_' . md5($ip);

        // Incrementar contador en cache
        $attempts = $this->cache->get($key, fn() => 0);
        $this->cache->delete($key);
        $newAttempts = $attempts + 1;
        $this->cache->get($key, function (\Symfony\Contracts\Cache\ItemInterface $item) use ($newAttempts) {
            $item->expiresAfter(self::BLOCK_TTL);
            return $newAttempts;
        });

        $this->registrarAudit('LOGIN_FAILED', $email, $request, [
            'attempts' => $newAttempts,
            'blocked'  => $newAttempts >= self::MAX_ATTEMPTS,
        ]);
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $ip      = $request->getClientIp() ?? '0.0.0.0';
        $key     = 'login_attempts_' . md5($ip);
        $this->cache->delete($key);

        $this->registrarAudit('LOGIN_SUCCESS', $event->getUser()->getUserIdentifier(), $request, []);

        // Redirigir automáticamente a vista de campo si es TENS/ENFERMERA en móvil
        $user = $event->getUser();
        if ($this->esTrabajadorDeCampo($user) && $this->esMobile($request->headers->get('User-Agent', ''))) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_campo_index')));
        }
    }

    private function esTrabajadorDeCampo(UserInterface $user): bool
    {
        $roles = $user->getRoles();
        return in_array('ROLE_TENS', $roles, true) || in_array('ROLE_ENFERMERA', $roles, true);
    }

    private function esMobile(string $userAgent): bool
    {
        return (bool) preg_match(
            '/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i',
            $userAgent
        );
    }

    private function registrarAudit(string $evento, ?string $email, $request, array $detalles): void
    {
        try {
            $log = new AuditLog();
            $log->setEvento($evento)
                ->setEmailIntentado($email)
                ->setIpAddress($request->getClientIp() ?? '0.0.0.0')
                ->setUserAgent(substr($request->headers->get('User-Agent', ''), 0, 500))
                ->setTenant($this->tenantContext->getCurrentSubdomain())
                ->setDetalles($detalles);

            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable) {
            // Nunca romper el flujo de login por un fallo de audit
        }
    }
}
