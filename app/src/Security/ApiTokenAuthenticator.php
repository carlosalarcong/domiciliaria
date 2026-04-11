<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\Tenant\ApiTokenRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiTokenRepository $tokenRepository,
    ) {}

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/') && $request->headers->has('X-API-KEY');
    }

    public function authenticate(Request $request): Passport
    {
        $plainToken = trim($request->headers->get('X-API-KEY', ''));

        if ($plainToken === '') {
            throw new CustomUserMessageAuthenticationException('Token API vacío.');
        }

        $apiToken = $this->tokenRepository->findByPlainToken($plainToken);

        if ($apiToken === null) {
            throw new CustomUserMessageAuthenticationException('Token API inválido.');
        }

        if (!$apiToken->isValid()) {
            throw new CustomUserMessageAuthenticationException('Token API inactivo o expirado.');
        }

        $apiToken->marcarUso();

        $apiUser = new ApiUser($apiToken);

        return new SelfValidatingPassport(
            new UserBadge($apiUser->getUserIdentifier(), fn() => $apiUser),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['error' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
