<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Commands\RemoveFriendCommand;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RemoveFriendUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly FriendshipRepositoryInterface $friendshipRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'RemoveFriend'; }

    protected function doExecute($command): bool
    {
        if (!$command instanceof RemoveFriendCommand) {
            throw new InvalidArgumentException('Command must be an instance of RemoveFriendCommand');
        }

        $friendship = $this->friendshipRepository->findByUsers($command->userId, $command->friendId);

        if ($friendship === null || !$friendship->isAccepted()) {
            throw new RuntimeException('Friendship not found');
        }

        $this->friendshipRepository->delete($friendship->getId());
        return true;
    }
}
