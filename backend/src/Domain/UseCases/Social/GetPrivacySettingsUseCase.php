<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Queries\GetFriendsQuery;
use App\Domain\Repository\Social\PrivacySettingsRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class GetPrivacySettingsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly PrivacySettingsRepositoryInterface $privacySettingsRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetPrivacySettings'; }

    protected function doExecute($query): array
    {
        if (!is_object($query) || !property_exists($query, 'userId')) {
            throw new InvalidArgumentException('Query must have a userId property');
        }

        $settings = $this->privacySettingsRepository->findByUserId($query->userId);
        return $settings->toArray();
    }
}
