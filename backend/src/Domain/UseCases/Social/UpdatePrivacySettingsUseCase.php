<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Commands\UpdatePrivacySettingsCommand;
use App\Domain\Model\PrivacySettings;
use App\Domain\Repository\Social\PrivacySettingsRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class UpdatePrivacySettingsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly PrivacySettingsRepositoryInterface $privacySettingsRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'UpdatePrivacySettings'; }

    protected function doExecute($command): array
    {
        if (!$command instanceof UpdatePrivacySettingsCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdatePrivacySettingsCommand');
        }

        $settings = new PrivacySettings(
            userId:              $command->userId,
            showAdditions:       $command->showAdditions,
            showStatusChanges:   $command->showStatusChanges,
            showRatings:         $command->showRatings,
            showNotes:           $command->showNotes,
            showReadingSessions: $command->showReadingSessions,
            showAchievements:    $command->showAchievements
        );

        $saved = $this->privacySettingsRepository->save($settings);
        return $saved->toArray();
    }
}
