<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\Voter\TurnoVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class TurnoVoterTest extends TestCase
{
    private TurnoVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new TurnoVoter();
    }

    private function makeToken(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    private function makeUser(array $roles, bool $activo = true): User
    {
        $user = new User();
        $user->setEmail('test@test.cl');
        $user->setNombre('Test');
        $user->setApellido('User');
        $user->setRoles($roles);
        $user->setActivo($activo);
        return $user;
    }

    public function testAdminPuedeHacerTodo(): void
    {
        $user = $this->makeUser(['ROLE_ADMIN']);
        $token = $this->makeToken($user);

        foreach ([TurnoVoter::VER, TurnoVoter::CREAR, TurnoVoter::EDITAR, TurnoVoter::ELIMINAR] as $attr) {
            $this->assertSame(1, $this->voter->vote($token, null, [$attr]), "Admin debe poder: $attr");
        }
    }

    public function testCoordinadorPuedeCrearYEditar(): void
    {
        $user = $this->makeUser(['ROLE_COORDINADOR']);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, null, [TurnoVoter::CREAR]));
        $this->assertSame(1, $this->voter->vote($token, null, [TurnoVoter::EDITAR]));
        $this->assertSame(-1, $this->voter->vote($token, null, [TurnoVoter::ELIMINAR]));
    }

    public function testVisualizadorSoloPuedeVer(): void
    {
        $user = $this->makeUser(['ROLE_VISUALIZADOR']);
        $token = $this->makeToken($user);

        $this->assertSame(1, $this->voter->vote($token, null, [TurnoVoter::VER]));
        $this->assertSame(-1, $this->voter->vote($token, null, [TurnoVoter::CREAR]));
        $this->assertSame(-1, $this->voter->vote($token, null, [TurnoVoter::ELIMINAR]));
    }

    public function testUsuarioInactivoNoPuede(): void
    {
        $user = $this->makeUser(['ROLE_ADMIN'], activo: false);
        $token = $this->makeToken($user);

        $this->assertSame(-1, $this->voter->vote($token, null, [TurnoVoter::VER]));
        $this->assertSame(-1, $this->voter->vote($token, null, [TurnoVoter::CREAR]));
    }
}
