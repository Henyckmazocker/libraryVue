<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Auth;

use App\Domain\DTO\Commands\UpdateAnalyticsConsentCommand;
use App\Domain\Model\User;
use App\Domain\Model\ValueObjects\Email;
use App\Domain\Model\ValueObjects\GoogleId;
use App\Domain\Model\ValueObjects\Timestamp;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\Auth\UpdateAnalyticsConsentUseCase;
use App\Infrastructure\Persistence\User\Mappers\UserDataMapper;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Consentimiento de analítica: el command, el use case y el paso por
 * `User`/`UserDataMapper` (Plan «Consentimiento de Analítica», M1).
 */
class AnalyticsConsentUseCaseTest extends TestCase
{
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
    }

    private function usuario(?int $consent, ?string $at = null): User
    {
        return new User(
            7,
            GoogleId::fromString('1234567890'),
            Email::fromString('user@test.com'),
            'Test User',
            analyticsConsent: $consent,
            analyticsConsentAt: $at !== null ? Timestamp::fromString($at) : null
        );
    }

    // ─── Command ──────────────────────────────────────────

    #[Test]
    public function command_fromArray_accepts_true_and_false(): void
    {
        $si = UpdateAnalyticsConsentCommand::fromArray(['consent' => true], 5);
        $no = UpdateAnalyticsConsentCommand::fromArray(['consent' => false], 5);

        $this->assertSame(5, $si->userId);
        $this->assertTrue($si->consent);
        $this->assertFalse($no->consent);
    }

    public static function valoresNoBooleanos(): array
    {
        return [
            'ausente'       => [[]],
            'null'          => [['consent' => null]],
            'texto'         => [['consent' => 'x']],
            'texto "true"'  => [['consent' => 'true']],
            'entero 1'      => [['consent' => 1]],
            'entero 0'      => [['consent' => 0]],
        ];
    }

    #[Test]
    #[DataProvider('valoresNoBooleanos')]
    public function command_fromArray_rejects_anything_but_a_json_boolean(array $data): void
    {
        // Sin default: una clave ausente no puede convertirse en una decisión.
        $this->expectException(InvalidArgumentException::class);
        UpdateAnalyticsConsentCommand::fromArray($data, 5);
    }

    // ─── Use case ─────────────────────────────────────────

    #[Test]
    public function use_case_throws_on_invalid_command(): void
    {
        $useCase = new UpdateAnalyticsConsentUseCase($this->userRepo, new NullLogger());
        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new \stdClass());
    }

    #[Test]
    public function use_case_saves_the_decision_and_returns_it_with_its_date(): void
    {
        $this->userRepo->expects($this->once())
            ->method('updateAnalyticsConsent')
            ->with(7, true)
            ->willReturn($this->usuario(1, '2026-09-28 12:00:00'));

        $useCase = new UpdateAnalyticsConsentUseCase($this->userRepo, new NullLogger());
        $result  = $useCase->execute(new UpdateAnalyticsConsentCommand(userId: 7, consent: true));

        $this->assertSame(
            ['analytics_consent' => 1, 'analytics_consent_at' => '2026-09-28 12:00:00'],
            $result
        );
    }

    #[Test]
    public function use_case_never_goes_through_the_generic_update(): void
    {
        // El `update()` genérico reescribe el usuario entero: el consentimiento
        // tiene su propio UPDATE.
        $this->userRepo->expects($this->never())->method('update');
        $this->userRepo->method('updateAnalyticsConsent')->willReturn($this->usuario(0, '2026-09-28 12:00:00'));

        $useCase = new UpdateAnalyticsConsentUseCase($this->userRepo, new NullLogger());
        $result  = $useCase->execute(new UpdateAnalyticsConsentCommand(userId: 7, consent: false));

        $this->assertSame(0, $result['analytics_consent']);
    }

    #[Test]
    public function use_case_throws_when_the_user_does_not_exist(): void
    {
        $this->userRepo->method('updateAnalyticsConsent')->willReturn(null);

        $useCase = new UpdateAnalyticsConsentUseCase($this->userRepo, new NullLogger());
        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new UpdateAnalyticsConsentCommand(userId: 999, consent: true));
    }

    // ─── User y UserDataMapper ────────────────────────────

    #[Test]
    public function user_toArray_carries_the_three_states(): void
    {
        $this->assertArrayHasKey('analytics_consent', $this->usuario(null)->toArray());
        $this->assertNull($this->usuario(null)->toArray()['analytics_consent']);
        $this->assertSame(0, $this->usuario(0)->toArray()['analytics_consent']);
        $this->assertSame(1, $this->usuario(1)->toArray()['analytics_consent']);
    }

    private function fila(array $extra = []): array
    {
        return $extra + [
            'id' => 7,
            'google_id' => '1234567890',
            'email' => 'user@test.com',
            'name' => 'Test User',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
    }

    #[Test]
    public function mapper_keeps_null_as_undecided_and_reads_the_date(): void
    {
        $mapper = new UserDataMapper();

        // NULL de la columna: sin decidir, no «no».
        $this->assertNull($mapper->toDomain($this->fila(['analytics_consent' => null]))->getAnalyticsConsent());
        // Base sin migrar: sin la clave, igual.
        $this->assertNull($mapper->toDomain($this->fila())->getAnalyticsConsent());

        $user = $mapper->toDomain($this->fila([
            'analytics_consent' => '0',
            'analytics_consent_at' => '2026-09-28 12:00:00',
        ]));
        $this->assertSame(0, $user->getAnalyticsConsent());
        $this->assertSame('2026-09-28 12:00:00', $user->getAnalyticsConsentAt()?->toString());
    }

    #[Test]
    public function mapper_keeps_the_consent_out_of_the_generic_persistence(): void
    {
        // El UPDATE del login sale de aquí: con estas columnas moriría en una base
        // sin migrar, y además podría pisar la decisión.
        $data = (new UserDataMapper())->toPersistence($this->usuario(1, '2026-09-28 12:00:00'));

        $this->assertArrayNotHasKey('analytics_consent', $data);
        $this->assertArrayNotHasKey('analytics_consent_at', $data);
    }

    #[Test]
    public function mapper_writes_the_consent_columns_for_the_dedicated_update(): void
    {
        $data = (new UserDataMapper())->analyticsConsentToPersistence(
            false,
            Timestamp::fromString('2026-09-28 12:00:00')
        );

        $this->assertSame(['analytics_consent' => 0, 'analytics_consent_at' => '2026-09-28 12:00:00'], $data);
    }
}
