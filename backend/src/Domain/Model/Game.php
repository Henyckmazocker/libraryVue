<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Model\ValueObjects\GameIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;

class Game
{
    private GameIdentifier $id;
    private string $slug;
    private string $title;
    private ?string $releaseDate; // Stored as DATE string "YYYY-MM-DD"
    private ?string $developer;
    private ?string $publisher;
    private ?string $coverUrl;
    private ?string $backgroundUrl;
    private ?Rating $rating; // General game rating
    private ?Rating $userRating; // User's personal rating
    private ?string $description;
    private array $userStatuses;
    private Timestamp $addedTimestamp;
    private array $allowedStatuses;
    private ?array $tags;
    private ?array $allowedTags;
    /** @var Genre[]|null */
    private ?array $genres; // Game genres
    private ?array $platforms; // Platform names as strings ["PC", "PS4", "Xbox One"]
    private ?string $esrbRating; // ESRB rating: E, T, M, AO, etc.
    private ?int $playtime; // Average playtime in hours
    private ?int $metacriticScore; // Metacritic score 0-100
    private ?float $hoursPlayed; // User's hours played
    private ?string $platformPlayed; // Platform user played on
    private ?string $dateStarted; // Date user started playing (YYYY-MM-DD)
    private ?string $dateFinished; // Date user finished the game (YYYY-MM-DD)
    private ?string $personalNotes; // User's personal notes about the game

    public function __construct(
        GameIdentifier $id,
        string $slug,
        string $title,
        ?string $releaseDate,
        ?string $developer,
        ?string $publisher,
        ?string $coverUrl,
        ?string $backgroundUrl,
        ?Rating $rating,
        ?Rating $userRating,
        ?string $description,
        array $userStatuses,
        Timestamp $addedTimestamp,
        array $allowedStatuses = [],
        ?array $tags = null,
        ?array $allowedTags = null,
        ?array $genres = null,
        ?array $platforms = null,
        ?string $esrbRating = null,
        ?int $playtime = null,
        ?int $metacriticScore = null,
        ?float $hoursPlayed = null,
        ?string $platformPlayed = null,
        ?string $dateStarted = null,
        ?string $dateFinished = null,
        ?string $personalNotes = null
    ) {
        // Validations
        if (empty($title)) {
            throw new InvalidArgumentException('Title cannot be empty.');
        }
        if (empty($slug)) {
            throw new InvalidArgumentException('Slug cannot be empty.');
        }
        if ($playtime !== null && $playtime < 0) {
            throw new InvalidArgumentException('Playtime must be a non-negative integer.');
        }
        if ($metacriticScore !== null && ($metacriticScore < 0 || $metacriticScore > 100)) {
            throw new InvalidArgumentException('Metacritic score must be between 0 and 100.');
        }
        if ($hoursPlayed !== null && $hoursPlayed < 0) {
            throw new InvalidArgumentException('Hours played must be non-negative.');
        }

        $this->id = $id;
        $this->slug = $slug;
        $this->title = $title;
        $this->releaseDate = $releaseDate;
        $this->developer = $developer;
        $this->publisher = $publisher;
        $this->coverUrl = $coverUrl;
        $this->backgroundUrl = $backgroundUrl;
        $this->rating = $rating;
        $this->userRating = $userRating;
        $this->description = $description;
        $this->userStatuses = $userStatuses;
        $this->addedTimestamp = $addedTimestamp;
        $this->allowedStatuses = $allowedStatuses;
        $this->tags = $tags;
        $this->allowedTags = $allowedTags;
        $this->genres = $genres;
        $this->platforms = $platforms;
        $this->esrbRating = $esrbRating;
        $this->playtime = $playtime;
        $this->metacriticScore = $metacriticScore;
        $this->hoursPlayed = $hoursPlayed ?? 0.0;
        $this->platformPlayed = $platformPlayed;
        $this->dateStarted = $dateStarted;
        $this->dateFinished = $dateFinished;
        $this->personalNotes = $personalNotes;
    }

