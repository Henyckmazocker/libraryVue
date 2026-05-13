<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Queries\GetFriendsQuery;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class GetFriendsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly FriendshipRepositoryInterface $friendshipRepository,
        private readonly UserRepositoryInterface       $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetFriends'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetFriendsQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetFriendsQuery');
        }

        $friendships = $this->friendshipRepository->findAcceptedByUser($query->userId);

        $friends = [];
        foreach ($friendships as $friendship) {
            // The friend is whoever is NOT the current user
            $friendId = $friendship->getRequesterId() === $query->userId
                ? $friendship->getAddresseeId()
                : $friendship->getRequesterId();

            $user = $this->userRepository->findById($friendId);
            if ($user === null) {
                continue;
            }

            $friends[] = [
                'friendship_id' => $friendship->getId(),
                'id'            => $user->getId(),
                'username'      => $user->getUsername() ?? $user->getName(),
                'name'          => $user->getName(),
                'picture'       => $user->getPicture(),
                'since'         => $friendship->getUpdatedAt() ?? $friendship->getCreatedAt(),
            ];
        }

        return $friends;
    }
}
