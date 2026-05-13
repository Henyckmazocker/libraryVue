<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Commands\SendFriendRequestCommand;
use App\Domain\Model\Friendship;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SendFriendRequestUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly FriendshipRepositoryInterface $friendshipRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'SendFriendRequest'; }

    protected function doExecute($command): Friendship
    {
        if (!$command instanceof SendFriendRequestCommand) {
            throw new InvalidArgumentException('Command must be an instance of SendFriendRequestCommand');
        }

        $existing = $this->friendshipRepository->findByUsers($command->requesterId, $command->addresseeId);
        if ($existing !== null) {
            throw new RuntimeException('A friendship or pending request already exists between these users');
        }

        $friendship = new Friendship(null, $command->requesterId, $command->addresseeId, Friendship::STATUS_PENDING);
        return $this->friendshipRepository->save($friendship);
    }
}
