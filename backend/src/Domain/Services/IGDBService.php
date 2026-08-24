<?php

declare(strict_types=1);

namespace App\Domain\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\ResilientCall;

/**
 * Service for interacting with IGDB (Internet Game Database) API
 * Provides game data via Twitch API
 */
class IGDBService
{
    private Client $client;
    private LoggerInterface $logger;
    private CacheService $cache;
    private ResilientCall $resilient;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $accessToken = null;
    private ?int $tokenExpiration = null;
    
    private const BASE_URL = 'https://api.igdb.com/v4';
    private const AUTH_URL = 'https://id.twitch.tv/oauth2/token';
    private const CACHE_TTL_TOKEN = 5184000; // 60 days
    private const CACHE_KEY_TOKEN = 'igdb_access_token';
    private const CACHE_TTL_SEARCH = 21600; // 6 hours

    public function __construct(CacheService $cache, ResilientCall $resilient, LoggerInterface $logger)
    {
        // Get credentials from environment
        $this->clientId = $_ENV['IGDB_CLIENT_ID'] ?? null;
        $this->clientSecret = $_ENV['IGDB_CLIENT_SECRET'] ?? null;
        
        $this->client = new Client([
            'timeout' => 10.0,
            'connect_timeout' => 3.0,
            'headers' => [
                'User-Agent' => 'LibraryVue/1.0 (Educational Project)',
                'Accept' => 'application/json'
            ]
        ]);
        $this->cache = $cache;
        $this->resilient = $resilient;
        $this->logger = $logger;
        
        if (empty($this->clientId) || empty($this->clientSecret)) {
            $this->logger->error("IGDB: Credentials not configured");
        } else {
            $this->logger->info("IGDB: Credentials configured", [
                'client_id_prefix' => substr($this->clientId, 0, 10) . '...'
            ]);
        }
    }

