<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for editing user's book data
 */
final readonly class EditUserBookCommand
{
    /**
     * @param ISBN $isbn
     * @param int $userId
     * @param Rating|null $userRating
     * @param array $statuses
     * @param array $tags Array of tag IDs
     * @param int|null $currentPage
     * @param string|null $personalNotes
     * @param string|null $consumedAt
     */
    public function __construct(
        public ISBN $isbn,
        public int $userId,
        public ?Rating $userRating = null,
        public ?array $statuses = null,
        public array $tags = [],
        public ?int $currentPage = null,
        public ?string $personalNotes = null,
        public ?string $consumedAt = null,
        public ?int $ownershipFormatId = null,
        public ?int $pages = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        // Si los datos vienen dentro de un sub-array 'data', extraerlos
        $bookData = $data['data'] ?? $data;
        
        return new self(
            isbn: ISBN::fromString($data['isbn']),
            userId: $userId,
            userRating: isset($bookData['user_rating']) && is_numeric($bookData['user_rating']) && (float)$bookData['user_rating'] > 0
                ? Rating::fromNullableFloat((float)$bookData['user_rating'])
                : (isset($bookData['personalRating']) && is_numeric($bookData['personalRating']) && (float)$bookData['personalRating'] > 0
                    ? Rating::fromNullableFloat((float)$bookData['personalRating'])
                    : null),
            statuses: $bookData['statuses'] ?? $data['statuses'] ?? null,
            tags: $data['tags'] ?? [],
            currentPage: isset($bookData['current_page']) && is_numeric($bookData['current_page']) 
                ? (int)$bookData['current_page'] 
                : (isset($bookData['currentPage']) && is_numeric($bookData['currentPage'])
                    ? (int)$bookData['currentPage']
                    : null),
            personalNotes: $bookData['personal_notes'] ?? $bookData['personalNotes'] ?? null,
            consumedAt: $bookData['consumed_at'] ?? $bookData['consumedAt'] ?? null,
            ownershipFormatId: isset($bookData['ownership_format_id']) ? (int)$bookData['ownership_format_id'] : (isset($bookData['ownershipFormatId']) ? (int)$bookData['ownershipFormatId'] : null),
            pages: isset($bookData['pages']) && is_numeric($bookData['pages']) && (int)$bookData['pages'] > 0
                ? (int)$bookData['pages']
                : null
        );
    }
}
