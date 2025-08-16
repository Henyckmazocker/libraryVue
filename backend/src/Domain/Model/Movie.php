<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

class Movie
{
    private string $id;
    private string $title;
    private ?string $originalTitle;
    private ?string $director;
    private ?string $coverUrl;
    private ?float $rating; // General movie rating
    private ?float $userRating; // User's personal rating
    private ?string $description;
    private array $userStatuses;
    private int $addedTimestamp;
    private array $allowedStatuses;

    public function __construct(
        string $id,
        string $title,
        ?string $originalTitle,
        ?string $director,
        ?string $coverUrl,
        ?float $rating,
        ?float $userRating,
        ?string $description,
        array $userStatuses,
        int $addedTimestamp,
        array $allowedStatuses = []
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
    }

    public static function fromArray(array $data, array $allowedStatuses = []): self
    {
        if (empty($data['id']) || empty($data['title'])) {
            throw new InvalidArgumentException('ID and title are required for a movie.');
        }
        if (empty($data['userStatuses']) || !is_array($data['userStatuses'])) {
            throw new InvalidArgumentException('User statuses are required and must be an array.');
        }
        return new self(
            $data['id'],
            $data['title'],
            $data['originalTitle'] ?? null,
            $data['director'] ?? null,
            $data['coverUrl'] ?? null,
            isset($data['rating']) && is_numeric($data['rating']) ? (float)$data['rating'] : null,
            isset($data['user_rating']) && is_numeric($data['user_rating']) ? (float)$data['user_rating'] : null,
            $data['description'] ?? null,
            $data['userStatuses'],
            $data['addedTimestamp'] ?? time(),
            $allowedStatuses
        );
    }

    public function getId(): string { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getOriginalTitle(): ?string { return $this->originalTitle; }
    public function getDirector(): ?string { return $this->director; }
    public function getCoverUrl(): ?string { return $this->coverUrl; }
    public function getRating(): ?float { return $this->rating; }
    public function getUserRating(): ?float { return $this->userRating; }
    public function getDescription(): ?string { return $this->description; }
    public function getUserStatuses(): array { return $this->userStatuses; }
    public function getAddedTimestamp(): int { return $this->addedTimestamp; }
    public function getAllowedStatuses(): array { return $this->allowedStatuses; }

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

    public function setRating(?float $rating): void 
    { 
        $this->rating = $rating; 
    }

    public function setUserRating(?float $userRating): void 
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

    public function setAddedTimestamp(int $addedTimestamp): void 
    { 
        $this->addedTimestamp = $addedTimestamp; 
    }

    public function setAllowedStatuses(array $allowedStatuses): void 
    { 
        $this->allowedStatuses = $allowedStatuses; 
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'isbn' => $this->id, // Alias para compatibilidad con backend
            'imdbID' => $this->id, // Alias para compatibilidad con frontend
            'title' => $this->title,
            'originalTitle' => $this->originalTitle,
            'director' => $this->director,
            'coverUrl' => $this->coverUrl,
            'rating' => $this->rating,
            'user_rating' => $this->userRating,
            'description' => $this->description,
            'userStatuses' => $this->userStatuses,
            'addedTimestamp' => $this->addedTimestamp,
            'allowedStatuses' => $this->allowedStatuses
        ];
    }
}
