<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Auth;

use App\Domain\UseCases\Auth\LoginUserUseCase;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\DTO\Commands\LoginUserCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class LoginUserUseCaseTest extends TestCase
{
    private LoginUserUseCase $useCase;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new LoginUserUseCase($this->userRepo, new NullLogger());
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function returns_existing_user_on_login(): void
    {
        $existingUser = new User(
            1,
            GoogleId::fromString('1234567890'),
            Email::fromString('user@test.com'),
            'Test User'
        );

        $this->userRepo->method('findByGoogleId')->willReturn($existingUser);
        $this->userRepo->expects($this->once())->method('update');

        $command = new LoginUserCommand(
            googleId: GoogleId::fromString('1234567890'),
            email: Email::fromString('user@test.com'),
            name: 'Test User'
        );

        $result = $this->useCase->execute($command);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame(1, $result->getId());
    }

    #[Test]
    public function creates_new_user_on_first_login(): void
    {
        $this->userRepo->method('findByGoogleId')->willReturn(null);

        $newUser = new User(
            2,
            GoogleId::fromString('9876543210'),
            Email::fromString('new@test.com'),
            'New User'
        );
        $this->userRepo->method('save')->willReturn($newUser);

        $command = new LoginUserCommand(
            googleId: GoogleId::fromString('9876543210'),
            email: Email::fromString('new@test.com'),
            name: 'New User'
        );

        $result = $this->useCase->execute($command);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame(2, $result->getId());
    }

    #[Test]
    public function updates_email_if_changed(): void
    {
        $existingUser = new User(
            1,
            GoogleId::fromString('1234567890'),
            Email::fromString('old@test.com'),
            'Test User'
        );

        $this->userRepo->method('findByGoogleId')->willReturn($existingUser);
        $this->userRepo->expects($this->once())->method('update');

        $command = new LoginUserCommand(
            googleId: GoogleId::fromString('1234567890'),
            email: Email::fromString('new@test.com'),
            name: 'Test User'
        );

        $result = $this->useCase->execute($command);

        $this->assertSame('new@test.com', $result->getEmail()->toString());
    }

    #[Test]
    public function updates_name_if_changed(): void
    {
        $existingUser = new User(
            1,
            GoogleId::fromString('1234567890'),
            Email::fromString('user@test.com'),
            'Old Name'
        );

        $this->userRepo->method('findByGoogleId')->willReturn($existingUser);
        $this->userRepo->expects($this->once())->method('update');

        $command = new LoginUserCommand(
            googleId: GoogleId::fromString('1234567890'),
            email: Email::fromString('user@test.com'),
            name: 'New Name'
        );

        $result = $this->useCase->execute($command);
        $this->assertSame('New Name', $result->getName());
    }
}
