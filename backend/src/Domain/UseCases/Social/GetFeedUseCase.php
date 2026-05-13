<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Queries\GetFriendsQuery;
use App\Domain\Model\PrivacySettings;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\Social\FeedEventRepositoryInterface;
use App\Domain\Repository\Social\PrivacySettingsRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetFeedQuery;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class GetFeedUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly FriendshipRepositoryInterface    $friendshipRepository,
        private readonly FeedEventRepositoryInterface     $feedEventRepository,
        private readonly PrivacySettingsRepositoryInterface $privacySettingsRepository,
        private readonly UserRepositoryInterface          $userRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetFeed'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetFeedQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetFeedQuery');
        }

        // 1. Get accepted friends
        $friendships = $this->friendshipRepository->findAcceptedByUser($query->userId);

        if (empty($friendships)) {
            return ['events' => [], 'hasMore' => false, 'total' => 0];
        }

        // 2. Build allowed event types per friend
        $allowedByUser = [];
        foreach ($friendships as $friendship) {
            $friendId = $friendship->getRequesterId() === $query->userId
                ? $friendship->getAddresseeId()
                : $friendship->getRequesterId();

            $privacySettings = $this->privacySettingsRepository->findByUserId($friendId);
            $visibleTypes    = $privacySettings->getVisibleEventTypes();

            if (!empty($visibleTypes)) {
                $allowedByUser[$friendId] = $visibleTypes;
            }
        }

        if (empty($allowedByUser)) {
            return ['events' => [], 'hasMore' => false, 'total' => 0];
        }

        // 3. Query feed
        $total   = $this->feedEventRepository->countFeedEvents($allowedByUser);
        $events  = $this->feedEventRepository->findFeedEvents($allowedByUser, $query->limit, $query->offset);
        $hasMore = ($query->offset + $query->limit) < $total;

        return [
            'events'  => $events,
            'hasMore' => $hasMore,
            'total'   => $total,
        ];
    }
}
