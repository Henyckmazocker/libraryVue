<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Queries\GetPublicProfileQuery;
use App\Domain\Model\Friendship;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class GetPublicProfileUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface       $userRepository,
        private readonly FriendshipRepositoryInterface $friendshipRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetPublicProfile'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetPublicProfileQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetPublicProfileQuery');
        }

        if (empty($query->username)) {
            throw new InvalidArgumentException('Username is required');
        }

        $user = $this->userRepository->findByUsername($query->username);
        if ($user === null) {
            throw new RuntimeException("User '{$query->username}' not found");
        }

        $friendship   = $this->friendshipRepository->findByUsers($query->viewerUserId, $user->getId());
        $friendStatus = 'none';
        $friendshipId = null;
        if ($friendship !== null) {
            $friendshipId = $friendship->getId();
            if ($friendship->isAccepted()) {
                $friendStatus = 'friends';
            } elseif ($friendship->isPending()) {
                $friendStatus = $friendship->getRequesterId() === $query->viewerUserId
                    ? 'pending_sent'
                    : 'pending_received';
            }
        }

        return [
            'id'            => $user->getId(),
            'username'      => $user->getUsername() ?? $user->getName(),
            'name'          => $user->getName(),
            'picture'       => $user->getPicture(),
            'friend_status' => $friendStatus,
            'friendship_id' => $friendshipId,
        ];
    }
}
