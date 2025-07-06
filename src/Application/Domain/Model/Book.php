<?php

declare(strict_types=1);

namespace App\Application\Domain\Model;

class Book
{
    private string $isbn;
    private string $title;
    private ?string $author;
    private ?string $coverUrl;
    private ?float $rating; // Nullable float for rating
    private ?int $addedTimestamp;
    private array $userStatuses;

    public function __construct(
        string $isbn,
        string $title,
        ?string $author,
        ?string $coverUrl,
        ?float $rating,
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
        if (empty($userStatuses)) {
            throw new \InvalidArgumentException('A book must have at least one user status.');
        }
        foreach ($userStatuses as $status) {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException("Invalid status: {$status}. Allowed statuses are: " . implode(', ', $allowedStatuses));
            }
        }

        $this->isbn = $isbn;
        $this->title = $title;
        $this->author = $author;
        $this->coverUrl = $coverUrl;
        $this->rating = $rating;
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

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function getRating(): ?float
    {
        return $this->rating;
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

    public function getUserStatuses(): array
    {
        return $this->userStatuses;
    }

    public function setUserStatuses(array $userStatuses, array $allowedStatuses): void
    {
        if (empty($userStatuses)) {
            throw new \InvalidArgumentException('A book must have at least one user status.');
        }
        foreach ($userStatuses as $status) {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException("Invalid status: {$status}. Allowed statuses are: " . implode(', ', $allowedStatuses));
            }
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
            'coverUrl' => $this->coverUrl,
            'rating' => $this->rating,
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
        if (empty($data['userStatuses']) || !is_array($data['userStatuses'])) {
            throw new \InvalidArgumentException('User statuses are required and must be an array.');
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
            $data['coverUrl'] ?? null,
            isset($data['rating']) ? (float)$data['rating'] : null,
            $data['userStatuses'],
            $allowedStatuses,
            isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null
        );
    }
} 