<?php

declare(strict_types=1);

namespace App\Domain\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Cache\CacheService;

/**
 * Service for interacting with the OMDb (Open Movie Database) API.
 * Handles movie/series search, full detail retrieval, and season episode lookup.
 * Responses are cached to minimise external API calls.
 */
class OmdbService
{
    private Client $client;
    private ?string $apiKey;

    private const BASE_URL    = 'https://www.omdbapi.com/';
    private const CACHE_TTL   = 86400;     // 24 hours
    private const NAMESPACE   = 'omdb';

    public function __construct(
        private readonly CacheService  $cache,
        private readonly LoggerInterface $logger
    ) {
        $this->apiKey = $_ENV['OMDB_API_KEY'] ?? null;

        $this->client = new Client([
            'base_uri'        => self::BASE_URL,
            'timeout'         => 10.0,
            'connect_timeout' => 3.0,
            'headers'         => [
                'User-Agent' => 'LibraryVue/1.0 (Educational Project)',
                'Accept'     => 'application/json',
            ],
        ]);

        if (empty($this->apiKey)) {
            $this->logger->warning('OmdbService: OMDB_API_KEY is not configured');
        }
    }

    /**
     * Search movies/series by title.
     *
     * @param string $title  Search term
     * @param string $type   'movie' | 'series' | '' (all)
     * @return array         Array of search results or empty on failure
     */
    public function searchByTitle(string $title, string $type = ''): array
    {
        if (empty($this->apiKey)) {
            return ['error' => true, 'message' => 'OMDb API key not configured'];
        }

        $cacheKey = 'search_' . md5($title . '_' . $type);
        $cached   = $this->cache->get($cacheKey, self::NAMESPACE);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $params = ['apikey' => $this->apiKey, 's' => $title];
            if (!empty($type)) {
                $params['type'] = $type;
            }

            $response = $this->client->get('', ['query' => $params]);
            $data     = json_decode($response->getBody()->getContents(), true);

            if (($data['Response'] ?? '') === 'True') {
                $result = $data['Search'] ?? [];
                $this->cache->set($cacheKey, $result, self::CACHE_TTL, self::NAMESPACE);
                return $result;
            }

            $this->logger->info('OmdbService: search returned no results', [
                'title' => $title,
                'error' => $data['Error'] ?? 'unknown',
            ]);
            return [];

        } catch (GuzzleException $e) {
            $this->logger->error('OmdbService: searchByTitle failed', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get full movie/series details by IMDb ID.
     *
     * @param string $imdbId   IMDb ID (e.g. "tt1234567")
     * @param string $plot     'short' | 'full'
     * @return array|null      Normalised movie data, or null on failure
     */
    public function getDetailsByImdbId(string $imdbId, string $plot = 'full'): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $cacheKey = 'details_' . $imdbId . '_' . $plot;
        $cached   = $this->cache->get($cacheKey, self::NAMESPACE);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->client->get('', [
                'query' => [
                    'apikey' => $this->apiKey,
                    'i'      => $imdbId,
                    'plot'   => $plot,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (($data['Response'] ?? '') === 'True') {
                $this->cache->set($cacheKey, $data, self::CACHE_TTL, self::NAMESPACE);
                return $data;
            }

            $this->logger->info('OmdbService: no details found', [
                'imdbId' => $imdbId,
                'error'  => $data['Error'] ?? 'unknown',
            ]);
            return null;

        } catch (GuzzleException $e) {
            $this->logger->error('OmdbService: getDetailsByImdbId failed', [
                'imdbId' => $imdbId,
                'error'  => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get episode list for a specific season of a series.
     *
     * @param string $imdbId        Series IMDb ID
     * @param int    $seasonNumber  Season number
     * @return array                Array of episodes, or empty on failure
     */
    public function getSeasonEpisodes(string $imdbId, int $seasonNumber): array
    {
        if (empty($this->apiKey)) {
            return [];
        }

        $cacheKey = 'season_' . $imdbId . '_' . $seasonNumber;
        $cached   = $this->cache->get($cacheKey, self::NAMESPACE);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->client->get('', [
                'query' => [
                    'apikey' => $this->apiKey,
                    'i'      => $imdbId,
                    'Season' => $seasonNumber,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (($data['Response'] ?? '') === 'True' && isset($data['Episodes'])) {
                $episodes = $data['Episodes'];
                $this->cache->set($cacheKey, $episodes, self::CACHE_TTL, self::NAMESPACE);
                return $episodes;
            }

            return [];

        } catch (GuzzleException $e) {
            $this->logger->error('OmdbService: getSeasonEpisodes failed', [
                'imdbId'       => $imdbId,
                'seasonNumber' => $seasonNumber,
                'error'        => $e->getMessage(),
            ]);
            return [];
        }
    }
}
