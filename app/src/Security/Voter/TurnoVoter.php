<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TurnoVoter extends Voter
{
    public const VER = 'TURNO_VER';
    public const CREAR = 'TURNO_CREAR';
    public const EDITAR = 'TURNO_EDITAR';
    public const ELIMINAR = 'TURNO_ELIMINAR';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VER, self::CREAR, self::EDITAR, self::ELIMINAR]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VER => $this->puedeVer($user),
            self::CREAR => $this->puedeCrear($user),
            self::EDITAR => $this->puedeEditar($user),
            self::ELIMINAR => $this->puedeEliminar($user),
            default => false,
        };
    }

    private function puedeVer(User $user): bool
    {
        return $user->isActivo();
    }

    private function puedeCrear(User $user): bool
    {
        return $user->isActivo() && array_intersect(
            ['ROLE_ADMIN', 'ROLE_COORDINADOR'],
            $user->getRoles()
        ) !== [];
    }

    private function puedeEditar(User $user): bool
    {
        return $user->isActivo() && array_intersect(
            ['ROLE_ADMIN', 'ROLE_COORDINADOR'],
            $user->getRoles()
        ) !== [];
    }

    private function puedeEliminar(User $user): bool
    {
        return $user->isActivo() && in_array('ROLE_ADMIN', $user->getRoles());
    }
}
