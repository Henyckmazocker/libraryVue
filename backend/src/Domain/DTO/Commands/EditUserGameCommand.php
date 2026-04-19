<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for editing user's game data
 */
final readonly class EditUserGameCommand
{
    public function __construct(
        public int $userId,
        public int $gameId,
        public ?Rating $userRating = null,
        public ?string $personalNotes = null,
        public ?float $hoursPlayed = null,
        public ?string $platformPlayed = null,
        public ?string $completedAt = null,
        public ?string $dateStarted = null,
        public ?string $dateFinished = null,
        public ?array $statuses = null,
        public array $tags = []
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!isset($data['gameId'])) {
            throw new \InvalidArgumentException('Game ID is required');
        }

        // Si los datos vienen dentro de un sub-array 'data', extraerlos
        $gameData = $data['data'] ?? $data;

        return new self(
            userId: $userId,
            gameId: is_int($data['gameId']) ? $data['gameId'] : (int)$data['gameId'],
            userRating: isset($gameData['personalRating']) || isset($gameData['user_rating'])
                ? Rating::fromNullableFloat((float)($gameData['personalRating'] ?? $gameData['user_rating']))
                : null,
            personalNotes: $gameData['personal_notes'] ?? $gameData['personalNotes'] ?? $gameData['notes'] ?? null,
            hoursPlayed: isset($gameData['hoursPlayed']) || isset($gameData['hours_played'])
                ? (float)($gameData['hoursPlayed'] ?? $gameData['hours_played'])
                : null,
            platformPlayed: $gameData['platform_played'] ?? $gameData['platformPlayed'] ?? null,
            completedAt: $gameData['completed_at'] ?? $gameData['completedAt'] ?? null,
            dateStarted: !empty($gameData['dateStarted']) ? $gameData['dateStarted'] : (!empty($gameData['date_started']) ? $gameData['date_started'] : null),
            dateFinished: !empty($gameData['dateFinished']) ? $gameData['dateFinished'] : (!empty($gameData['date_finished']) ? $gameData['date_finished'] : null),
            statuses: $gameData['statuses'] ?? $data['statuses'] ?? null,
            tags: $data['tags'] ?? []
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->userRating !== null) {
            $data['personal_rating'] = $this->userRating->toFloat();
        }
        if ($this->personalNotes !== null) {
            $data['personal_notes'] = $this->personalNotes;
        }
        if ($this->hoursPlayed !== null) {
            $data['hours_played'] = $this->hoursPlayed;
        }
        if ($this->platformPlayed !== null) {
            $data['platform_played'] = $this->platformPlayed;
        }
        if ($this->completedAt !== null) {
            $data['completed_at'] = $this->completedAt;
        }
        if ($this->dateStarted !== null) {
            $data['date_started'] = $this->dateStarted;
        }
        if ($this->dateFinished !== null) {
            $data['date_finished'] = $this->dateFinished;
        }

        return $data;
    }
}
