<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;

/**
 * Command DTO for adding a movie to user's library
 */
final readonly class AddMovieCommand
{
    /**
     * @param MovieIdentifier $id Movie identifier (IMDB/ISBN)
     * @param string $title Movie title
     * @param int $userId User ID adding the movie
     * @param array $statuses User statuses for this movie
     * @param string|null $originalTitle Original title
     * @param string|null $director Director name
     * @param string|null $coverUrl Cover/poster URL
     * @param Rating|null $rating General movie rating
     * @param Rating|null $userRating User's personal rating
     * @param string|null $description Movie description/plot
     * @param array|null $genres Array of Genre VOs
     */
    public function __construct(
        public MovieIdentifier $id,
        public string $title,
        public int $userId,
        public array $statuses = [],
        public ?string $originalTitle = null,
        public ?string $director = null,
        public ?string $coverUrl = null,
        public ?Rating $rating = null,
        public ?Rating $userRating = null,
        public ?string $description = null,
        public ?array $genres = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        // Parse genres
        $genres = null;
        if (isset($data['genres']) && is_array($data['genres'])) {
            $genres = array_map(
                fn($g) => is_string($g) ? Genre::fromString($g) : $g,
                $data['genres']
            );
        }

        return new self(
            id: MovieIdentifier::fromString($data['id'] ?? $data['isbn'] ?? $data['imdbID']),
            title: $data['title'],
            userId: $userId,
            statuses: $data['userStatuses'] ?? [],
            originalTitle: $data['original_title'] ?? $data['originalTitle'] ?? null,
            director: $data['director'] ?? null,
            coverUrl: $data['coverUrl'] ?? $data['cover_url'] ?? $data['Poster'] ?? null,
            rating: isset($data['rating']) && is_numeric($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null,
            userRating: isset($data['user_rating']) && is_numeric($data['user_rating']) && (float)$data['user_rating'] > 0
                ? Rating::fromNullableFloat((float)$data['user_rating'])
                : null,
            description: $data['description'] ?? $data['Plot'] ?? null,
            genres: $genres
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'isbn' => $this->id->toString(),
            'imdbID' => $this->id->toString(),
            'title' => $this->title,
            'original_title' => $this->originalTitle,
            'director' => $this->director,
            'coverUrl' => $this->coverUrl,
            'cover_url' => $this->coverUrl,
            'rating' => $this->rating?->toFloat(),
            'user_rating' => $this->userRating?->toFloat(),
            'description' => $this->description,
            'userStatuses' => $this->statuses,
            'genres' => $this->genres !== null 
                ? array_map(fn(Genre $g) => $g->toString(), $this->genres)
                : null
        ];
    }
}
