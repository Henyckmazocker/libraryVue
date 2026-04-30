<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for editing user's album data
 *
 * Uses ?array $statuses = null to distinguish between:
 * - null  → statuses were not sent; do not touch them
 * - []    → user cleared all statuses; remove them all
 */
final readonly class EditUserAlbumCommand
{
    public function __construct(
        public int $userId,
        public int $albumId,
        public ?Rating $userRating = null,
        public ?string $personalNotes = null,
        public ?int $listenCount = null,
        public ?string $favoriteTrack = null,
        public ?string $completedAt = null,
        public ?string $dateStarted = null,
        public ?string $dateFinished = null,
        public ?array $statuses = null,
        public array $tags = [],
        public ?int $ownershipFormatId = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['albumId'])) {
            throw new \InvalidArgumentException('Album ID is required.');
        }

        // If data is nested inside a 'data' sub-array (frontend EditItemModal pattern)
        $albumData = $data['data'] ?? $data;

        $userRating = null;
        $ratingValue = $albumData['personalRating'] ?? $albumData['user_rating'] ?? null;
        if ($ratingValue !== null && is_numeric($ratingValue)) {
            $userRating = Rating::fromNullableFloat((float)$ratingValue);
        }

        $listenCount = null;
        $listenValue = $albumData['listenCount'] ?? $albumData['listen_count'] ?? null;
        if ($listenValue !== null && is_numeric($listenValue)) {
            $listenCount = (int)$listenValue;
        }

        return new self(
            userId: $userId,
            albumId: is_int($data['albumId']) ? $data['albumId'] : (int)$data['albumId'],
            userRating: $userRating,
            personalNotes: $albumData['personal_notes'] ?? $albumData['personalNotes'] ?? $albumData['notes'] ?? null,
            listenCount: $listenCount,
            favoriteTrack: $albumData['favorite_track'] ?? $albumData['favoriteTrack'] ?? null,
            completedAt: $albumData['completed_at'] ?? $albumData['completedAt'] ?? null,
            dateStarted: !empty($albumData['dateStarted'])
                ? $albumData['dateStarted']
                : (!empty($albumData['date_started']) ? $albumData['date_started'] : null),
            dateFinished: !empty($albumData['dateFinished'])
                ? $albumData['dateFinished']
                : (!empty($albumData['date_finished']) ? $albumData['date_finished'] : null),
            statuses: $albumData['statuses'] ?? $data['statuses'] ?? null,
            tags: $data['tags'] ?? [],
            ownershipFormatId: isset($albumData['ownership_format_id']) ? (int)$albumData['ownership_format_id'] : (isset($albumData['ownershipFormatId']) ? (int)$albumData['ownershipFormatId'] : null)
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->userRating !== null) {
            $data['personal_rating'] = $this->userRating->toFloat();
        }
        if ($this->personalNotes !== null) {
            $data['personal_notes'] = $this->personalNotes;
        }
        if ($this->listenCount !== null) {
            $data['listen_count'] = $this->listenCount;
        }
        if ($this->favoriteTrack !== null) {
            $data['favorite_track'] = $this->favoriteTrack;
        }
        if ($this->completedAt !== null) {
            $data['completed_at'] = $this->completedAt;
        }
        if ($this->dateStarted !== null) {
            $data['date_started'] = $this->dateStarted;
        }
        if ($this->dateFinished !== null) {
            $data['date_finished'] = $this->dateFinished;
        }
        if ($this->ownershipFormatId !== null) {
            $data['ownership_format_id'] = $this->ownershipFormatId;
        }

        return $data;
    }
}
