<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Repository\Catalog\MovieCatalogInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Local first, network only when local falls short
 *
 * The point of the whole plan lives here, in one testable place, instead of
 * being spread across the controller and the services. The rules are explicit
 * on purpose:
 *
 *   - search       → the network is never consulted. The mirror has 1.28 M
 *                    titles including Spanish release names; if it finds
 *                    nothing, there is nothing.
 *   - findByImdbId → falls back when the local record is missing a Plot or a
 *                    Poster, which IMDb's datasets never provide, and **merges**
 *                    rather than replaces: the local runtime, rating and genres
 *                    are better than TMDB's.
 *   - seasonEpisodes → falls back only when local returns nothing.
 *
 * Every fallback is logged, so it can be measured how much traffic is actually
 * left after the mirror.
 */
class FallbackMovieCatalog implements MovieCatalogInterface
{
    private MovieCatalogInterface $local;
    private MovieCatalogInterface $remote;
    private LoggerInterface $logger;

    public function __construct(
        MovieCatalogInterface $local,
        MovieCatalogInterface $remote,
        LoggerInterface $logger
    ) {
        $this->local  = $local;
        $this->remote = $remote;
        $this->logger = $logger;
    }

    public function search(string $query, string $type = '', int $limit = 20): array
    {
        return $this->local->search($query, $type, $limit);
    }

    public function findByImdbId(string $imdbId): ?array
    {
        $local = $this->local->findByImdbId($imdbId);

        if ($local !== null && !$this->needsEnrichment($local)) {
            return $local;
        }

        $remote = $this->fromRemote(
            fn (): ?array => $this->remote->findByImdbId($imdbId),
            'findByImdbId',
            $imdbId
        );

        if ($remote === null) {
            // Offline, or TMDB does not know it. A record without a plot beats
            // no record at all, which is what OMDb used to return.
            return $local;
        }
        if ($local === null) {
            return $remote;
        }

        return $this->merge($local, $remote);
    }

    public function seasonEpisodes(string $imdbId, int $season): array
    {
        $local = $this->local->seasonEpisodes($imdbId, $season);
        if ($local !== []) {
            return $local;
        }

        return $this->fromRemote(
            fn (): array => $this->remote->seasonEpisodes($imdbId, $season),
            'seasonEpisodes',
            $imdbId
        ) ?? [];
    }

    /**
     * The two fields IMDb's open datasets never carry
     */
    private function needsEnrichment(array $record): bool
    {
        return ($record['Plot'] ?? null) === null || ($record['Poster'] ?? null) === null;
    }

    /**
     * Local values win; the remote only fills the holes
     *
     * Not the other way round: the mirror's runtime, genres and imdbRating come
     * from IMDb itself, and TMDB's are a second-hand copy.
     */
    private function merge(array $local, array $remote): array
    {
        foreach ($remote as $key => $value) {
            if (($local[$key] ?? null) === null && $value !== null) {
                $local[$key] = $value;
            }
        }

        return $local;
    }

    /**
     * Run a remote call, log it, and never let it take the request down
     *
     * @template T
     * @param callable():T $call
     * @return T|null
     */
    private function fromRemote(callable $call, string $method, string $imdbId)
    {
        $this->logger->info('catalog fallback', ['method' => $method, 'imdbId' => $imdbId]);

        try {
            return $call();
        } catch (Throwable $e) {
            // Without a network the whole point is to degrade, not to 500.
            $this->logger->warning('catalog fallback failed', [
                'method' => $method,
                'imdbId' => $imdbId,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }
}
