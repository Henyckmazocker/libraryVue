<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\GameIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;

/**
 * Command DTO for adding a game to user's library
 */
final readonly class AddGameCommand
{
    /**
     * @param GameIdentifier $id Game identifier (RAWG API ID)
     * @param string $slug Game slug
     * @param string $title Game title
     * @param int $userId User ID adding the game
     * @param array $statuses User statuses for this game
     * @param string|null $releaseDate Release date (YYYY-MM-DD)
     * @param string|null $developer Developer name
     * @param string|null $publisher Publisher name
     * @param string|null $coverUrl Cover/poster URL
     * @param string|null $backgroundUrl Background image URL
     * @param Rating|null $rating General game rating
     * @param Rating|null $userRating User's personal rating
     * @param string|null $description Game description
     * @param array|null $genres Array of Genre VOs
     * @param array|null $platforms Array of platform names
     * @param string|null $esrbRating ESRB rating (E, T, M, AO)
     * @param int|null $playtime Average playtime in hours
     * @param int|null $metacriticScore Metacritic score (0-100)
     * @param float|null $hoursPlayed User's hours played
     * @param string|null $platformPlayed Platform user played on
     * @param string|null $dateStarted Date when user started playing (YYYY-MM-DD)
     * @param string|null $dateFinished Date when user finished playing (YYYY-MM-DD)
     * @param string|null $personalNotes User's personal notes about the game
     */
    public function __construct(
        public GameIdentifier $id,
        public string $slug,
        public string $title,
        public int $userId,
        public array $statuses = [],
        public ?string $releaseDate = null,
        public ?string $developer = null,
        public ?string $publisher = null,
        public ?string $coverUrl = null,
        public ?string $backgroundUrl = null,
        public ?Rating $rating = null,
        public ?Rating $userRating = null,
        public ?string $description = null,
        public ?array $genres = null,
        public ?array $platforms = null,
        public ?string $esrbRating = null,
        public ?int $playtime = null,
        public ?int $metacriticScore = null,
        public ?float $hoursPlayed = null,
        public ?string $platformPlayed = null,
        public ?string $dateStarted = null,
        public ?string $dateFinished = null,
        public ?string $personalNotes = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        // Parse genres
        $genres = null;
        if (isset($data['genres'])) {
            if (is_array($data['genres'])) {
                $genres = array_map(
                    fn($g) => is_string($g) ? Genre::fromString($g) : $g,
                    $data['genres']
                );
            } elseif (is_string($data['genres']) && !empty($data['genres'])) {
                // Parse comma-separated string
                $genreStrings = array_map('trim', explode(',', $data['genres']));
                $genres = array_map(fn($g) => Genre::fromString($g), $genreStrings);
            }
        }

        // Parse platforms
        $platforms = $data['platforms'] ?? null;
        if (is_string($platforms) && !empty($platforms)) {
            // Parse comma-separated string or JSON
            if (str_starts_with(trim($platforms), '[')) {
                $platforms = json_decode($platforms, true);
            } else {
                $platforms = array_map('trim', explode(',', $platforms));
            }
        } elseif (!is_array($platforms)) {
            $platforms = null;
        }

        // Generate slug from title if not provided
        $slug = $data['slug'] ?? self::generateSlug($data['title']);

        return new self(
            id: is_int($data['id']) 
                ? GameIdentifier::fromInt($data['id'])
                : GameIdentifier::fromString($data['id']),
            slug: $slug,
            title: $data['title'],
            userId: $userId,
            statuses: $data['userStatuses'] ?? [],
            releaseDate: $data['release_date'] ?? $data['releaseDate'] ?? null,
            developer: $data['developer'] ?? null,
            publisher: $data['publisher'] ?? null,
            coverUrl: $data['coverUrl'] ?? $data['cover_url'] ?? $data['background_image'] ?? null,
            backgroundUrl: $data['backgroundUrl'] ?? $data['background_url'] ?? $data['background_image'] ?? null,
            rating: isset($data['rating']) && is_numeric($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null,
            userRating: isset($data['user_rating']) && is_numeric($data['user_rating']) && (float)$data['user_rating'] > 0
                ? Rating::fromNullableFloat((float)$data['user_rating'])
                : null,
            description: $data['description'] ?? $data['description_raw'] ?? null,
            genres: $genres,
            platforms: $platforms,
            esrbRating: $data['esrb_rating'] ?? $data['esrbRating'] ?? null,
            playtime: isset($data['playtime']) && is_numeric($data['playtime'])
                ? (int)$data['playtime']
                : null,
            metacriticScore: isset($data['metacritic_score']) && is_numeric($data['metacritic_score'])
                ? (int)$data['metacritic_score']
                : (isset($data['metacritic']) && is_numeric($data['metacritic'])
                    ? (int)$data['metacritic']
                    : null),
            hoursPlayed: isset($data['hours_played']) && is_numeric($data['hours_played'])
                ? (float)$data['hours_played']
                : (isset($data['hoursPlayed']) && is_numeric($data['hoursPlayed'])
                    ? (float)$data['hoursPlayed']
                    : null),
            platformPlayed: $data['platform_played'] ?? $data['platformPlayed'] ?? null,
            dateStarted: !empty($data['date_started']) ? $data['date_started'] : (!empty($data['dateStarted']) ? $data['dateStarted'] : null),
            dateFinished: !empty($data['date_finished']) ? $data['date_finished'] : (!empty($data['dateFinished']) ? $data['dateFinished'] : null),
            personalNotes: $data['personal_notes'] ?? $data['personalNotes'] ?? $data['notes'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'slug' => $this->slug,
            'title' => $this->title,
            'release_date' => $this->releaseDate,
            'developer' => $this->developer,
            'publisher' => $this->publisher,
            'coverUrl' => $this->coverUrl,
            'cover_url' => $this->coverUrl,
            'backgroundUrl' => $this->backgroundUrl,
            'background_url' => $this->backgroundUrl,
            'rating' => $this->rating?->toFloat(),
            'user_rating' => $this->userRating?->toFloat(),
            'description' => $this->description,
            'userStatuses' => $this->statuses,
            'genres' => $this->genres !== null 
                ? array_map(fn(Genre $g) => $g->toString(), $this->genres)
                : null,
            'platforms' => $this->platforms,
            'esrb_rating' => $this->esrbRating,
            'esrbRating' => $this->esrbRating,
            'playtime' => $this->playtime,
            'metacritic_score' => $this->metacriticScore,
            'metacritic' => $this->metacriticScore,
            'hours_played' => $this->hoursPlayed,
            'platform_played' => $this->platformPlayed,
            'personal_notes' => $this->personalNotes,
        ];
    }

    /**
     * Generate a URL-friendly slug from a title
     */
    private static function generateSlug(string $title): string
    {
        // Convert to lowercase
        $slug = strtolower($title);
        
        // Remove special characters and replace spaces with hyphens
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return $slug;
    }
}
