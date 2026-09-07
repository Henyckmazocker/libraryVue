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
            showAchievements: true,
            showJournal: true
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
            showAchievements: true,
            showJournal: true
        );

        $result = $useCase->execute($command);

        $this->assertIsArray($result);
        $this->assertFalse($result['show_additions']);
        $this->assertTrue($result['show_ratings']);
        $this->assertTrue($result['show_journal']);
    }

    #[Test]
    public function command_fromArray_applies_defaults(): void
    {
        $command = UpdatePrivacySettingsCommand::fromArray([], 5);

        $this->assertSame(5, $command->userId);
        $this->assertTrue($command->showAdditions);
        $this->assertTrue($command->showRatings);
        $this->assertFalse($command->showNotes);
        // El diario es el segundo que cae a apagado: enseñarlo se pide, no se hereda.
        $this->assertFalse($command->showJournal);
    }

    #[Test]
    public function journal_is_not_a_feed_event(): void
    {
        // `showJournal` es permiso de lectura del diario, no un `event_type`.
        // Si entrara en EVENT_TYPE_MAP saldría aquí y el feed filtraría por un
        // tipo que `feed_events.event_type` no admite.
        $settings = new PrivacySettings(userId: 1, showJournal: true);

        $this->assertNotContains('journal', $settings->getVisibleEventTypes());
        $this->assertFalse($settings->isEventVisible('journal'));
        $this->assertTrue($settings->toArray()['show_journal']);
    }
}
