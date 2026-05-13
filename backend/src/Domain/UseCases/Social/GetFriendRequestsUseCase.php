<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Queries\GetFriendRequestsQuery;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class GetFriendRequestsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly FriendshipRepositoryInterface $friendshipRepository,
        private readonly UserRepositoryInterface       $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetFriendRequests'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetFriendRequestsQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetFriendRequestsQuery');
        }

        $pendingFriendships = $this->friendshipRepository->findPendingRequestsForUser($query->userId);

        $requests = [];
        foreach ($pendingFriendships as $friendship) {
            $requester = $this->userRepository->findById($friendship->getRequesterId());
            if ($requester === null) {
                continue;
            }

            $requests[] = [
                'friendship_id' => $friendship->getId(),
                'id'            => $requester->getId(),
                'username'      => $requester->getUsername() ?? $requester->getName(),
                'name'          => $requester->getName(),
                'picture'       => $requester->getPicture(),
                'requested_at'  => $friendship->getCreatedAt(),
            ];
        }

        return $requests;
    }
}
