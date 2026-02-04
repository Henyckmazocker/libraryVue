<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * Work - Represents a literary work (conceptual entity)
 * 
 * A work is the abstract concept of a book (e.g., "The Hitchhiker's Guide to the Galaxy")
 * independent of its physical manifestations (editions).
 * 
 * Authors and subjects are properties of the work, shared across all editions.
 */
class Work
{
    private ?int $workId;
    private ?string $openlibraryWorkKey;
    private ?string $syntheticWorkKey;
    private string $title;
    private ?string $subtitle;
    private array $authors; // JSON array
    private ?string $description;
    private ?array $subjects; // JSON array (genres/topics)
    private ?int $firstPublishYear;
    private ?string $originalLanguage;
    private bool $isSynthetic;
    private bool $needsReview;
    private bool $manuallyEdited;
    private ?array $manuallyEditedFields;

    public function __construct(
        string $title,
        array $authors,
        ?int $workId = null,
        ?string $openlibraryWorkKey = null,
        ?string $syntheticWorkKey = null
    ) {
        if (empty($title)) {
            throw new \InvalidArgumentException('Title cannot be empty.');
        }
        if (empty($authors)) {
            throw new \InvalidArgumentException('Authors cannot be empty.');
        }

        $this->workId = $workId;
        $this->openlibraryWorkKey = $openlibraryWorkKey;
        $this->syntheticWorkKey = $syntheticWorkKey;
        $this->title = $title;
        $this->subtitle = null;
        $this->authors = $authors;
        $this->description = null;
        $this->subjects = null;
        $this->firstPublishYear = null;
        $this->originalLanguage = null;
        $this->isSynthetic = false;
        $this->needsReview = false;
        $this->manuallyEdited = false;
        $this->manuallyEditedFields = null;
    }

    // Getters
    public function getWorkId(): ?int 
    { 
        return $this->workId; 
    }

    public function setWorkId(int $workId): void 
    { 
        $this->workId = $workId; 
    }

    public function getOpenlibraryWorkKey(): ?string 
    { 
        return $this->openlibraryWorkKey; 
    }

    public function getSyntheticWorkKey(): ?string
    {
        return $this->syntheticWorkKey;
    }

    public function getTitle(): string 
    { 
        return $this->title; 
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    public function getAuthors(): array 
    { 
        return $this->authors; 
    }

    public function getDescription(): ?string 
    { 
        return $this->description; 
    }

    public function setDescription(?string $description): void 
    { 
        $this->description = $description; 
    }

    public function getSubjects(): ?array 
    { 
        return $this->subjects; 
    }

    public function setSubjects(?array $subjects): void 
    { 
        $this->subjects = $subjects; 
    }

    public function getFirstPublishYear(): ?int 
    { 
        return $this->firstPublishYear; 
    }

    public function setFirstPublishYear(?int $year): void 
    { 
        $this->firstPublishYear = $year; 
    }

    public function getOriginalLanguage(): ?string
    {
        return $this->originalLanguage;
    }

    public function setOriginalLanguage(?string $language): void
    {
        $this->originalLanguage = $language;
    }

    public function isSynthetic(): bool 
    { 
        return $this->isSynthetic; 
    }

    public function needsReview(): bool
    {
        return $this->needsReview;
    }

    public function setNeedsReview(bool $needsReview): void
    {
        $this->needsReview = $needsReview;
    }

    public function isManuallyEdited(): bool
    {
        return $this->manuallyEdited;
    }

    public function markAsManuallyEdited(array $fields): void
    {
        $this->manuallyEdited = true;
        $this->manuallyEditedFields = $fields;
    }
    
    public function markAsSynthetic(string $syntheticKey): void
    {
        $this->isSynthetic = true;
        $this->syntheticWorkKey = $syntheticKey;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'work_id' => $this->workId,
            'openlibrary_work_key' => $this->openlibraryWorkKey,
            'synthetic_work_key' => $this->syntheticWorkKey,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'authors' => $this->authors,
            'description' => $this->description,
            'subjects' => $this->subjects,
            'first_publish_year' => $this->firstPublishYear,
            'original_language' => $this->originalLanguage,
            'is_synthetic' => $this->isSynthetic,
            'needs_review' => $this->needsReview,
            'manually_edited' => $this->manuallyEdited,
            'manually_edited_fields' => $this->manuallyEditedFields,
        ];
    }

    /**
     * Create Work from array data
     */
    public static function fromArray(array $data): self
    {
        $work = new self(
            $data['title'],
            $data['authors'],
            $data['work_id'] ?? null,
            $data['openlibrary_work_key'] ?? null,
            $data['synthetic_work_key'] ?? null
        );

        if (isset($data['subtitle'])) {
            $work->setSubtitle($data['subtitle']);
        }
        if (isset($data['description'])) {
            $work->setDescription($data['description']);
        }
        if (isset($data['subjects'])) {
            $work->setSubjects($data['subjects']);
        }
        if (isset($data['first_publish_year'])) {
            $work->setFirstPublishYear($data['first_publish_year']);
        }
        if (isset($data['original_language'])) {
            $work->setOriginalLanguage($data['original_language']);
        }
        if (isset($data['needs_review'])) {
            $work->setNeedsReview($data['needs_review']);
        }
        if (isset($data['manually_edited']) && $data['manually_edited'] && isset($data['manually_edited_fields'])) {
            $work->markAsManuallyEdited($data['manually_edited_fields']);
        }

        return $work;
    }
}