    /**
     * Get the Client ID (safe to expose to frontend)
     * 
     * @return string|null Client ID
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * Get or refresh the access token
     * 
     * @return string|null Access token
     * @throws \RuntimeException if credentials are not configured
     */
    public function getAccessToken(): ?string
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \RuntimeException('IGDB credentials not configured');
        }

        // Check if we have a valid cached token
        if ($this->accessToken && $this->tokenExpiration && time() < $this->tokenExpiration) {
            return $this->accessToken;
        }

        // Try to get token from cache
        $cachedToken = $this->cache->get(self::CACHE_KEY_TOKEN);
        if ($cachedToken) {
            $this->accessToken = $cachedToken['token'];
            $this->tokenExpiration = $cachedToken['expiration'];
            
            if (time() < $this->tokenExpiration) {
                $this->logger->debug("IGDB: Using cached access token");
                return $this->accessToken;
            }
        }

        // Request new token
        return $this->refreshAccessToken();
    }

    /**
     * Request a new access token from Twitch OAuth
     * 
     * @return string Access token
     * @throws \RuntimeException if token request fails
     */
    private function refreshAccessToken(): string
    {
        try {
            $this->logger->info("IGDB: Requesting new access token");

            $response = $this->client->post(self::AUTH_URL, [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['access_token'])) {
                throw new \RuntimeException('Invalid token response from IGDB');
            }

            $this->accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? self::CACHE_TTL_TOKEN;
            $this->tokenExpiration = time() + $expiresIn;

            // Cache the token
            $this->cache->set(self::CACHE_KEY_TOKEN, [
                'token' => $this->accessToken,
                'expiration' => $this->tokenExpiration
            ], $expiresIn);

            $this->logger->info("IGDB: Access token obtained", [
                'expires_in' => $expiresIn,
                'token_prefix' => substr($this->accessToken, 0, 10) . '...'
            ]);

            return $this->accessToken;

        } catch (GuzzleException $e) {
            $this->logger->error("IGDB: Failed to obtain access token", [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to obtain IGDB access token: ' . $e->getMessage());
        }
    }

    /**
     * Get token information for frontend
     * 
     * @return array Token data with accessToken and expiresIn
     */
    public function getTokenInfo(): array
    {
        try {
            $token = $this->getAccessToken();
            $expiresIn = $this->tokenExpiration ? ($this->tokenExpiration - time()) : 0;

            return [
                'accessToken' => $token,
                'expiresIn' => $expiresIn,
                'tokenType' => 'bearer'
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to get token info: ' . $e->getMessage());
        }
    }

    /**
     * Search games in IGDB
     * 
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @return array Array of games
     */
    public function searchGames(string $query, int $limit = 20): array
    {
        return $this->searchGamesResilient($query, $limit)['data'];
    }

    /**
     * Search games, falling back to the last known results when IGDB fails
     *
     * Still throws when there is nothing cached to serve: that is the historic
     * contract, and GameController turns it into a 503 (:357).
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function searchGamesResilient(string $query, int $limit = 20): array
    {
        $cacheKey = 'search_' . md5($query . '_' . $limit);

        return $this->resilient->around(
            $cacheKey,
            'igdb',
            self::CACHE_TTL_SEARCH,
            fn() => $this->runGameSearch($query, $limit)
        );
    }

    /**
     * @throws \RuntimeException When IGDB cannot be reached
     */
    private function runGameSearch(string $query, int $limit): array
    {
        try {
            $token = $this->getAccessToken();

            $body = "search \"{$query}\"; fields name, cover.url, first_release_date, summary, rating, platforms.name, genres.name, involved_companies.company.name, involved_companies.developer, involved_companies.publisher; limit {$limit};";

            $this->logger->info("IGDB: Searching games", [
                'query' => $query,
                'limit' => $limit
            ]);

            $response = $this->client->post(self::BASE_URL . '/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'text/plain'
                ],
                'body' => $body
            ]);

            $games = json_decode($response->getBody()->getContents(), true);

            $this->logger->info("IGDB: Search completed", [
                'results' => count($games)
            ]);

            return $games ?? [];

        } catch (GuzzleException $e) {
            $this->logger->error("IGDB: Search failed", [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to search games in IGDB: ' . $e->getMessage());
        }
    }

    /**
     * Get game by IGDB ID
     * 
     * @param int $gameId IGDB game ID
     * @return array|null Game data or null if not found
     */
    public function getGameById(int $gameId): ?array
    {
        try {
            $token = $this->getAccessToken();

            $body = "where id = {$gameId}; fields name, cover.url, first_release_date, summary, rating, platforms.name, genres.name, involved_companies.company.name, involved_companies.developer, involved_companies.publisher, screenshots.url, videos.video_id, websites.url, websites.category; limit 1;";

            $this->logger->info("IGDB: Getting game by ID", [
                'game_id' => $gameId
            ]);

            $response = $this->client->post(self::BASE_URL . '/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'text/plain'
                ],
                'body' => $body
            ]);

            $games = json_decode($response->getBody()->getContents(), true);

            return $games[0] ?? null;

        } catch (GuzzleException $e) {
            $this->logger->error("IGDB: Failed to get game by ID", [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Execute a custom IGDB query
     * 
     * @param string $endpoint API endpoint (e.g., '/games')
     * @param string $body Query body in IGDB query language
     * @return array Query results
     */
    public function executeQuery(string $endpoint, string $body): array
    {
        try {
            $token = $this->getAccessToken();

            $this->logger->debug("IGDB: Executing custom query", [
                'endpoint' => $endpoint,
                'body' => $body
            ]);

            $response = $this->client->post(self::BASE_URL . $endpoint, [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'text/plain'
                ],
                'body' => $body
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            return $result ?? [];

        } catch (GuzzleException $e) {
            $this->logger->error("IGDB: Query execution failed", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to execute IGDB query: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed game information including screenshots
     * 
     * @param int $gameId IGDB game ID
     * @return array|null Game details with screenshots
     */
    public function getGameDetails(int $gameId): ?array
    {
        try {
            $token = $this->getAccessToken();
            
            $this->logger->info("IGDB: Getting detailed game information", [
                'game_id' => $gameId
            ]);

            // Get game details
            $gameBody = "fields name, cover.url, first_release_date, summary, rating, rating_count, platforms.name, genres.name, involved_companies.company.name, involved_companies.developer, involved_companies.publisher, screenshots.url, age_ratings.category, age_ratings.rating, websites.url, websites.category; where id = {$gameId};";
            
            $gameResponse = $this->client->post(self::BASE_URL . '/games', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'text/plain'
                ],
                'body' => $gameBody
            ]);

            $games = json_decode($gameResponse->getBody()->getContents(), true);
            
            if (empty($games)) {
                return null;
            }

            $game = $games[0];

            // Get detailed screenshots
            $screenshotsBody = "fields url, image_id; where game = {$gameId}; limit 10;";
            
            $screenshotsResponse = $this->client->post(self::BASE_URL . '/screenshots', [
                'headers' => [
                    'Client-ID' => $this->clientId,
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'text/plain'
                ],
                'body' => $screenshotsBody
            ]);

            $screenshots = json_decode($screenshotsResponse->getBody()->getContents(), true);
            
            // Add screenshots to game data
            $game['detailed_screenshots'] = $screenshots ?? [];

            return $game;

        } catch (GuzzleException $e) {
            $this->logger->error("IGDB: Failed to get game details", [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
