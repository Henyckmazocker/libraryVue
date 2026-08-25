<?php

declare(strict_types=1);

namespace App\Domain\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\Utils;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Http\HttpClientFactory;

/**
 * Service for interacting with The Movie Database (TMDB) API.
 *
 * The enrichment layer for films and series, and the reason OMDb is gone: the local
 * IMDb mirror gives the skeleton (title, year, runtime, genres, rating), and
 * this fills in what IMDb's open datasets do not publish — a Spanish plot, a
 * poster, and the director.
 *
 * Everything is looked up by IMDb id through /find, so the mirror's tconst
 * stays the identity across both sources.
 *
 * Note on caching: TMDB's terms allow caching for at most 6 months, which is
 * why the tmdb_title table has a cached_at column. This file-level cache is a
 * short-lived accelerator on top of that, not the durable copy.
 */
class TmdbService
{
    private Client $client;
    private ?string $apiKey;

    private const BASE_URL  = 'https://api.themoviedb.org/3/';
    private const CACHE_TTL = 86400;     // 24 hours
    private const NAMESPACE = 'tmdb';
    private const LANGUAGE  = 'es-ES';

    /** TMDB serves images from a CDN; the path stored is relative to this */
    public const IMAGE_BASE = 'https://image.tmdb.org/t/p/w500';

    public function __construct(
        private readonly CacheService $cache,
        private readonly LoggerInterface $logger,
        HttpClientFactory $http
    ) {
        $this->apiKey = $_ENV['TMDB_API_KEY'] ?? null;

        $this->client = $http->create(HttpClientFactory::PROFILE_WEB, 'LibraryVue/1.0 (Educational Project)', [
            'base_uri'        => self::BASE_URL,
            'timeout'         => 10.0,
            'connect_timeout' => 3.0,
            'headers'         => ['Accept' => 'application/json'],
        ]);

        if (empty($this->apiKey)) {
            $this->logger->warning('TmdbService: TMDB_API_KEY is not configured');
        }
    }

    /**
     * Resolve an IMDb id to its TMDB record.
     *
     * @return array|null ['tmdb_id' => int, 'media_type' => 'movie'|'tv', ...]
     *                    or null when TMDB does not know the title
     */
    public function findByImdbId(string $imdbId): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $cacheKey = 'find_' . $imdbId;
        $cached   = $this->cache->get($cacheKey, self::NAMESPACE);
        if ($cached !== null) {
            return $cached;
        }

        $data = $this->get('find/' . rawurlencode($imdbId), ['external_source' => 'imdb_id']);
        if ($data === null) {
            return null;
        }

        $hit = $data['movie_results'][0] ?? $data['tv_results'][0] ?? null;
        if ($hit === null) {
            $this->logger->info('TmdbService: no match for imdb id', ['imdbId' => $imdbId]);
            return null;
        }

        $result = [
            'tmdb_id'    => (int) $hit['id'],
            'media_type' => isset($data['movie_results'][0]) ? 'movie' : 'tv',
            'title'      => $hit['title'] ?? $hit['name'] ?? null,
            'overview'   => ($hit['overview'] ?? '') !== '' ? $hit['overview'] : null,
            'poster_path' => $hit['poster_path'] ?? null,
        ];

        $this->cache->set($cacheKey, $result, self::CACHE_TTL, self::NAMESPACE);

        return $result;
    }

    /**
     * Resolve many IMDb ids at once, concurrently.
     *
     * One /find call per id is unavoidable —TMDB has no batch endpoint— but
     * they do not have to be sequential: 20 ids take ~320 ms in parallel
     * against ~3.4 s one after another. Used to fill in the posters of a page
     * of search results.
     *
     * @param array<int,string> $imdbIds
     * @return array<string,array> Keyed by imdb id; ids TMDB does not know are absent
     */
    public function findByImdbIds(array $imdbIds): array
    {
        if (empty($this->apiKey) || $imdbIds === []) {
            return [];
        }

        $found    = [];
        $promises = [];

        foreach (array_unique($imdbIds) as $imdbId) {
            $cached = $this->cache->get('find_' . $imdbId, self::NAMESPACE);
            if ($cached !== null) {
                $found[$imdbId] = $cached;
                continue;
            }

            $promises[$imdbId] = $this->client->getAsync('find/' . rawurlencode($imdbId), [
                'query' => [
                    'external_source' => 'imdb_id',
                    'api_key'         => $this->apiKey,
                    'language'        => self::LANGUAGE,
                ],
            ]);
        }

        if ($promises === []) {
            return $found;
        }

        // settle() y no unwrap(): una respuesta que falle no debe tumbar el lote.
        foreach (Utils::settle($promises)->wait() as $imdbId => $outcome) {
            if ($outcome['state'] !== 'fulfilled') {
                continue;
            }

            $data = json_decode($outcome['value']->getBody()->getContents(), true);
            $hit  = $data['movie_results'][0] ?? $data['tv_results'][0] ?? null;
            if ($hit === null) {
                continue;
            }

            $result = [
                'tmdb_id'     => (int) $hit['id'],
                'media_type'  => isset($data['movie_results'][0]) ? 'movie' : 'tv',
                'title'       => $hit['title'] ?? $hit['name'] ?? null,
                'overview'    => ($hit['overview'] ?? '') !== '' ? $hit['overview'] : null,
                'poster_path' => $hit['poster_path'] ?? null,
            ];

            $this->cache->set('find_' . $imdbId, $result, self::CACHE_TTL, self::NAMESPACE);
            $found[$imdbId] = $result;
        }

        return $found;
    }

    /**
     * Full record for a title already resolved to a TMDB id.
     *
     * @param string $mediaType 'movie' | 'tv'
     * @return array|null Raw TMDB payload, with credits appended
     */
    public function details(int $tmdbId, string $mediaType): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $cacheKey = 'details_' . $mediaType . '_' . $tmdbId;
        $cached   = $this->cache->get($cacheKey, self::NAMESPACE);
        if ($cached !== null) {
            return $cached;
        }

        $data = $this->get($mediaType . '/' . $tmdbId, ['append_to_response' => 'credits']);
        if ($data === null) {
            return null;
        }

        $this->cache->set($cacheKey, $data, self::CACHE_TTL, self::NAMESPACE);

        return $data;
    }

    /**
     * Episodes of one season of a series.
     *
     * @return array Raw TMDB episode list, or empty on failure
     */
    public function seasonEpisodes(int $tmdbId, int $season): array
    {
        if (empty($this->apiKey)) {
            return [];
        }

        $cacheKey = 'season_' . $tmdbId . '_' . $season;
        $cached   = $this->cache->get($cacheKey, self::NAMESPACE);
        if ($cached !== null) {
            return $cached;
        }

        $data = $this->get('tv/' . $tmdbId . '/season/' . $season);
        if ($data === null) {
            return [];
        }

        $episodes = $data['episodes'] ?? [];
        $this->cache->set($cacheKey, $episodes, self::CACHE_TTL, self::NAMESPACE);

        return $episodes;
    }

    /**
     * One GET against TMDB, with the api key and language already applied.
     */
    private function get(string $path, array $query = []): ?array
    {
        try {
            $response = $this->client->get($path, [
                'query' => $query + [
                    'api_key'  => $this->apiKey,
                    'language' => self::LANGUAGE,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (GuzzleException $e) {
            $this->logger->error('TmdbService: request failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
