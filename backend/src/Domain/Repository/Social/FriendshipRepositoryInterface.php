<?php

declare(strict_types=1);

namespace App\Domain\Repository\Social;

use App\Domain\Model\Friendship;

interface FriendshipRepositoryInterface
{
    /**
     * Find a friendship between two users (in either direction)
     */
    public function findByUsers(int $userId1, int $userId2): ?Friendship;

    /**
     * Find all accepted friends of a user
     *
     * @return Friendship[]
     */
    public function findAcceptedByUser(int $userId): array;

    /**
     * Find pending incoming requests for a user (addressee = userId)
     *
     * @return Friendship[]
     */
    public function findPendingRequestsForUser(int $userId): array;

    /**
     * Persist a new friendship (INSERT)
     */
    public function save(Friendship $friendship): Friendship;

    /**
     * Update an existing friendship (UPDATE status)
     */
    public function update(Friendship $friendship): void;

    /**
     * Delete a friendship by ID
     */
    public function delete(int $friendshipId): void;

    /**
     * Count pending incoming requests for a user
     */
    public function countPendingRequestsForUser(int $userId): int;
}
