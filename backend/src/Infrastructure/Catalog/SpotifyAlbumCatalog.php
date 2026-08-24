<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Repository\Catalog\AlbumCatalogInterface;
use App\Domain\Services\SpotifyService;

/**
 * The Spotify API behind the catalog interface
 *
 * A thin adapter and nothing else: SpotifyService already returns Spotify's own
 * shape, which is the shape this interface speaks, so there is no mapping to
 * do. It exists so FallbackAlbumCatalog can hold two things of the same type.
 *
 * Note what this class does *not* do: it is never the path by which an album
 * gets stored. FallbackAlbumCatalog only reaches it when the mirror has nothing,
 * and what comes back is shown to the user, not persisted as catalog. Keeping
 * Spotify's content out of the database is the whole point of the plan this
 * belongs to.
 */
class SpotifyAlbumCatalog implements AlbumCatalogInterface
{
    private SpotifyService $spotify;

    public function __construct(SpotifyService $spotify)
    {
        $this->spotify = $spotify;
    }

    public function search(string $query, int $limit = 20): array
    {
        return $this->spotify->searchAlbums($query, $limit);
    }

    public function findById(string $id): ?array
    {
        return $this->spotify->getAlbum($id);
    }

    /**
     * Siempre null, y a propósito
     *
     * Este método existe para sacar un álbum del catálogo ABIERTO cuando solo
     * se tiene su código de barras. Resolverlo contra Spotify devolvería un
     * álbum de Spotify, que es justo lo que no se quiere persistir.
     */
    public function findByBarcode(string $barcode): ?array
    {
        return null;
    }
}