    public static function fromArray(array $data): self
    {
        if (empty($data['id']) || empty($data['slug']) || empty($data['title'])) {
            throw new InvalidArgumentException('ID, slug and title are required for a game.');
        }
        if (empty($data['userStatuses']) || !is_array($data['userStatuses'])) {
            throw new InvalidArgumentException('User statuses are required and must be an array.');
        }
        
        $id = is_int($data['id']) 
            ? GameIdentifier::fromInt($data['id']) 
            : GameIdentifier::fromString($data['id']);
            
        $rating = isset($data['rating']) && is_numeric($data['rating']) 
            ? Rating::fromNullableFloat((float)$data['rating']) 
            : null;
            
        $userRating = isset($data['user_rating']) && is_numeric($data['user_rating']) 
            ? Rating::fromNullableFloat((float)$data['user_rating']) 
            : null;
            
        $addedTimestamp = isset($data['addedTimestamp']) 
            ? Timestamp::fromUnixTimestamp($data['addedTimestamp']) 
            : Timestamp::now();
        
        // Process genres array
        $genres = null;
        if (isset($data['genres']) && is_array($data['genres'])) {
            $genres = array_map(fn($g) => Genre::fromString($g), $data['genres']);
        }
        
        // Process platforms array (kept as strings)
        $platforms = $data['platforms'] ?? null;
        if ($platforms !== null && !is_array($platforms)) {
            $platforms = json_decode($platforms, true);
        }
        
        // Process playtime
        $playtime = isset($data['playtime']) && is_numeric($data['playtime'])
            ? (int)$data['playtime']
            : null;
            
        // Process metacritic score
        $metacriticScore = isset($data['metacritic_score']) && is_numeric($data['metacritic_score'])
            ? (int)$data['metacritic_score']
            : null;
            
        // Process hours played
        $hoursPlayed = isset($data['hours_played']) && is_numeric($data['hours_played'])
            ? (float)$data['hours_played']
            : null;
        
        return new self(
            $id,
            $data['slug'],
            $data['title'],
            $data['release_date'] ?? $data['releaseDate'] ?? null,
            $data['developer'] ?? null,
            $data['publisher'] ?? null,
            $data['coverUrl'] ?? $data['cover_url'] ?? null,
            $data['backgroundUrl'] ?? $data['background_url'] ?? null,
            $rating,
            $userRating,
            $data['description'] ?? null,
            $data['userStatuses'],
            $addedTimestamp,
            $data['allowedStatuses'] ?? [],
            $data['tags'] ?? null,
            $data['allowedTags'] ?? null,
            $genres,
            $platforms,
            $data['esrb_rating'] ?? $data['esrbRating'] ?? null,
            $playtime,
            $metacriticScore,
            $hoursPlayed,
            $data['platform_played'] ?? $data['platformPlayed'] ?? null,
            $data['date_started'] ?? $data['dateStarted'] ?? null,
            $data['date_finished'] ?? $data['dateFinished'] ?? null,
            $data['personal_notes'] ?? $data['personalNotes'] ?? null
        );
    }

    // Getters
    public function getId(): GameIdentifier
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getReleaseDate(): ?string
    {
        return $this->releaseDate;
    }

