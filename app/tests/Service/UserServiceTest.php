<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Tenant\User;
use App\Repository\Tenant\UserRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserServiceTest extends TestCase
{
    private UserService $service;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;
    private UserRepository $repo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->repo = $this->createMock(UserRepository::class);

        $this->service = new UserService($this->em, $this->repo, $this->hasher);
    }

    public function testCrearHashesPasswordAndPersists(): void
    {
        $user = new User();
        $user->setEmail('test@test.cl');
        $user->setNombre('Test');
        $user->setApellido('User');
        $user->setRoles(['ROLE_TENS']);

        $this->hasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'password123')
            ->willReturn('hashed_password');

        $this->em->expects($this->once())->method('persist')->with($user);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->crear($user, 'password123');

        $this->assertSame($user, $result);
        $this->assertSame('hashed_password', $result->getPassword());
    }

    public function testActualizarSinPasswordNoHashea(): void
    {
        $user = new User();
        $user->setPassword('existing_hash');

        $this->hasher->expects($this->never())->method('hashPassword');
        $this->em->expects($this->once())->method('flush');

        $this->service->actualizar($user, null);

        $this->assertSame('existing_hash', $user->getPassword());
    }

    public function testActualizarConPasswordHashea(): void
    {
        $user = new User();
        $user->setPassword('old_hash');

        $this->hasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'nueva_password')
            ->willReturn('new_hash');

        $this->em->expects($this->once())->method('flush');

        $this->service->actualizar($user, 'nueva_password');

        $this->assertSame('new_hash', $user->getPassword());
    }

    public function testToggleActivoInvierteEstado(): void
    {
        $user = new User();
        $user->setActivo(true);

        $this->em->expects($this->once())->method('flush');

        $this->service->toggleActivo($user);

        $this->assertFalse($user->isActivo());
    }

    public function testToggleActivoDeInactivoAActivo(): void
    {
        $user = new User();
        $user->setActivo(false);

        $this->em->expects($this->once())->method('flush');

        $this->service->toggleActivo($user);

        $this->assertTrue($user->isActivo());
    }
}
