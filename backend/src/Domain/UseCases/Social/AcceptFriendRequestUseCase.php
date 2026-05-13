<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Commands\AcceptFriendRequestCommand;
use App\Domain\Model\Friendship;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class AcceptFriendRequestUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly FriendshipRepositoryInterface $friendshipRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'AcceptFriendRequest'; }

    protected function doExecute($command): Friendship
    {
        if (!$command instanceof AcceptFriendRequestCommand) {
            throw new InvalidArgumentException('Command must be an instance of AcceptFriendRequestCommand');
        }

        // Find the friendship by scanning both pending requests for this user
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

        $friendship->accept();
        $this->friendshipRepository->update($friendship);
        return $friendship;
    }
}