    public function getDeveloper(): ?string
    {
        return $this->developer;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function getBackgroundUrl(): ?string
    {
        return $this->backgroundUrl;
    }

    public function getRating(): ?Rating
    {
        return $this->rating;
    }

    public function getUserRating(): ?Rating
    {
        return $this->userRating;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUserStatuses(): array
    {
        return $this->userStatuses;
    }

    public function getAddedTimestamp(): Timestamp
    {
        return $this->addedTimestamp;
    }

    public function getAllowedStatuses(): array
    {
        return $this->allowedStatuses;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function getAllowedTags(): ?array
    {
        return $this->allowedTags;
    }

    public function getGenres(): ?array
    {
        return $this->genres;
    }

    public function getPlatforms(): ?array
    {
        return $this->platforms;
    }

    public function getEsrbRating(): ?string
    {
        return $this->esrbRating;
    }

    public function getPlaytime(): ?int
    {
        return $this->playtime;
    }

    public function getMetacriticScore(): ?int
    {
        return $this->metacriticScore;
    }

    public function getHoursPlayed(): ?float
    {
        return $this->hoursPlayed;
    }

    public function getPlatformPlayed(): ?string
    {
        return $this->platformPlayed;
    }

    public function getDateStarted(): ?string
    {
        return $this->dateStarted;
    }

    public function getDateFinished(): ?string
    {
        return $this->dateFinished;
    }

    public function getPersonalNotes(): ?string
    {
        return $this->personalNotes;
    }

    // Utility methods
    public function hasStatus(string $status): bool
    {
        return in_array($status, $this->userStatuses, true);
    }

    public function isCompleted(): bool
    {
        return $this->hasStatus('completed') || $this->hasStatus('100-completed');
    }

    public function isPlaying(): bool
    {
        return $this->hasStatus('playing');
    }

    public function isInWishlist(): bool
    {
        return $this->hasStatus('in-wishlist');
    }

    public function hasGenre(string $genreName): bool
    {
        if ($this->genres === null) {
            return false;
        }
        
        foreach ($this->genres as $genre) {
            if (strcasecmp($genre->toString(), $genreName) === 0) {
                return true;
            }
        }
        
        return false;
    }

    public function hasPlatform(string $platformName): bool
    {
        if ($this->platforms === null) {
            return false;
        }
        
        foreach ($this->platforms as $platform) {
            if (strcasecmp($platform, $platformName) === 0) {
                return true;
            }
        }
        
        return false;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'slug' => $this->slug,
            'title' => $this->title,
            'name' => $this->title, // Alias for compatibility
            'release_date' => $this->releaseDate,
            'releaseDate' => $this->releaseDate, // camelCase alias
            'released' => $this->releaseDate, // Additional alias for compatibility
            'developer' => $this->developer,
            'publisher' => $this->publisher,
            'coverUrl' => $this->coverUrl,
            'cover_url' => $this->coverUrl, // snake_case alias
            'backgroundUrl' => $this->backgroundUrl,
            'background_url' => $this->backgroundUrl, // snake_case alias
            'rating' => $this->rating?->toFloat(),
            'user_rating' => $this->userRating?->toFloat(),
            'description' => $this->description,
            'userStatuses' => $this->userStatuses,
            'addedTimestamp' => $this->addedTimestamp->toUnixTimestamp(),
            'allowedStatuses' => $this->allowedStatuses,
            'tags' => $this->tags,
            'allowedTags' => $this->allowedTags,
            'genres' => $this->genres !== null 
                ? array_map(fn($g) => $g->toString(), $this->genres) 
                : null,
            'platforms' => $this->platforms,
            'esrb_rating' => $this->esrbRating,
            'esrbRating' => $this->esrbRating, // camelCase alias
            'playtime' => $this->playtime,
            'metacritic_score' => $this->metacriticScore,
            'metacriticScore' => $this->metacriticScore, // camelCase alias
            'metacritic' => $this->metacriticScore, // Additional alias
            'hours_played' => $this->hoursPlayed,
            'hoursPlayed' => $this->hoursPlayed, // camelCase alias
            'platform_played' => $this->platformPlayed,
            'platformPlayed' => $this->platformPlayed, // camelCase alias
            'date_started' => $this->dateStarted,
            'dateStarted' => $this->dateStarted, // camelCase alias
            'date_finished' => $this->dateFinished,
            'dateFinished' => $this->dateFinished, // camelCase alias
            'personal_notes' => $this->personalNotes,
            'personalNotes' => $this->personalNotes, // camelCase alias
            'notes' => $this->personalNotes, // Additional alias
        ];
    }
}
