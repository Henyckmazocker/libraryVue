<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;

class Book
{
    private ISBN $isbn;
    private string $title;
    private ?string $author;
    private ?string $publisher;
    private ?int $publicationYear; // Changed from publicationDate to year
    private ?string $coverUrl;
    private ?Rating $rating; // General rating as VO
    private ?Rating $userRating; // User's personal rating as VO
    private ?int $pages;
    private ?string $description;
    private ?string $language;
    private Timestamp $addedTimestamp; // Always has value, as VO
    private ?int $currentPage; // Página actual del usuario
    private array $userStatuses;
    private ?array $tags;
    private ?array $allowedTags;
    private ?Genre $genre; // Single genre as VO (changed from genres array)
    private ?int $activeReadingSessionId;
    private ?int $totalSessionsCompleted;
    private ?int $currentSessionNumber;
    private ?string $sessionStartedAt;
    private ?string $lastSessionCompletedAt;
    private ?string $personalNotes;
    private ?string $consumedAt;

    public function __construct(
        ISBN $isbn,
        string $title,
        ?string $author,
        ?string $publisher,
        ?int $publicationYear,
        ?string $coverUrl,
        ?Rating $rating,
        ?Rating $userRating,
        ?int $pages,
        ?string $description,
        array $userStatuses,
        array $allowedStatuses,
        ?Timestamp $addedTimestamp = null,
        ?int $currentPage = null,
        ?array $tags = null,
        ?array $allowedTags = null,
        ?Genre $genre = null,
        ?string $language = null,
        ?int $activeReadingSessionId = null,
        ?int $totalSessionsCompleted = null,
        ?int $currentSessionNumber = null,
        ?string $sessionStartedAt = null,
        ?string $lastSessionCompletedAt = null,
        ?string $personalNotes = null,
        ?string $consumedAt = null
    ) {
        // Validation handled by Value Objects
        if (empty($title)) {
            throw new \InvalidArgumentException('Title cannot be empty.');
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
        // Allow empty userStatuses
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
        $this->publicationYear = $publicationYear;
        $this->coverUrl = $coverUrl;
        $this->rating = $rating;
        $this->userRating = $userRating;
        $this->pages = $pages;
        $this->description = $description;
        $this->language = $language;
        $this->currentPage = $currentPage ?? 0;
        $this->userStatuses = array_unique($userStatuses);
        $this->addedTimestamp = $addedTimestamp ?? Timestamp::now();
        $this->tags = $tags;
        $this->allowedTags = $allowedTags;
        $this->genre = $genre;
        $this->activeReadingSessionId = $activeReadingSessionId;
        $this->totalSessionsCompleted = $totalSessionsCompleted ?? 0;
        $this->currentSessionNumber = $currentSessionNumber;
        $this->sessionStartedAt = $sessionStartedAt;
        $this->lastSessionCompletedAt = $lastSessionCompletedAt;
        $this->personalNotes = $personalNotes;
        $this->consumedAt = $consumedAt;
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

    public function getGenre(): ?Genre
    {
        return $this->genre;
    }

    public function getGenres(): ?array
    {
        return $this->genre !== null ? [$this->genre->toString()] : null;
    }

    public function setGenre(?Genre $genre): void
    {
        $this->genre = $genre;
    }

    public function getIsbn(): ISBN
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

    public function getPublicationYear(): ?int
    {
        return $this->publicationYear;
    }

    public function getPublicationDate(): ?string
    {
        // Backward compatibility: return year as date string
        return $this->publicationYear !== null ? (string) $this->publicationYear : null;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getRating(): ?Rating
    {
        return $this->rating;
    }

    public function getUserRating(): ?Rating
    {
        return $this->userRating;
    }

    public function setRating(?Rating $rating): void
    {
        $this->rating = $rating;
    }

    public function setUserRating(?Rating $userRating): void
    {
        $this->userRating = $userRating;
    }

    public function getPersonalNotes(): ?string
    {
        return $this->personalNotes;
    }

    public function getConsumedAt(): ?string
    {
        return $this->consumedAt;
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

    public function getAddedTimestamp(): Timestamp
    {
        return $this->addedTimestamp;
    }
    
    public function setAddedTimestamp(Timestamp $timestamp): void
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
            'isbn' => $this->isbn->toString(),
            'title' => $this->title,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'publication_year' => $this->publicationYear,
            'publicationDate' => $this->publicationYear !== null ? (string) $this->publicationYear : null, // Backward compatibility
            'coverUrl' => $this->coverUrl,
            'cover_url' => $this->coverUrl, // Alias for consistency
            'language' => $this->language,
            'rating' => $this->rating?->toFloat(),
            'user_rating' => $this->userRating?->toFloat(),
            'pages' => $this->pages,
            'description' => $this->description,
            'currentPage' => $this->currentPage,
            'current_page' => $this->currentPage, // Alias
            'userStatuses' => $this->userStatuses,
            'addedTimestamp' => $this->addedTimestamp->toUnixTimestamp(),
            'tags' => $this->tags,
            'allowedTags' => $this->allowedTags,
            'genre' => $this->genre?->toString(),
            'genres' => $this->genre !== null ? [$this->genre->toString()] : null, // Backward compatibility as array
            'active_reading_session_id' => $this->activeReadingSessionId,
            'total_sessions_completed' => $this->totalSessionsCompleted,
            'current_session_number' => $this->currentSessionNumber,
            'session_started_at' => $this->sessionStartedAt,
            'last_session_completed_at' => $this->lastSessionCompletedAt,
            'personal_notes' => $this->personalNotes,
            'consumed_at' => $this->consumedAt,
        ];
    }

    /**
     * Creates a Book instance from an array of data.
     * Useful for deserialization, e.g., when loading from JSON.
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        // Allow empty userStatuses
        if (!isset($data['userStatuses']) || !is_array($data['userStatuses'])) {
            $data['userStatuses'] = [];
        }
        
        // Ensure allowedStatuses are available for validation
        $allowedStatuses = $data['allowedStatuses'] ?? [];
        
        foreach ($data['userStatuses'] as $status) {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException("Invalid status in data: {$status}. Allowed statuses are: " . implode(', ', $allowedStatuses));
            }
        }

        // Convert primitives to VOs
        $isbn = ISBN::fromString($data['isbn'] ?? '');
        
        $rating = isset($data['rating']) && $data['rating'] !== null
            ? Rating::fromNullableFloat((float) $data['rating'])
            : null;
        
        $userRating = isset($data['user_rating']) && $data['user_rating'] !== null
            ? Rating::fromNullableFloat((float) $data['user_rating'])
            : null;
        
        $addedTimestamp = isset($data['addedTimestamp']) && $data['addedTimestamp'] !== null
            ? Timestamp::fromUnixTimestamp((int) $data['addedTimestamp'])
            : null;
        
        // Handle genre (can be string or from array for backward compatibility)
        $genre = null;
        if (isset($data['genre']) && !empty($data['genre'])) {
            $genre = Genre::fromString((string) $data['genre']);
        } elseif (isset($data['genres']) && is_array($data['genres']) && !empty($data['genres'])) {
            $genre = Genre::fromString((string) $data['genres'][0]);
        }

        // Handle publication_year (can be from year or date field)
        $publicationYear = isset($data['publication_year']) ? (int) $data['publication_year'] : null;
        if ($publicationYear === null && isset($data['publicationDate']) && is_numeric($data['publicationDate'])) {
            $publicationYear = (int) $data['publicationDate'];
        }

        return new self(
            $isbn,
            $data['title'] ?? '',
            $data['author'] ?? null,
            $data['publisher'] ?? null,
            $publicationYear,
            $data['coverUrl'] ?? $data['cover_url'] ?? null,
            $rating,
            $userRating,
            isset($data['pages']) ? (int) $data['pages'] : null,
            is_array($data['description'] ?? null) ? implode(' ', $data['description']) : ($data['description'] ?? null),
            $data['userStatuses'],
            $allowedStatuses,
            $addedTimestamp,
            isset($data['currentPage']) ? (int) $data['currentPage'] : (isset($data['current_page']) ? (int) $data['current_page'] : null),
            $data['tags'] ?? null,
            $data['allowedTags'] ?? null,
            $genre,
            $data['language'] ?? null,
            isset($data['active_reading_session_id']) ? (int) $data['active_reading_session_id'] : null,
            isset($data['total_sessions_completed']) ? (int) $data['total_sessions_completed'] : null,
            isset($data['current_session_number']) ? (int) $data['current_session_number'] : null,
            $data['session_started_at'] ?? null,
            $data['last_session_completed_at'] ?? null,
            $data['personal_notes'] ?? null,
            $data['consumed_at'] ?? null
        );
    }
}