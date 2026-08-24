<?php

declare(strict_types=1);

namespace App\Domain\Model\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object: AlbumId
 *
 * La identidad de un álbum en la biblioteca. Acepta dos formas:
 *
 *   - **MBID** de MusicBrainz — `b1392450-e666-3926-a536-22c65f834433`, un UUID
 *     de 36 caracteres. Es la forma normal desde que el catálogo se sirve del
 *     mirror local.
 *   - **base62 de Spotify** — 15-25 alfanuméricos. Sigue admitiéndose porque un
 *     álbum puede llegar por el fallback de Spotify cuando el mirror no lo
 *     tiene, y porque los guardados anteriores al mirror se identifican así.
 *
 * Existe en vez de haber ampliado [[SpotifyId]] a propósito: el problema que el
 * mirror de música vino a resolver es que la identidad de un álbum estuviera
 * atada a un proveedor privado. Guardar MBIDs en una clase llamada `SpotifyId`
 * habría dejado el acoplamiento intacto y encima mintiendo. `SpotifyId` sigue
 * existiendo para la columna `spotify_id`, que es el puente de reconciliación.
 */
final class AlbumId
{
    /** MusicBrainz Identifier: UUID canónico en minúsculas o mayúsculas */
    private const MBID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    /** El mismo que valida SpotifyId; se repite y no se importa para que las dos clases sean independientes */
    private const BASE62_PATTERN = '/^[a-zA-Z0-9]{15,25}$/';

    private string $value;

    private function __construct(string $id)
    {
        $this->validate($id);
        $this->value = $id;
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public static function fromNullableString(?string $id): ?self
    {
        return $id !== null && $id !== '' ? new self($id) : null;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Si esta identidad es un MBID
     *
     * Lo consulta la persistencia para saber en qué columna escribirla:
     * `mb_release_group_gid` o `spotify_id`. No es cosmético — las dos columnas
     * tienen su propio UNIQUE y confundirlas rompe la deduplicación.
     */
    public function isMusicBrainz(): bool
    {
        return self::looksLikeMbid($this->value);
    }

    /**
     * Si una cadena cualquiera tiene forma de MBID
     *
     * Público y estático porque el mismo discriminador hace falta sobre ids que
     * no son de álbum: `AlbumDataMapper` lo usa con el id de artista para saber
     * si va a `mb_artist_gid` (CHAR(36)) o a `artist_id` (VARCHAR(22), medido
     * para el base62 de Spotify), y meter un MBID en la segunda es un error
     * 1406 en tiempo de guardado.
     */
    public static function looksLikeMbid(string $id): bool
    {
        return preg_match(self::MBID_PATTERN, $id) === 1;
    }

    private function validate(string $id): void
    {
        if ($id === '') {
            throw new InvalidArgumentException('Album ID cannot be empty.');
        }

        if (preg_match(self::MBID_PATTERN, $id) !== 1
            && preg_match(self::BASE62_PATTERN, $id) !== 1) {
            throw new InvalidArgumentException(
                "Invalid album ID format: \"{$id}\". Must be a MusicBrainz MBID (36-char UUID) "
                . 'or a Spotify base62 id (15-25 alphanumerics).'
            );
        }
    }
}
