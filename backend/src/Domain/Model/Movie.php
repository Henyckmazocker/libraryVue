<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;

class Movie
{
    private MovieIdentifier $id;
    private string $title;
    private ?string $originalTitle;
    private ?string $director;
    private ?string $coverUrl;
    private ?Rating $rating; // General movie rating
    private ?Rating $userRating; // User's personal rating
    private ?string $description;
    private array $userStatuses;
    private Timestamp $addedTimestamp;
    private array $allowedStatuses;
    private ?array $tags;
    private ?array $allowedTags;
    /** @var Genre[]|null */
    private ?array $genres; // Géneros de la película
    private ?array $ownershipFormat; // Formato de posesión (id, value, label)
    private string $mediaType; // 'movie' o 'series'
    private ?int $totalSeasons; // Número de temporadas (solo series)

    public function __construct(
        MovieIdentifier $id,
        string $title,
        ?string $originalTitle,
        ?string $director,
        ?string $coverUrl,
        ?Rating $rating,
        ?Rating $userRating,
        ?string $description,
        array $userStatuses,
        Timestamp $addedTimestamp,
        array $allowedStatuses = [],
        ?array $tags = null,
        ?array $allowedTags = null,
        ?array $genres = null,
        ?array $ownershipFormat = null,
        string $mediaType = 'movie',
        ?int $totalSeasons = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->originalTitle = $originalTitle;
        $this->director = $director;
        $this->coverUrl = $coverUrl;
        $this->rating = $rating;
        $this->userRating = $userRating;
        $this->description = $description;
        $this->userStatuses = $userStatuses;
        $this->addedTimestamp = $addedTimestamp;
        $this->allowedStatuses = $allowedStatuses;
        $this->tags = $tags;
        $this->allowedTags = $allowedTags;
        $this->genres = $genres;
        $this->ownershipFormat = $ownershipFormat;
        $this->mediaType = in_array($mediaType, ['movie', 'series']) ? $mediaType : 'movie';
        $this->totalSeasons = $totalSeasons;
    }

    public static function fromArray(array $data): self
    {
        if ((empty($data['id']) && empty($data['isbn'])) || empty($data['title'])) {
            throw new InvalidArgumentException('ID and title are required for a movie.');
        }
        if (empty($data['userStatuses']) || !is_array($data['userStatuses'])) {
            throw new InvalidArgumentException('User statuses are required and must be an array.');
        }
        
        $id = MovieIdentifier::fromString(empty($data['id']) ? $data['isbn'] : $data['id']);
        $rating = isset($data['rating']) && is_numeric($data['rating']) 
            ? Rating::fromNullableFloat((float)$data['rating']) 
            : null;
        $userRating = isset($data['user_rating']) && is_numeric($data['user_rating']) 
            ? Rating::fromNullableFloat((float)$data['user_rating']) 
            : null;
        $addedTimestamp = isset($data['addedTimestamp']) 
            ? Timestamp::fromUnixTimestamp($data['addedTimestamp']) 
            : Timestamp::now();
        
        $genres = null;
        if (isset($data['genres']) && is_array($data['genres'])) {
            $genres = array_map(fn($g) => Genre::fromString($g), $data['genres']);
        }
        
        return new self(
            $id,
            $data['title'],
            $data['originalTitle'] ?? null,
            $data['director'] ?? null,
            $data['coverUrl'] ?? null,
            $rating,
            $userRating,
            $data['description'] ?? null,
            $data['userStatuses'],
            $addedTimestamp,
            $data['allowedStatuses'] ?? [],
            $data['tags'] ?? null,
            $data['allowedTags'] ?? null,
            $genres,
            $data['ownership_format'] ?? $data['ownershipFormat'] ?? null,
            $data['media_type'] ?? $data['mediaType'] ?? 'movie',
            isset($data['total_seasons']) ? (int)$data['total_seasons'] : (isset($data['totalSeasons']) ? (int)$data['totalSeasons'] : null)
        );
    }

