<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Tenant\ApiToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Representa al llamador externo autenticado mediante X-API-KEY.
 * No es un usuario de base de datos — existe solo durante la request.
 */
class ApiUser implements UserInterface
{
    public function __construct(
        private readonly ApiToken $token,
    ) {}

    public function getToken(): ApiToken
    {
        return $this->token;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_API'];

        foreach ($this->token->getPermisos() as $permiso) {
            $roles[] = 'ROLE_API_' . strtoupper($permiso);
        }

        return $roles;
    }

    public function getUserIdentifier(): string
    {
        return 'api:' . $this->token->getNombre();
    }

    public function eraseCredentials(): void {}
}
