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
    private array $userStatuses;

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
        ?int $addedTimestamp = null
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
        // Permitir userStatuses vacío (mostrar en la vista, no lanzar excepción)
        if (!is_array($userStatuses)) {
            $userStatuses = [];
        }
        foreach ($userStatuses as $status) {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException("Invalid status: {$status}. Allowed statuses are: " . implode(', ', $allowedStatuses));
            }
        }

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
        $this->userStatuses = array_unique($userStatuses);
        $this->addedTimestamp = $addedTimestamp ?? time();
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
            'userStatuses' => $this->userStatuses,
            'addedTimestamp' => $this->addedTimestamp,
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
    public static function fromArray(array $data, array $allowedStatuses): self
    {
        // Permitir userStatuses vacío (mostrar en la vista, no lanzar excepción)
        if (!isset($data['userStatuses']) || !is_array($data['userStatuses'])) {
            $data['userStatuses'] = [];
        }
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
            $allowedStatuses,
            isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null
        );
    }
}