<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for adding a book to user's library
 * Encapsulates all data needed to add a book
 */
final readonly class AddBookCommand
{
    /**
     * @param ISBN $isbn Book identifier
     * @param string $title Book title
     * @param int $userId User ID adding the book
     * @param array $statuses User statuses for this book
     * @param string|null $author Book author
     * @param string|null $publisher Publisher name
     * @param int|null $publicationYear Year of publication
     * @param string|null $coverUrl Cover image URL
     * @param Rating|null $rating General book rating
     * @param Rating|null $userRating User's personal rating
     * @param int|null $pages Number of pages
     * @param string|null $description Book description
     * @param array $genres Book genres array
     * @param string|null $language Book language
     */
    public function __construct(
        public ISBN $isbn,
        public string $title,
        public int $userId,
        public array $statuses = [],
        public ?string $author = null,
        public ?string $publisher = null,
        public ?int $publicationYear = null,
        public ?string $coverUrl = null,
        public ?Rating $rating = null,
        public ?Rating $userRating = null,
        public ?int $pages = null,
        public ?string $description = null,
        public array $genres = [],
        public ?string $language = null
    ) {}

    /**
     * Create command from array data
     * Converts primitives to Value Objects
     */
    public static function fromArray(array $data, int $userId): self
    {
        // Process genres array
        $genres = [];
        if (isset($data['genres']) && is_array($data['genres'])) {
            $genres = array_filter($data['genres'], fn($g) => !empty($g));
        } elseif (isset($data['genre']) && !empty($data['genre'])) {
            $genres = [$data['genre']];
        }

        return new self(
            isbn: ISBN::fromString($data['isbn']),
            title: $data['title'],
            userId: $userId,
            statuses: $data['userStatuses'] ?? [],
            author: $data['author'] ?? null,
            publisher: $data['publisher'] ?? null,
            publicationYear: isset($data['publicationDate']) && is_numeric($data['publicationDate'])
                ? (int)$data['publicationDate']
                : ($data['publication_year'] ?? null),
            coverUrl: $data['coverUrl'] ?? $data['cover_url'] ?? null,
            rating: isset($data['rating']) && is_numeric($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null,
            userRating: isset($data['user_rating']) && is_numeric($data['user_rating']) && (float)$data['user_rating'] > 0
                ? Rating::fromNullableFloat((float)$data['user_rating'])
                : null,
            pages: isset($data['pages']) && is_numeric($data['pages'])
                ? (int)$data['pages']
                : null,
            description: is_array($data['description'] ?? null)
                ? implode(' ', $data['description'])
                : ($data['description'] ?? null),
            genres: $genres,
            language: $data['language'] ?? null
        );
    }

    /**
     * Convert to array for Book entity creation
     */
    public function toArray(): array
    {
        return [
            'isbn' => $this->isbn->toString(),
            'title' => $this->title,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'publication_year' => $this->publicationYear,
            'publicationDate' => $this->publicationYear,
            'coverUrl' => $this->coverUrl,
            'cover_url' => $this->coverUrl,
            'rating' => $this->rating?->toFloat(),
            'user_rating' => $this->userRating?->toFloat(),
            'pages' => $this->pages,
            'description' => $this->description,
            'userStatuses' => $this->statuses,
            'allowedStatuses' => [],
            'genres' => $this->genres,
            'language' => $this->language
        ];
    }
}
