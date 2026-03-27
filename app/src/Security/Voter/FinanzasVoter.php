<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FinanzasVoter extends Voter
{
    public const VER = 'FINANZAS_VER';
    public const GESTIONAR = 'FINANZAS_GESTIONAR';
    public const EXPORTAR = 'FINANZAS_EXPORTAR';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VER, self::GESTIONAR, self::EXPORTAR]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActivo()) {
            return false;
        }

        $rolesPermitidos = ['ROLE_ADMIN', 'ROLE_COORDINADOR'];
        return array_intersect($rolesPermitidos, $user->getRoles()) !== [];
    }
}
