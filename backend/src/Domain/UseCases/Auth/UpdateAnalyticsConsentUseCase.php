<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Auth;

use App\Domain\DTO\Commands\UpdateAnalyticsConsentCommand;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Guarda el consentimiento de analítica del usuario (`users.analytics_consent`)
 * y cuándo lo dio. Vive en `Auth/` junto a `UpdateUserProfileUseCase` porque es
 * un dato del propio usuario, no de la visibilidad social.
 */
class UpdateAnalyticsConsentUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'UpdateAnalyticsConsent'; }

    /**
     * @return array{analytics_consent: int|null, analytics_consent_at: string|null}
     */
    protected function doExecute($command): array
    {
        if (!$command instanceof UpdateAnalyticsConsentCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateAnalyticsConsentCommand');
        }

        $user = $this->userRepository->updateAnalyticsConsent($command->userId, $command->consent);
        if ($user === null) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        return [
            'analytics_consent'    => $user->getAnalyticsConsent(),
            'analytics_consent_at' => $user->getAnalyticsConsentAt()?->toString(),
        ];
    }
}
