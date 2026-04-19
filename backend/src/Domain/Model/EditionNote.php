<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;

/**
 * EditionNote - Represents a note made by a user on a specific page of a book edition
 * 
 * Allows users to take multiple notes per page with different types:
 * - note: General note
 * - quote: Direct quote from the book
 * - thought: Personal thought or reflection
 * - question: Question raised while reading
 * - summary: Summary of content
 * - progress: Progress update
 * - general: General note not tied to specific content
 */
class EditionNote
{
    private ?int $id;
    private int $userId;
    private int $userEditionId;
    private int $pageNumber;
    private ?string $noteText;
    private string $noteType;
    private bool $isPrivate;
    private Timestamp $createdAt;
    private Timestamp $updatedAt;

    private const VALID_NOTE_TYPES = [
        'note',
        'quote',
        'thought',
        'question',
        'summary',
        'progress',
        'general'
    ];

    public function __construct(
        int $userId,
        int $userEditionId,
        int $pageNumber,
        ?string $noteText = null,
        string $noteType = 'progress',
        bool $isPrivate = true,
        ?int $id = null
    ) {
        if ($pageNumber <= 0) {
            throw new InvalidArgumentException('Page number must be positive');
        }

        if (!in_array($noteType, self::VALID_NOTE_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid note type. Must be one of: %s', implode(', ', self::VALID_NOTE_TYPES))
            );
        }

        if ($noteText !== null && trim($noteText) === '') {
            throw new InvalidArgumentException('Note text cannot be empty');
        }

        $this->userId = $userId;
        $this->userEditionId = $userEditionId;
        $this->pageNumber = $pageNumber;
        $this->noteText = $noteText;
        $this->noteType = $noteType;
        $this->isPrivate = $isPrivate;
        $this->id = $id;
        $this->createdAt = Timestamp::now();
        $this->updatedAt = Timestamp::now();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserEditionId(): int
    {
        return $this->userEditionId;
    }

    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    public function setPageNumber(int $pageNumber): void
    {
        if ($pageNumber <= 0) {
            throw new InvalidArgumentException('Page number must be positive');
        }
        $this->pageNumber = $pageNumber;
    }

    public function getNoteText(): ?string
    {
        return $this->noteText;
    }

    public function setNoteText(?string $noteText): void
    {
        if ($noteText !== null && trim($noteText) === '') {
            throw new InvalidArgumentException('Note text cannot be empty');
        }
        $this->noteText = $noteText;
        $this->updateTimestamp();
    }

    public function getNoteType(): string
    {
        return $this->noteType;
    }

    public function setNoteType(string $noteType): void
    {
        if (!in_array($noteType, self::VALID_NOTE_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid note type. Must be one of: %s', implode(', ', self::VALID_NOTE_TYPES))
            );
        }
        $this->noteType = $noteType;
        $this->updateTimestamp();
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): void
    {
        $this->isPrivate = $isPrivate;
        $this->updateTimestamp();
    }

    public function getCreatedAt(): Timestamp
    {
        return $this->createdAt;
    }

    public function setCreatedAt(Timestamp $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): Timestamp
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(Timestamp $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'userId' => $this->userId,
            'user_edition_id' => $this->userEditionId,
            'userEditionId' => $this->userEditionId,
            'page_number' => $this->pageNumber,
            'pageNumber' => $this->pageNumber,
            'note_text' => $this->noteText,
            'noteText' => $this->noteText,
            'note_type' => $this->noteType,
            'noteType' => $this->noteType,
            'is_private' => $this->isPrivate,
            'isPrivate' => $this->isPrivate,
            'created_at' => $this->createdAt->toIso8601(),
            'createdAt' => $this->createdAt->toIso8601(),
            'updated_at' => $this->updatedAt->toIso8601(),
            'updatedAt' => $this->updatedAt->toIso8601(),
        ];
    }

    /**
     * Get valid note types
     */
    public static function getValidNoteTypes(): array
    {
        return self::VALID_NOTE_TYPES;
    }
}
