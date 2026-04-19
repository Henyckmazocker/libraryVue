<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Game\Mappers;

use App\Domain\Model\Game;
use App\Domain\Model\ValueObjects\GameIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

/**
 * Maps between database rows and Game domain entities
 */
class GameDataMapper
{
    use HydrationHelpersTrait;

    /**
     * Convert database row to Game entity
     *
     * @param array $row Database row with snake_case columns
     * @return Game
     */
    public function toDomain(array $row): Game
    {
        $id = GameIdentifier::fromInt($this->extractRequiredInt($row, 'id'));
        
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

        // Parse platforms JSON array
        $platforms = $this->extractJson($row, 'platforms', []);
        if (!is_array($platforms)) {
            $platforms = [];
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

        return new Game(
            $id,
            $this->extractString($row, 'slug'),
            $this->extractString($row, 'title'),
            $this->extractString($row, 'release_date', null),
            $this->extractString($row, 'developer', null),
            $this->extractString($row, 'publisher', null),
            $this->extractString($row, 'coverUrl', null),
            $this->extractString($row, 'backgroundUrl', null),
            $rating,
            $userRating,
            $this->extractString($row, 'description', null),
            $userStatuses,  // 12: array $userStatuses - CORRECTED ORDER
            $addedAt,       // 13: Timestamp $addedTimestamp - CORRECTED ORDER
            [],             // 14: array $allowedStatuses - loaded separately
            null,           // 15: ?array $tags - loaded separately
            null,           // 16: ?array $allowedTags - loaded separately
            $genres,        // 17: ?array $genres - CORRECTED ORDER
            $platforms,     // 18: ?array $platforms - CORRECTED ORDER
            $this->extractString($row, 'esrb_rating', null),    // 19: ?string $esrbRating
            $this->extractInt($row, 'playtime', null),          // 20: ?int $playtime
            $this->extractInt($row, 'metacritic_score', null),  // 21: ?int $metacriticScore
            $this->extractFloat($row, 'hours_played', null),    // 22: ?float $hoursPlayed
            $this->extractString($row, 'platform_played', null), // 23: ?string $platformPlayed
            $this->extractString($row, 'date_started', null),    // 24: ?string $dateStarted
            $this->extractString($row, 'date_finished', null),    // 25: ?string $dateFinished
            $this->extractString($row, 'personal_notes', null)    // 26: ?string $personalNotes
        );
    }

    /**
     * Convert Game entity to database array
     *
     * @param Game $game
     * @return array Database array with snake_case keys
     */
    public function toPersistence(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'slug' => $game->getSlug(),
            'title' => $game->getTitle(),
            'release_date' => $this->toDbValue($game->getReleaseDate()),
            'developer' => $this->toDbValue($game->getDeveloper()),
            'publisher' => $this->toDbValue($game->getPublisher()),
            'coverUrl' => $this->toDbValue($game->getCoverUrl()),
            'backgroundUrl' => $this->toDbValue($game->getBackgroundUrl()),
            'rating' => $this->toDbValue($game->getRating()),
            'description' => $this->toDbValue($game->getDescription()),
            'platforms' => $this->toDbValue($game->getPlatforms(), 'json'),
            'genres' => $this->toDbValue($game->getGenres(), 'json'),
            'esrb_rating' => $this->toDbValue($game->getEsrbRating()),
            'playtime' => $this->toDbValue($game->getPlaytime()),
            'metacritic_score' => $this->toDbValue($game->getMetacriticScore()),
            'addedTimestamp' => $game->getAddedTimestamp()->toUnixTimestamp()
        ];
    }

    /**
     * Convert array of database rows to array of Game entities
     *
     * @param array $rows
     * @return Game[]
     */
    public function toDomainCollection(array $rows): array
    {
        return array_map(
            fn(array $row) => $this->toDomain($row),
            $rows
        );
    }
}
