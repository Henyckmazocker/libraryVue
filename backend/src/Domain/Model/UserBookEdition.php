<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Timestamp;

/**
 * UserBookEdition - Represents a user's ownership of a specific edition
 * 
 * This is the user's library entry for a book edition they own.
 * Contains user-specific data like reading progress, ratings, and ownership details.
 */
class UserBookEdition
{
    private ?int $id;
    private int $userId;
    private int $editionId;
    private Timestamp $addedAt;
    private ?Timestamp $consumedAt;
    private int $currentPage;
    private ?int $activeReadingSessionId;
    private ?Rating $editionRating; // Rating of this specific edition's quality
    private ?Rating $workRating; // Rating of the literary work itself
    private string $ownershipType;
    private ?Timestamp $acquisitionDate;
    private ?string $acquisitionType;
    private ?string $condition;
    private ?string $conditionNotes;
    private ?string $location;
    private bool $isDigital;
    private int $totalSessionsCompleted;
    private ?Timestamp $lastSessionCompletedAt;
    private ?string $personalNotes;
    private ?array $ownershipFormat = null; // Formato de posesión (id, value, label)

    public function __construct(
        int $userId,
        int $editionId,
        ?int $id = null
    ) {
        $this->userId = $userId;
        $this->editionId = $editionId;
        $this->id = $id;
        $this->addedAt = Timestamp::now();
        $this->currentPage = 0;
        $this->ownershipType = 'physical';
        $this->isDigital = false;
        $this->totalSessionsCompleted = 0;
        
        // Initialize all nullable properties
        $this->consumedAt = null;
        $this->activeReadingSessionId = null;
        $this->editionRating = null;
        $this->workRating = null;
        $this->acquisitionDate = null;
        $this->acquisitionType = null;
        $this->condition = null;
        $this->conditionNotes = null;
        $this->location = null;
        $this->lastSessionCompletedAt = null;
        $this->personalNotes = null;
        $this->ownershipFormat = null;
    }

    // Getters and Setters
    public function getId(): ?int 
    { 
        return $this->id; 
    }

    public function setId(int $id): void 
    { 
        $this->id = $id; 
    }

    public function getUserId(): int 
    { 
        return $this->userId; 
    }

    public function getEditionId(): int 
    { 
        return $this->editionId; 
    }

    public function getAddedAt(): Timestamp
    {
        return $this->addedAt;
    }

    public function getCurrentPage(): int 
    { 
        return $this->currentPage; 
    }

    public function setCurrentPage(int $page): void 
    { 
        if ($page < 0) {
            throw new \InvalidArgumentException('Current page must be non-negative.');
        }
        $this->currentPage = $page; 
    }

    public function getConsumedAt(): ?Timestamp 
    { 
        return $this->consumedAt; 
    }
    
    public function markAsConsumed(): void
    {
        $this->consumedAt = Timestamp::now();
    }

    public function unmarkAsConsumed(): void
    {
        $this->consumedAt = null;
    }

    public function getActiveReadingSessionId(): ?int
    {
        return $this->activeReadingSessionId;
    }

    public function setActiveReadingSessionId(?int $sessionId): void
    {
        $this->activeReadingSessionId = $sessionId;
    }

    public function getEditionRating(): ?Rating 
    { 
        return $this->editionRating; 
    }

    public function setEditionRating(?Rating $rating): void 
    { 
        $this->editionRating = $rating; 
    }

    public function getWorkRating(): ?Rating 
    { 
        return $this->workRating; 
    }

    public function setWorkRating(?Rating $rating): void 
    { 
        $this->workRating = $rating; 
    }

    public function getOwnershipType(): string
    {
        return $this->ownershipType;
    }

