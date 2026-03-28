<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Tenant\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PacienteVoter extends Voter
{
    public const VER_OPERATIVO = 'PACIENTE_VER_OPERATIVO';
    public const VER_CLINICO = 'PACIENTE_VER_CLINICO';
    public const CREAR = 'PACIENTE_CREAR';
    public const EDITAR = 'PACIENTE_EDITAR';
    public const ELIMINAR = 'PACIENTE_ELIMINAR';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VER_OPERATIVO,
            self::VER_CLINICO,
            self::CREAR,
            self::EDITAR,
            self::ELIMINAR,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActivo()) {
            return false;
        }

        $roles = $user->getRoles();

        return match ($attribute) {
            self::VER_OPERATIVO => true,
            self::VER_CLINICO => array_intersect(
                ['ROLE_ADMIN', 'ROLE_COORDINADOR', 'ROLE_ENFERMERA'],
                $roles
            ) !== [],
            self::CREAR, self::EDITAR => array_intersect(
                ['ROLE_ADMIN', 'ROLE_COORDINADOR'],
                $roles
            ) !== [],
            self::ELIMINAR => in_array('ROLE_ADMIN', $roles),
            default => false,
        };
    }
}
