<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\DTO\Commands\UpdatePrivacySettingsCommand;
use App\Domain\DTO\Queries\GetFeedQuery;
use App\Domain\UseCases\Social\GetFeedUseCase;
use App\Domain\UseCases\Social\GetPrivacySettingsUseCase;
use App\Domain\UseCases\Social\UpdatePrivacySettingsUseCase;

class FeedController extends BaseController
{
    public function __construct(
        private readonly GetFeedUseCase               $getFeedUseCase,
        private readonly GetPrivacySettingsUseCase    $getPrivacySettingsUseCase,
        private readonly UpdatePrivacySettingsUseCase $updatePrivacySettingsUseCase
    ) {}

    public function getFeed(GetFeedQuery $query): array
    {
        $result = $this->getFeedUseCase->execute($query);
        return $this->successResponse('Feed retrieved', $result);
    }

    public function getPrivacySettings(int $userId): array
    {
        $settings = $this->getPrivacySettingsUseCase->execute((object) ['userId' => $userId]);
        return $this->successResponse('Privacy settings retrieved', $settings);
    }

    public function updatePrivacySettings(UpdatePrivacySettingsCommand $command): array
    {
        $settings = $this->updatePrivacySettingsUseCase->execute($command);
        return $this->successResponse('Privacy settings updated', $settings);
    }
}
