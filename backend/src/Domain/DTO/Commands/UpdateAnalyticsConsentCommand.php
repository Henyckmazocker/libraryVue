<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use InvalidArgumentException;

/**
 * La decisión del usuario sobre la analítica (Augur): sí o no.
 *
 * A diferencia de `UpdatePrivacySettingsCommand::fromArray`, aquí NO hay valor
 * por defecto: una clave ausente o mal tipada no puede convertirse en una
 * decisión que el usuario no tomó. Solo se aceptan los booleanos JSON
 * `true`/`false`; `1`/`0`, `"true"` o `"x"` se rechazan con 400
 * (`InvalidArgumentException`, que `ActionRouter::dispatch` traduce).
 */
final readonly class UpdateAnalyticsConsentCommand
{
    public function __construct(
        public int  $userId,
        public bool $consent
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        if (!array_key_exists('consent', $data) || !is_bool($data['consent'])) {
            throw new InvalidArgumentException("Field 'consent' must be a boolean (true or false).");
        }

        return new self(
            userId:  $userId,
            consent: $data['consent']
        );
    }
}
