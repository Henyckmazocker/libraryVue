<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Queries\SearchUsersQuery;
use App\Domain\Model\Friendship;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class SearchUsersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface       $userRepository,
        private readonly FriendshipRepositoryInterface $friendshipRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'SearchUsers'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof SearchUsersQuery) {
            throw new InvalidArgumentException('Query must be an instance of SearchUsersQuery');
        }

        $users = $this->userRepository->searchByUsername($query->searchTerm, $query->currentUserId, $query->limit);

        $results = [];
        foreach ($users as $user) {
            $friendship    = $this->friendshipRepository->findByUsers($query->currentUserId, $user->getId());
            $friendStatus  = $this->resolveFriendStatus($friendship, $query->currentUserId);

            $results[] = [
                'id'             => $user->getId(),
                'username'       => $user->getUsername() ?? $user->getName(),
                'name'           => $user->getName(),
                'picture'        => $user->getPicture(),
                'friend_status'  => $friendStatus,  // none | pending_sent | pending_received | friends
                'friendship_id'  => $friendship?->getId(),
            ];
        }

        return $results;
    }

    private function resolveFriendStatus(?Friendship $friendship, int $currentUserId): string
    {
        if ($friendship === null) {
            return 'none';
        }
        if ($friendship->isAccepted()) {
            return 'friends';
        }
        if ($friendship->isPending()) {
            return $friendship->getRequesterId() === $currentUserId ? 'pending_sent' : 'pending_received';
        }
        return 'none';
    }
}
