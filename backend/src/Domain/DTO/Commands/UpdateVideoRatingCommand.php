<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\Rating;

/**
 * Command DTO for updating a user's personal rating for a video
 *
 * `$rating` es nulable: `null` (o un `rating` de 0) borra la valoración.
 */
final readonly class UpdateVideoRatingCommand
{
    public function __construct(
        public int $userId,
        public string $youtubeId,
        public ?Rating $rating
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId:    $userId,
            // El `?? ''` silencioso dejaba pasar un payload sin identificador y
            // el fallo aparecía abajo, como «vídeo no encontrado».
            youtubeId: $data['youtubeId']
                ?? $data['youtube_id']
                ?? $data['id']
                ?? throw new \InvalidArgumentException('YouTube ID is required.'),
            rating:    isset($data['rating']) && (float)$data['rating'] > 0
                ? Rating::fromNullableFloat((float)$data['rating'])
                : null
        );
    }
}
