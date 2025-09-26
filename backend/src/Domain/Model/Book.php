<?php

declare(strict_types=1);

namespace App\Domain\Model;

class Book
{
    private string $isbn;
    private string $title;
    private ?string $author;
    private ?string $publisher;
    private ?string $publicationDate;
    private ?string $coverUrl;
    private ?float $rating; // Nullable float for rating (general rating)
    private ?float $userRating; // Nullable float for user's personal rating
    private ?int $pages;
    private ?string $description;
    private ?int $addedTimestamp;
    private ?int $currentPage; // Página actual del usuario
    private array $userStatuses;
    private ?array $tags;
    private ?array $allowedTags;
    private ?array $genres; // Géneros del libro

    public function __construct(
        string $isbn,
        string $title,
        ?string $author,
        ?string $publisher,
        ?string $publicationDate,
        ?string $coverUrl,
        ?float $rating,
        ?float $userRating,
        ?int $pages,
        ?string $description,
        array $userStatuses,
        array $allowedStatuses,
        ?int $addedTimestamp = null,
        ?int $currentPage = null,
        ?array $tags = null,
        ?array $allowedTags = null,
        ?array $genres = null
    ) {
        if (empty($isbn)) {
            throw new \InvalidArgumentException('ISBN cannot be empty.');
        }
        if (empty($title)) {
            throw new \InvalidArgumentException('Title cannot be empty.');
        }
        if ($rating !== null && ($rating < 0.5 || $rating > 5)) {
            throw new \InvalidArgumentException('Rating must be between 0.5 and 5, or null.');
        }
        // Additional validation for rating being a multiple of 0.5 can be added here if desired
        if ($rating !== null && floor($rating * 2) != $rating * 2) {
            throw new \InvalidArgumentException('Rating must be a multiple of 0.5.');
        }
        if ($userRating !== null && ($userRating < 0.5 || $userRating > 5)) {
            throw new \InvalidArgumentException('User rating must be between 0.5 and 5, or null.');
        }
        if ($userRating !== null && floor($userRating * 2) != $userRating * 2) {
            throw new \InvalidArgumentException('User rating must be a multiple of 0.5.');
        }
        if ($pages !== null && $pages <= 0) {
            throw new \InvalidArgumentException('Pages must be a positive integer, or null.');
        }
        if ($currentPage !== null && $currentPage < 0) {
            throw new \InvalidArgumentException('Current page must be a non-negative integer, or null.');
        }
        if ($currentPage !== null && $pages !== null && $currentPage > $pages) {
            throw new \InvalidArgumentException('Current page cannot be greater than total pages.');
        }
        // Permitir userStatuses vacío (mostrar en la vista, no lanzar excepción)
        if (!is_array($userStatuses)) {
            $userStatuses = [];
        }
        foreach ($userStatuses as $status) {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException("Invalid status: {$status}. Allowed statuses are: " . implode(', ', $allowedStatuses));
            }
        }
        $this->tags = $tags;
        $this->genres = $genres;
        $this->isbn = $isbn;
        $this->title = $title;
        $this->author = $author;
        $this->publisher = $publisher;
        $this->publicationDate = $publicationDate;
        $this->coverUrl = $coverUrl;
        $this->rating = $rating;
        $this->userRating = $userRating;
        $this->pages = $pages;
        $this->description = $description;
        $this->currentPage = $currentPage ?? 0;
        $this->userStatuses = array_unique($userStatuses);
        $this->addedTimestamp = $addedTimestamp ?? time();
        $this->allowedTags = $allowedTags;
    }

    public function getAllowedTags(): ?array
    {
        return $this->allowedTags;
    }

    public function setAllowedTags(?array $allowedTags): void
    {
        $this->allowedTags = $allowedTags;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): void
    {
        $this->tags = $tags;
    }

    public function getGenres(): ?array
    {
        return $this->genres;
    }

    public function setGenres(?array $genres): void
    {
        $this->genres = $genres;
    }

    public function getIsbn(): string
    {
        return $this->isbn;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function getPublicationDate(): ?string
    {
        return $this->publicationDate;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function getUserRating(): ?float
    {
        return $this->userRating;
    }

    public function setRating(?float $rating): void
    {
        if ($rating !== null && ($rating < 0.5 || $rating > 5)) {
            throw new \InvalidArgumentException('Rating must be between 0.5 and 5, or null.');
        }
        if ($rating !== null && floor($rating * 2) != $rating * 2) {
            throw new \InvalidArgumentException('Rating must be a multiple of 0.5.');
        }
        $this->rating = $rating;
    }

    public function setUserRating(?float $userRating): void
    {
        if ($userRating !== null && ($userRating < 0.5 || $userRating > 5)) {
            throw new \InvalidArgumentException('User rating must be between 0.5 and 5, or null.');
        }
        if ($userRating !== null && floor($userRating * 2) != $userRating * 2) {
            throw new \InvalidArgumentException('User rating must be a multiple of 0.5.');
        }
        $this->userRating = $userRating;
    }

    public function getPages(): ?int
    {
        return $this->pages;
    }

    public function setPages(?int $pages): void
    {
        if ($pages !== null && $pages <= 0) {
            throw new \InvalidArgumentException('Pages must be a positive integer, or null.');
        }
        $this->pages = $pages;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCurrentPage(): ?int
    {
        return $this->currentPage;
    }

    public function setCurrentPage(?int $currentPage): void
    {
        if ($currentPage !== null && $currentPage < 0) {
            throw new \InvalidArgumentException('Current page must be a non-negative integer, or null.');
        }
        if ($currentPage !== null && $this->pages !== null && $currentPage > $this->pages) {
            throw new \InvalidArgumentException('Current page cannot be greater than total pages.');
        }
        $this->currentPage = $currentPage ?? 0;
    }

    public function getUserStatuses(): array
    {
        return $this->userStatuses;
    }

    public function setUserStatuses(array $userStatuses): void
    {
        if (empty($userStatuses)) {
            throw new \InvalidArgumentException('A book must have at least one user status.');
        }
        $this->userStatuses = array_unique($userStatuses);
    }

    public function getAddedTimestamp(): ?int
    {
        return $this->addedTimestamp;
    }
    
    public function setAddedTimestamp(int $timestamp): void
    {
        $this->addedTimestamp = $timestamp;
    }

    /**
     * Converts the Book object to an array.
     * Useful for serialization, e.g., when saving to JSON.
     */
    public function toArray(): array
    {
        return [
            'isbn' => $this->isbn,
            'title' => $this->title,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'publicationDate' => $this->publicationDate,
            'coverUrl' => $this->coverUrl,
            'rating' => $this->rating,
            'user_rating' => $this->userRating,
            'pages' => $this->pages,
            'description' => $this->description,
            'currentPage' => $this->currentPage,
            'userStatuses' => $this->userStatuses,
            'addedTimestamp' => $this->addedTimestamp,
            'tags' => $this->tags,
            'allowedTags' => $this->allowedTags,
            'genres' => $this->genres,
        ];
    }

    /**
     * Creates a Book instance from an array of data.
     * Useful for deserialization, e.g., when loading from JSON.
     *
     * @param array $data
     * @param array $allowedStatuses
     * @return self
     */
    public static function fromArray(array $data): self
    {
        // Permitir userStatuses vacío (mostrar en la vista, no lanzar excepción)
        if (!isset($data['userStatuses']) || !is_array($data['userStatuses'])) {
            $data['userStatuses'] = [];
        }
        
        // Asegurar que allowedStatuses esté disponible para validación
        $allowedStatuses = $data['allowedStatuses'] ?? [];
        
        foreach ($data['userStatuses'] as $status) {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException("Invalid status in data: {$status}. Allowed statuses are: " . implode(', ', $allowedStatuses));
            }
        }

        return new self(
            $data['isbn'] ?? '',
            $data['title'] ?? '',
            $data['author'] ?? null,
            $data['publisher'] ?? null,
            $data['publicationDate'] ?? null,
            $data['coverUrl'] ?? null,
            isset($data['rating']) ? (float)$data['rating'] : null,
            isset($data['user_rating']) ? (float)$data['user_rating'] : null,
            isset($data['pages']) ? (int)$data['pages'] : null,
            is_array($data['description'] ?? null) ? implode(' ', $data['description']) : ($data['description'] ?? null),
            $data['userStatuses'],
            $data['allowedStatuses'] ?? null,
            isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null,
            isset($data['currentPage']) ? (int)$data['currentPage'] : null,
            $data['tags'] ?? null,
            $data['allowedTags'] ?? null,
            $data['genres'] ?? null
        );
    }
}