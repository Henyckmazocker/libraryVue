<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\Recommendation;
use InvalidArgumentException;

final readonly class ResolveRecommendationCommand
{
    public function __construct(
        public int    $userId,
        public int    $recommendationId,
        public string $resolution
    ) {
        if (!in_array($resolution, [Recommendation::STATUS_ADDED, Recommendation::STATUS_DISMISSED], true)) {
            throw new InvalidArgumentException("resolution must be 'added' or 'dismissed'");
        }
    }

    public static function fromArray(array $data, int $userId): self
    {
        $recommendationId = (int) ($data['recommendationId'] ?? $data['recommendation_id'] ?? 0);
        if ($recommendationId <= 0) {
            throw new InvalidArgumentException('recommendationId is required');
        }

        // NO se llama `action`, y no es una cuestión de estilo: el payload viaja
        // plano en la raíz junto a la clave `action` del protocolo
        // (`Application.php:117-122`), así que un parámetro con ese nombre pisa
        // al enrutado y la petición muere con «No valid action specified».
        // Los tests de integración no pueden verlo —entran por `dispatch()`,
        // con la acción ya separada del payload—; lo cazó un `curl`.
        return new self($userId, $recommendationId, trim((string) ($data['resolution'] ?? '')));
    }
}
