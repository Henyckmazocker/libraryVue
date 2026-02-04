<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book\Mappers;

use App\Domain\Model\UserBookEdition;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Timestamp;

/**
 * Maps UserBookEdition entity between Domain and Persistence layers
 */
final class UserBookEditionDataMapper
{
    /**
     * Convert database row to UserBookEdition domain entity
     */
    public function toDomain(array $dbRow): UserBookEdition
    {
        $userBookEdition = new UserBookEdition(
            (int) $dbRow['user_id'],
            (int) $dbRow['edition_id'],
            isset($dbRow['id']) ? (int) $dbRow['id'] : null
        );

        if (isset($dbRow['current_page'])) {
            $userBookEdition->setCurrentPage((int) $dbRow['current_page']);
        }

        if (isset($dbRow['consumed_at']) && $dbRow['consumed_at']) {
            // consumed_at will be set from DB timestamp
            $userBookEdition->markAsConsumed();
        }

        if (isset($dbRow['active_reading_session_id']) && $dbRow['active_reading_session_id']) {
            $userBookEdition->setActiveReadingSessionId((int) $dbRow['active_reading_session_id']);
        }

        if (isset($dbRow['edition_rating']) && $dbRow['edition_rating'] !== null) {
            $userBookEdition->setEditionRating(
                Rating::fromNullableFloat((float) $dbRow['edition_rating'])
            );
        }

        if (isset($dbRow['work_rating']) && $dbRow['work_rating'] !== null) {
            $userBookEdition->setWorkRating(
                Rating::fromNullableFloat((float) $dbRow['work_rating'])
            );
        }

        if (isset($dbRow['ownership_type'])) {
            $userBookEdition->setOwnershipType($dbRow['ownership_type']);
        }

        if (isset($dbRow['condition'])) {
            $userBookEdition->setCondition($dbRow['condition']);
        }

        if (isset($dbRow['location'])) {
            $userBookEdition->setLocation($dbRow['location']);
        }

        if (isset($dbRow['is_digital'])) {
            $userBookEdition->setIsDigital((bool) $dbRow['is_digital']);
        }

        if (isset($dbRow['personal_notes'])) {
            $userBookEdition->setPersonalNotes($dbRow['personal_notes']);
        }

        return $userBookEdition;
    }

    /**
     * Convert UserBookEdition domain entity to database row
     */
    public function toDatabase(UserBookEdition $userBookEdition): array
    {
        return [
            'id' => $userBookEdition->getId(),
            'user_id' => $userBookEdition->getUserId(),
            'edition_id' => $userBookEdition->getEditionId(),
            'added_at' => $userBookEdition->getAddedAt()->toDateTime()->format('Y-m-d H:i:s'),
            'consumed_at' => $userBookEdition->getConsumedAt()?->toDateTime()->format('Y-m-d H:i:s'),
            'current_page' => $userBookEdition->getCurrentPage(),
            'active_reading_session_id' => $userBookEdition->getActiveReadingSessionId(),
            'edition_rating' => $userBookEdition->getEditionRating()?->toFloat(),
            'work_rating' => $userBookEdition->getWorkRating()?->toFloat(),
            'ownership_type' => $userBookEdition->getOwnershipType(),
            'condition' => $userBookEdition->getCondition(),
            'location' => $userBookEdition->getLocation(),
            'is_digital' => $userBookEdition->isDigital() ? 1 : 0,
            'total_sessions_completed' => $userBookEdition->getTotalSessionsCompleted(),
            'personal_notes' => $userBookEdition->getPersonalNotes(),
        ];
    }
}