    public function getId(): MovieIdentifier { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getOriginalTitle(): ?string { return $this->originalTitle; }
    public function getDirector(): ?string { return $this->director; }
    public function getCoverUrl(): ?string { return $this->coverUrl; }
    public function getRating(): ?Rating { return $this->rating; }
    public function getUserRating(): ?Rating { return $this->userRating; }
    public function getDescription(): ?string { return $this->description; }
    public function getUserStatuses(): array { return $this->userStatuses; }
    public function getAddedTimestamp(): Timestamp { return $this->addedTimestamp; }
    public function getAllowedStatuses(): array { return $this->allowedStatuses; }
    public function getTags(): ?array { return $this->tags; }
    public function getAllowedTags(): ?array { return $this->allowedTags; }
    /** @return Genre[]|null */
    public function getGenres(): ?array { return $this->genres; }

    public function setTags(?array $tags): void { $this->tags = $tags; }
    public function setAllowedTags(?array $allowedTags): void { $this->allowedTags = $allowedTags; }
    public function setGenres(?array $genres): void { $this->genres = $genres; }

    public function setTitle(string $title): void 
    { 
        $this->title = $title; 
    }

    public function setOriginalTitle(?string $originalTitle): void 
    { 
        $this->originalTitle = $originalTitle; 
    }

    public function setDirector(?string $director): void 
    { 
        $this->director = $director; 
    }

    public function setCoverUrl(?string $coverUrl): void 
    { 
        $this->coverUrl = $coverUrl; 
    }

    public function setRating(?Rating $rating): void 
    { 
        $this->rating = $rating; 
    }

    public function setUserRating(?Rating $userRating): void 
    { 
        $this->userRating = $userRating; 
    }

    public function setDescription(?string $description): void 
    { 
        $this->description = $description; 
    }

    public function setUserStatuses(array $userStatuses): void 
    { 
        $this->userStatuses = $userStatuses; 
    }

    public function setAddedTimestamp(Timestamp $addedTimestamp): void 
    { 
        $this->addedTimestamp = $addedTimestamp; 
    }

    public function setAllowedStatuses(array $allowedStatuses): void 
    { 
        $this->allowedStatuses = $allowedStatuses; 
    }

    public function getOwnershipFormat(): ?array { return $this->ownershipFormat; }
    public function setOwnershipFormat(?array $ownershipFormat): void { $this->ownershipFormat = $ownershipFormat; }
    public function getMediaType(): string { return $this->mediaType; }
    public function setMediaType(string $mediaType): void { $this->mediaType = in_array($mediaType, ['movie', 'series']) ? $mediaType : 'movie'; }
    public function getTotalSeasons(): ?int { return $this->totalSeasons; }
    public function setTotalSeasons(?int $totalSeasons): void { $this->totalSeasons = $totalSeasons; }

    public function toArray(): array
    {
        $idString = $this->id->toString();
        $genres = null;
        if ($this->genres !== null) {
            $genres = array_map(fn(Genre $g) => $g->toString(), $this->genres);
        }
        
        return [
            'id' => $idString,
            'isbn' => $idString, // Alias para compatibilidad con backend
            'imdbID' => $idString, // Alias para compatibilidad con frontend
            'title' => $this->title,
            'originalTitle' => $this->originalTitle,
            'director' => $this->director,
            'coverUrl' => $this->coverUrl,
            'rating' => $this->rating?->toFloat(),
            'user_rating' => $this->userRating?->toFloat(),
            'description' => $this->description,
            'userStatuses' => $this->userStatuses,
            'addedTimestamp' => $this->addedTimestamp->toUnixTimestamp(),
            'allowedStatuses' => $this->allowedStatuses,
            'tags' => $this->tags,
            'allowedTags' => $this->allowedTags,
            'genres' => $genres,
            'ownership_format'       => $this->ownershipFormat,
            'ownershipFormat'        => $this->ownershipFormat,
            'ownership_format_value' => $this->ownershipFormat['value'] ?? null,
            'ownership_format_label' => $this->ownershipFormat['label'] ?? null,
            'media_type'   => $this->mediaType,
            'mediaType'    => $this->mediaType,
            'total_seasons' => $this->totalSeasons,
            'totalSeasons'  => $this->totalSeasons,
        ];
    }
}
