<?php

declare(strict_types=1);

namespace App\Domain\Repository\Social;

use App\Domain\Model\FeedEvent;

interface FeedEventRepositoryInterface
{
    /**
     * Persist a new feed event
     */
    public function save(FeedEvent $event): FeedEvent;

    /**
     * Fetch feed events for a set of users filtered by allowed event types per user.
     *
     * $allowedByUser format: [ userId => ['item_added', 'item_rated', ...], ... ]
     *
     * Returns events enriched with user data (username, name, picture) as arrays.
     *
     * @param array<int, string[]> $allowedByUser
     * @return array[]
     */
    public function findFeedEvents(array $allowedByUser, int $limit, int $offset): array;

    /**
     * Count total feed events for the given user/event-type map
     *
     * @param array<int, string[]> $allowedByUser
     */
    public function countFeedEvents(array $allowedByUser): int;
}
