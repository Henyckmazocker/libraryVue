<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Commands\RejectFriendRequestCommand;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RejectFriendRequestUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly FriendshipRepositoryInterface $friendshipRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'RejectFriendRequest'; }

    protected function doExecute($command): bool
    {
        if (!$command instanceof RejectFriendRequestCommand) {
            throw new InvalidArgumentException('Command must be an instance of RejectFriendRequestCommand');
        }

        $pending = $this->friendshipRepository->findPendingRequestsForUser($command->userId);
        $friendship = null;
        foreach ($pending as $f) {
            if ($f->getId() === $command->friendshipId) {
                $friendship = $f;
                break;
            }
        }

        if ($friendship === null) {
            throw new RuntimeException('Friend request not found or you are not the recipient');
        }

        $friendship->reject();
        $this->friendshipRepository->update($friendship);
        return true;
    }
}
