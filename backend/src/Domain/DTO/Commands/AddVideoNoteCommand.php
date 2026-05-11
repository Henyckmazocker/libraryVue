<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

/**
 * Command DTO for adding a note to a video
 */
final readonly class AddVideoNoteCommand
{
    public function __construct(
        public int $userId,
        public string $youtubeId,
        public string $noteText,
        public string $noteType = 'note',
        public bool $isPrivate = true
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId:    $userId,
            youtubeId: $data['youtubeId'] ?? $data['youtube_id'] ?? $data['videoId'] ?? '',
            noteText:  $data['noteText'] ?? $data['note_text'] ?? '',
            noteType:  $data['noteType'] ?? $data['note_type'] ?? 'note',
            isPrivate: (bool)($data['isPrivate'] ?? $data['is_private'] ?? true)
        );
    }
}
