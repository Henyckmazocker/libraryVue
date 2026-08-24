<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Model\ValueObjects\AlbumId;
use App\Domain\Repository\Catalog\AlbumCatalogInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Local first, Spotify only when local has nothing
 *
 * The same decorator as FallbackMovieCatalog, with one rule deliberately
 * stricter. For films, `search` never touches the network. For albums it can,
 * and the reason is worth writing down: the mirror keeps only `Album` and `EP`
 * release groups, so a single, a bootleg or a brand-new release that has not
 * reached a dump yet is genuinely absent — not "not popular enough".
 *
 *   - search   → falls back only when local returns **zero** results.
 *   - findById → an MBID is never asked of Spotify (it would 404); a base62 id
 *                is never asked of the mirror. The id decides, not a try/catch.
 *
 * Every fallback is logged, so how much Spotify traffic actually survives the
 * mirror can be measured rather than guessed.
 */
class FallbackAlbumCatalog implements AlbumCatalogInterface
{
    private AlbumCatalogInterface $local;
    private AlbumCatalogInterface $remote;
    private LoggerInterface $logger;

    public function __construct(
        AlbumCatalogInterface $local,
        AlbumCatalogInterface $remote,
        LoggerInterface $logger
    ) {
        $this->local  = $local;
        $this->remote = $remote;
        $this->logger = $logger;
    }

    public function search(string $query, int $limit = 20): array
    {
        $local = $this->local->search($query, $limit);
        if ($local !== []) {
            return $local;
        }

        return $this->fromRemote(
            fn (): array => $this->remote->search($query, $limit),
            'search',
            $query
        ) ?? [];
    }

    public function findById(string $id): ?array
    {
        // Routing on the shape of the id, not on a failed lookup: asking
        // Spotify for an MBID is a guaranteed 404 and a wasted round trip, and
        // asking the mirror for a base62 id is a guaranteed empty row.
        if (self::isMusicBrainzId($id)) {
            return $this->local->findById($id);
        }

        return $this->fromRemote(
            fn (): ?array => $this->remote->findById($id),
            'findById',
            $id
        );
    }

    /**
     * Solo el mirror, nunca la red
     *
     * Preguntarle a Spotify por un código de barras devolvería contenido de
     * Spotify, y el único motivo por el que este método existe es evitar
     * persistirlo.
     */
    public function findByBarcode(string $barcode): ?array
    {
        return $this->local->findByBarcode($barcode);
    }

    /** A MusicBrainz id is a UUID; a Spotify one never is. AlbumId owns the shape. */
    public static function isMusicBrainzId(string $id): bool
    {
        return AlbumId::looksLikeMbid($id);
    }

    /**
     * Run a remote call, log it, and never let it take the request down
     *
     * @template T
     * @param callable():T $call
     * @return T|null
     */
    private function fromRemote(callable $call, string $method, string $subject)
    {
        $this->logger->info('album catalog fallback', ['method' => $method, 'subject' => $subject]);

        try {
            return $call();
        } catch (Throwable $e) {
            // Without a network, or without Spotify credentials, the point is
            // to degrade rather than 500. The mirror covers the normal case.
            $this->logger->warning('album catalog fallback failed', [
                'method'  => $method,
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }
}
