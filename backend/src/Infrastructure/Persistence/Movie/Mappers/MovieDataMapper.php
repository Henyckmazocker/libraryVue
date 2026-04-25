<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Movie\Mappers;

use App\Domain\Model\Movie;
use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

/**
 * Maps between database rows and Movie domain entities
 */
class MovieDataMapper
{
    use HydrationHelpersTrait;

    /**
     * Convert database row to Movie entity
     *
     * @param array $row Database row with snake_case columns
     * @return Movie
     */
    public function toDomain(array $row): Movie
    {
        $id = MovieIdentifier::fromString($this->extractString($row, 'isbn'));
        
        $rating = isset($row['rating']) 
            ? Rating::fromNullableFloat($this->extractFloat($row, 'rating', null))
            : null;
            
        $userRating = isset($row['user_rating']) 
            ? Rating::fromNullableFloat($this->extractFloat($row, 'user_rating', null))
            : null;

        // Parse genres JSON array
        $genresData = $this->extractJson($row, 'genres', []);
        $genres = null;
        if (is_array($genresData)) {
            $genres = array_map(
                fn($g) => is_string($g) ? Genre::fromString($g) : null,
                $genresData
            );
            $genres = array_filter($genres); // Remove nulls
        }

        $addedAt = isset($row['user_added_at'])
            ? Timestamp::fromString($this->extractString($row, 'user_added_at'))
            : Timestamp::now();

        // User statuses as array of strings
        $userStatuses = [];
        if (array_key_exists('user_statuses', $row) && $row['user_statuses'] !== null) {
            if (is_array($row['user_statuses'])) {
                $userStatuses = $row['user_statuses'];
            } elseif (is_string($row['user_statuses']) && $row['user_statuses'] !== '') {
                $userStatuses = explode(', ', $row['user_statuses']);
            }
        }

        return new Movie(
            $id,
            $this->extractString($row, 'title'),
            $this->extractString($row, 'original_title', null),
            $this->extractString($row, 'director', null),
            $this->extractString($row, 'coverUrl', null),
            $rating,
            $userRating,
            $this->extractString($row, 'description', null),
            $userStatuses,
            $addedAt,
            [], // allowedStatuses - loaded separately
            null, // tags - loaded separately
            null, // allowedTags - loaded separately
            $genres,
            $this->buildOwnershipFormat($row)
        );
    }

    /**
     * Convert Movie entity to database array
     *
     * @param Movie $movie
     * @return array Database array with snake_case keys
     */
    public function toPersistence(Movie $movie): array
    {
        return [
            'isbn' => $movie->getId(),
            'title' => $movie->getTitle(),
            'original_title' => $this->toDbValue($movie->getOriginalTitle()),
            'director' => $this->toDbValue($movie->getDirector()),
            'coverUrl' => $this->toDbValue($movie->getCoverUrl()),
            'rating' => $this->toDbValue($movie->getRating()),
            'description' => $this->toDbValue($movie->getDescription()),
            'addedTimestamp' => $movie->getAddedTimestamp()->toUnixTimestamp(),
            'genres' => $this->toDbValue($movie->getGenres(), 'json')
        ];
    }

    /**
     * Convert array of database rows to array of Movie entities
     *
     * @param array $rows
     * @return Movie[]
     */
    public function toDomainCollection(array $rows): array
    {
        return array_map(
            fn(array $row) => $this->toDomain($row),
            $rows
        );
    }
}
