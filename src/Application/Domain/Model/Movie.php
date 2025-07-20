<?php

declare(strict_types=1);

namespace App\Application\Domain\Model;

use InvalidArgumentException;

class Movie
{
    private string $id;
    private string $title;
    private ?string $originalTitle;
    private ?string $director;
    private ?string $coverUrl;
    private ?float $rating;
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
    public function getUserStatuses(): array { return $this->userStatuses; }
    public function getAddedTimestamp(): int { return $this->addedTimestamp; }
    public function getAllowedStatuses(): array { return $this->allowedStatuses; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'originalTitle' => $this->originalTitle,
            'director' => $this->director,
            'coverUrl' => $this->coverUrl,
            'rating' => $this->rating,
            'userStatuses' => $this->userStatuses,
            'addedTimestamp' => $this->addedTimestamp,
            'allowedStatuses' => $this->allowedStatuses
        ];
    }
}