    public function setOwnershipType(string $type): void
    {
        $allowedTypes = ['physical', 'ebook', 'audiobook', 'borrowed', 'wishlist'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new \InvalidArgumentException('Invalid ownership type: ' . $type);
        }
        $this->ownershipType = $type;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setCondition(?string $condition): void
    {
        if ($condition !== null) {
            $allowedConditions = ['mint', 'like-new', 'very-good', 'good', 'acceptable', 'poor'];
            if (!in_array($condition, $allowedConditions, true)) {
                throw new \InvalidArgumentException('Invalid condition: ' . $condition);
            }
        }
        $this->condition = $condition;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function isDigital(): bool
    {
        return $this->isDigital;
    }

    public function setIsDigital(bool $isDigital): void
    {
        $this->isDigital = $isDigital;
    }

    public function getTotalSessionsCompleted(): int
    {
        return $this->totalSessionsCompleted;
    }

    public function incrementSessionsCompleted(): void
    {
        $this->totalSessionsCompleted++;
        $this->lastSessionCompletedAt = Timestamp::now();
    }

    public function getPersonalNotes(): ?string 
    { 
        return $this->personalNotes; 
    }

    public function setPersonalNotes(?string $notes): void 
    { 
        $this->personalNotes = $notes; 
    }

    public function getOwnershipFormat(): ?array { return $this->ownershipFormat; }
    public function setOwnershipFormat(?array $ownershipFormat): void { $this->ownershipFormat = $ownershipFormat; }

    /**
     * Convert to legacy format for frontend compatibility
     * CRITICAL: Frontend expects these exact field names
     */
    public function toLegacyFormat(): array
    {
        return [
            'user_edition_id' => $this->id,
            'added_at' => $this->addedAt->toDateTime()->format('Y-m-d H:i:s'),
            'addedTimestamp' => $this->addedAt->toUnixTimestamp(),
            'consumed_at' => $this->consumedAt?->toDateTime()->format('Y-m-d H:i:s'),
            'current_page' => $this->currentPage,
            'currentPage' => $this->currentPage, // Compatibility with camelCase
            'personal_rating' => $this->editionRating?->toFloat(), // Frontend uses "personal_rating"
            'rating' => $this->editionRating?->toFloat(), // General rating field
            'user_rating' => $this->editionRating?->toFloat(), // Main frontend field (snake_case)
            'userRating' => $this->editionRating?->toFloat(), // Additional compatibility (camelCase)
            'edition_rating' => $this->editionRating?->toFloat(),
            'work_rating' => $this->workRating?->toFloat(),
            'total_sessions_completed' => $this->totalSessionsCompleted,
            'totalSessionsCompleted' => $this->totalSessionsCompleted,
            'ownership_type' => $this->ownershipType,
            'ownership_format'       => $this->ownershipFormat,
            'ownershipFormat'        => $this->ownershipFormat,
            'ownership_format_value' => $this->ownershipFormat['value'] ?? null,
            'ownership_format_label' => $this->ownershipFormat['label'] ?? null,
            'personal_notes' => $this->personalNotes,
            'personalNotes' => $this->personalNotes,
            'active_reading_session_id' => $this->activeReadingSessionId,
            'condition' => $this->condition,
            'location' => $this->location,
        ];
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'edition_id' => $this->editionId,
            'added_at' => $this->addedAt->toDateTime()->format('Y-m-d H:i:s'),
            'consumed_at' => $this->consumedAt?->toDateTime()->format('Y-m-d H:i:s'),
            'current_page' => $this->currentPage,
            'active_reading_session_id' => $this->activeReadingSessionId,
            'edition_rating' => $this->editionRating?->toFloat(),
            'work_rating' => $this->workRating?->toFloat(),
            'ownership_type' => $this->ownershipType,
            'ownership_format'       => $this->ownershipFormat,
            'ownershipFormat'        => $this->ownershipFormat,
            'ownership_format_value' => $this->ownershipFormat['value'] ?? null,
            'ownership_format_label' => $this->ownershipFormat['label'] ?? null,
            'acquisition_date' => $this->acquisitionDate?->toDateTime()->format('Y-m-d'),
            'acquisition_type' => $this->acquisitionType,
            'condition' => $this->condition,
            'condition_notes' => $this->conditionNotes,
            'location' => $this->location,
            'is_digital' => $this->isDigital,
            'total_sessions_completed' => $this->totalSessionsCompleted,
            'last_session_completed_at' => $this->lastSessionCompletedAt?->toDateTime()->format('Y-m-d H:i:s'),
            'personal_notes' => $this->personalNotes,
        ];
    }

    /**
     * Create UserBookEdition from array data
     */
    public static function fromArray(array $data): self
    {
        $userBookEdition = new self(
            $data['user_id'],
            $data['edition_id'],
            $data['id'] ?? null
        );

        if (isset($data['current_page'])) {
            $userBookEdition->setCurrentPage($data['current_page']);
        }
        if (isset($data['consumed_at']) && $data['consumed_at']) {
            $userBookEdition->markAsConsumed();
        }
        if (isset($data['active_reading_session_id'])) {
            $userBookEdition->setActiveReadingSessionId($data['active_reading_session_id']);
        }
        if (isset($data['edition_rating']) && $data['edition_rating'] !== null) {
            $userBookEdition->setEditionRating(Rating::fromNullableFloat((float) $data['edition_rating']));
        }
        if (isset($data['work_rating']) && $data['work_rating'] !== null) {
            $userBookEdition->setWorkRating(Rating::fromNullableFloat((float) $data['work_rating']));
        }
        if (isset($data['ownership_type'])) {
            $userBookEdition->setOwnershipType($data['ownership_type']);
        }
        if (isset($data['condition'])) {
            $userBookEdition->setCondition($data['condition']);
        }
        if (isset($data['location'])) {
            $userBookEdition->setLocation($data['location']);
        }
        if (isset($data['is_digital'])) {
            $userBookEdition->setIsDigital((bool) $data['is_digital']);
        }
        if (isset($data['personal_notes'])) {
            $userBookEdition->setPersonalNotes($data['personal_notes']);
        }
        $ownershipFormat = $data['ownership_format'] ?? $data['ownershipFormat'] ?? null;
        if ($ownershipFormat !== null) {
            $userBookEdition->setOwnershipFormat($ownershipFormat);
        }

        return $userBookEdition;
    }
}
