<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Social;

use App\Domain\DTO\Commands\UpdatePrivacySettingsCommand;
use App\Domain\Model\PrivacySettings;
use App\Domain\Repository\Social\PrivacySettingsRepositoryInterface;
use App\Domain\UseCases\Social\GetPrivacySettingsUseCase;
use App\Domain\UseCases\Social\UpdatePrivacySettingsUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class PrivacySettingsUseCaseTest extends TestCase
{
    private PrivacySettingsRepositoryInterface $privacyRepo;

    protected function setUp(): void
    {
        $this->privacyRepo = $this->createMock(PrivacySettingsRepositoryInterface::class);
    }

    // ─── GetPrivacySettings ───────────────────────────────

    #[Test]
    public function get_privacy_settings_throws_on_invalid_input(): void
    {
        $useCase = new GetPrivacySettingsUseCase($this->privacyRepo, new NullLogger());
        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new \stdClass());
    }

    #[Test]
    public function get_privacy_settings_returns_array(): void
    {
        $settings = new PrivacySettings(userId: 1);
        $this->privacyRepo->method('findByUserId')->willReturn($settings);

        $useCase = new GetPrivacySettingsUseCase($this->privacyRepo, new NullLogger());
        $result  = $useCase->execute((object) ['userId' => 1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('show_additions', $result);
    }

    // ─── UpdatePrivacySettings ────────────────────────────

    #[Test]
    public function update_privacy_settings_throws_on_invalid_command(): void
    {
        $useCase = new UpdatePrivacySettingsUseCase($this->privacyRepo, new NullLogger());
        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new \stdClass());
    }

    #[Test]
    public function update_privacy_settings_saves_and_returns_array(): void
    {
        $saved = new PrivacySettings(
            userId: 1,
            showAdditions: false,
            showStatusChanges: true,
            showRatings: true,
            showNotes: false,
            showReadingSessions: false,
            showAchievements: true
        );
        $this->privacyRepo->expects($this->once())->method('save')->willReturn($saved);

        $useCase = new UpdatePrivacySettingsUseCase($this->privacyRepo, new NullLogger());
        $command = new UpdatePrivacySettingsCommand(
            userId: 1,
            showAdditions: false,
            showStatusChanges: true,
            showRatings: true,
            showNotes: false,
            showReadingSessions: false,
            showAchievements: true
        );

        $result = $useCase->execute($command);

        $this->assertIsArray($result);
        $this->assertFalse($result['show_additions']);
        $this->assertTrue($result['show_ratings']);
    }

    #[Test]
    public function command_fromArray_applies_defaults(): void
    {
        $command = UpdatePrivacySettingsCommand::fromArray([], 5);

        $this->assertSame(5, $command->userId);
        $this->assertTrue($command->showAdditions);
        $this->assertTrue($command->showRatings);
        $this->assertFalse($command->showNotes);
    }
}
