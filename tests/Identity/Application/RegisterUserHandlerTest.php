<?php

declare(strict_types=1);

namespace App\Tests\Identity\Application;

use App\Identity\Application\Register\RegisterUserCommand;
use App\Identity\Application\Register\RegisterUserHandler;
use App\Identity\Domain\Event\UserCreated;
use App\Identity\Domain\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\Exception\InvalidUserData;
use App\Shared\Domain\UserId;
use App\Tests\Identity\Support\FakePasswordHasher;
use App\Tests\Identity\Support\InMemoryUserRepository;
use App\Tests\Identity\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class RegisterUserHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private RecordingEventBus $events;
    private RegisterUserHandler $handler;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->events = new RecordingEventBus();
        $clock = new MockClock('2026-10-13 12:00:00');

        $roles = new \App\Tests\Identity\Support\InMemoryRoleRepository();
        (new \App\Identity\Application\SeedRoles\SeedDefaultRolesHandler($roles, new RecordingEventBus(), $clock))(
            new \App\Identity\Application\SeedRoles\SeedDefaultRolesCommand(),
        );

        $this->handler = new RegisterUserHandler($this->users, $roles, new FakePasswordHasher(), $this->events, $clock);
    }

    public function test_it_registers_a_user_with_normalized_email_and_hashed_password(): void
    {
        $id = ($this->handler)(new RegisterUserCommand('  Ana@Example.com ', 'correct horse battery'));

        $user = $this->users->findById(UserId::fromString($id));
        self::assertNotNull($user);
        self::assertSame('ana@example.com', $user->email()->value);
        self::assertNotSame('correct horse battery', $user->passwordHash()->value);
        self::assertInstanceOf(UserCreated::class, $this->events->published[0]);
    }

    public function test_it_rejects_a_duplicate_email_even_with_different_casing(): void
    {
        ($this->handler)(new RegisterUserCommand('ana@example.com', 'correct horse battery'));

        $this->expectException(EmailAlreadyRegistered::class);
        ($this->handler)(new RegisterUserCommand('ANA@example.com', 'another long password'));
    }

    public function test_it_rejects_a_weak_password_without_persisting_anything(): void
    {
        try {
            ($this->handler)(new RegisterUserCommand('ana@example.com', 'short'));
            self::fail('Debió rechazar la contraseña.');
        } catch (InvalidUserData) {
        }

        self::assertNull($this->users->findByEmail(\App\Identity\Domain\ValueObject\Email::fromString('ana@example.com')));
        self::assertSame([], $this->events->published);
    }
}