<?php

declare(strict_types=1);

namespace App\Domain\Repository\Social;

use App\Domain\Model\Recommendation;

interface RecommendationRepositoryInterface
{
    /**
     * Persist a new recommendation (INSERT)
     */
    public function save(Recommendation $recommendation): Recommendation;

    public function findById(int $recommendationId): ?Recommendation;

    /**
     * Recommendations addressed to a user, newest first
     *
     * @return Recommendation[]
     */
    public function findForRecipient(int $recipientId, string $status, int $limit, int $offset): array;

    /**
     * Total addressed to a user with that status — the pagination needs it and
     * it cannot come from counting the page.
     */
    public function countForRecipient(int $recipientId, string $status): int;

    /**
     * Does the same sender already have this item sent to this recipient?
     * The UNIQUE key enforces it; this is what turns it into a readable error.
     */
    public function existsBetween(int $senderId, int $recipientId, string $entityType, string $entityId): bool;

    /**
     * Persist a resolved status (UPDATE status + resolved_at)
     */
    public function update(Recommendation $recommendation): void;
}
