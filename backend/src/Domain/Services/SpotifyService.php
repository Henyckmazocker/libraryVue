<?php

declare(strict_types=1);

namespace App\Domain\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Http\HttpClientFactory;

/**
 * Service for interacting with Spotify Web API
 *
 * Uses Client Credentials Flow (no user login required) to access
 * the public catalogue: albums, artists, search, new releases.
 */
class SpotifyService
{
    private Client $client;
    private LoggerInterface $logger;
    private CacheService $cache;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $accessToken = null;
    private ?int $tokenExpiration = null;

    private const BASE_URL = 'https://api.spotify.com/v1';
    private const AUTH_URL = 'https://accounts.spotify.com/api/token';
    private const CACHE_KEY_TOKEN = 'spotify_access_token';
    private const TOKEN_CACHE_TTL = 3500;   // 1 h minus safety margin
    private const SEARCH_CACHE_TTL = 1800;  // 30 min
    private const DETAIL_CACHE_TTL = 86400; // 24 h

    public function __construct(CacheService $cache, LoggerInterface $logger, HttpClientFactory $http)
    {
        $this->clientId = $_ENV['SPOTIFY_CLIENT_ID'] ?? null;
        $this->clientSecret = $_ENV['SPOTIFY_CLIENT_SECRET'] ?? null;

        $this->client = $http->create(HttpClientFactory::PROFILE_WEB, 'LibraryVue/1.0 (Educational Project)', [
            'timeout'         => 10.0,
            'connect_timeout' => 3.0,
            'headers'         => ['Accept' => 'application/json'],
        ]);

        $this->cache = $cache;
        $this->logger = $logger;

        if (empty($this->clientId) || empty($this->clientSecret)) {
            $this->logger->error('Spotify: Credentials not configured.');
        } else {
            $this->logger->info('Spotify: Credentials configured.', [
                'client_id_prefix' => substr($this->clientId, 0, 8) . '...',
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    /**
     * Return a valid access token, refreshing if necessary.
     *
     * @throws \RuntimeException if credentials are missing or the request fails
     */
    public function getAccessToken(): string
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \RuntimeException('Spotify credentials not configured.');
        }

        // In-memory check
        if ($this->accessToken && $this->tokenExpiration && time() < $this->tokenExpiration) {
            return $this->accessToken;
        }

        // File-cache check
        $cachedToken = $this->cache->get(self::CACHE_KEY_TOKEN);
        if ($cachedToken) {
            $this->accessToken = $cachedToken['token'];
            $this->tokenExpiration = $cachedToken['expiration'];

            if (time() < $this->tokenExpiration) {
                $this->logger->debug('Spotify: Using cached access token.');
                return $this->accessToken;
            }
        }

        return $this->refreshAccessToken();
    }

    /**
     * Request a new access token from Spotify using Client Credentials Flow.
     */
    private function refreshAccessToken(): string
    {
        try {
            $this->logger->info('Spotify: Requesting new access token.');

            $response = $this->client->post(self::AUTH_URL, [
                'form_params' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['access_token'])) {
                throw new \RuntimeException('Invalid token response from Spotify.');
            }

            $this->accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? self::TOKEN_CACHE_TTL;
            $this->tokenExpiration = time() + $expiresIn;

            $this->cache->set(self::CACHE_KEY_TOKEN, [
                'token'      => $this->accessToken,
                'expiration' => $this->tokenExpiration,
            ], $expiresIn);

            $this->logger->info('Spotify: Access token obtained.', [
                'expires_in'   => $expiresIn,
                'token_prefix' => substr($this->accessToken, 0, 10) . '...',
            ]);

            return $this->accessToken;

        } catch (GuzzleException $e) {
            $this->logger->error('Spotify: Failed to obtain access token.', [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to obtain Spotify access token: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    /**
     * Search albums on Spotify.
     *
     * @param string $query  Search query (supports field filters: artist:, year:, label:, upc:)
     * @param int    $limit  Maximum results (1–50)
     * @return array Simplified album objects (without tracks)
     * @throws \RuntimeException on API failure
     */
    public function searchAlbums(string $query, int $limit = 20): array
    {
        $cacheKey = 'search_' . md5($query . '_' . $limit);

        $cached = $this->cache->get($cacheKey, 'spotify');
        if ($cached !== null) {
            $this->logger->debug('Spotify: Search cache hit.', ['query' => $query]);
            return $cached;
        }

        try {
            $results = $this->makeRequest('/search', [
                'q'     => $query,
                'type'  => 'album',
                'limit' => min(50, max(1, $limit)),
            ]);

            $albums = $results['albums']['items'] ?? [];

            $this->cache->set($cacheKey, $albums, self::SEARCH_CACHE_TTL, 'spotify');

            $this->logger->info('Spotify: Album search completed.', [
                'query'   => $query,
                'results' => count($albums),
            ]);

            return $albums;

        } catch (\RuntimeException $e) {
            $this->logger->error('Spotify: Album search failed.', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Album
    // -------------------------------------------------------------------------

    /**
     * Get full album data by Spotify ID.
     *
     * The response includes album.tracks.items[] (simplified track objects).
     *
     * @param string $spotifyId Spotify album ID (base62)
     * @return array|null Album data or null on failure
     */
    public function getAlbum(string $spotifyId): ?array
    {
        $cacheKey = 'album_' . $spotifyId;
        $cached = $this->cache->get($cacheKey, 'spotify');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $album = $this->makeRequest('/albums/' . urlencode($spotifyId));

            $this->cache->set($cacheKey, $album, self::DETAIL_CACHE_TTL, 'spotify');

            $this->logger->info('Spotify: Album fetched.', ['spotify_id' => $spotifyId]);

            return $album;

        } catch (\RuntimeException $e) {
            $this->logger->error('Spotify: Failed to fetch album.', [
                'spotify_id' => $spotifyId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get the tracklist for an album (paginated, returns all tracks).
     *
     * @param string $spotifyId Spotify album ID
     * @return array Simplified track objects
     */
    public function getAlbumTracks(string $spotifyId): array
    {
        $cacheKey = 'tracks_' . $spotifyId;
        $cached = $this->cache->get($cacheKey, 'spotify');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->makeRequest('/albums/' . urlencode($spotifyId) . '/tracks', [
                'limit' => 50,
            ]);

            $tracks = $result['items'] ?? [];

            $this->cache->set($cacheKey, $tracks, self::DETAIL_CACHE_TTL, 'spotify');

            return $tracks;

        } catch (\RuntimeException $e) {
            $this->logger->error('Spotify: Failed to fetch album tracks.', [
                'spotify_id' => $spotifyId,
                'error'      => $e->getMessage(),
            ]);
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Artist
    // -------------------------------------------------------------------------

    /**
     * Get artist data by Spotify ID.
     *
     * This is the ONLY reliable source of genres for an album
     * (album.genres[] is deprecated and almost always empty on Spotify).
     *
     * @param string $artistId Spotify artist ID
     * @return array|null Artist data including genres[], images[], popularity, followers
     */
    public function getArtist(string $artistId): ?array
    {
        $cacheKey = 'artist_' . $artistId;
        $cached = $this->cache->get($cacheKey, 'spotify');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $artist = $this->makeRequest('/artists/' . urlencode($artistId));

            $this->cache->set($cacheKey, $artist, self::DETAIL_CACHE_TTL, 'spotify');

            $this->logger->info('Spotify: Artist fetched.', ['artist_id' => $artistId]);

            return $artist;

        } catch (\RuntimeException $e) {
            $this->logger->error('Spotify: Failed to fetch artist.', [
                'artist_id' => $artistId,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get the discography for an artist (albums and singles).
     *
     * @param string $artistId Spotify artist ID
     * @param int    $limit    Maximum results (1–50)
     * @return array Simplified album objects
     */
    public function getArtistAlbums(string $artistId, int $limit = 50): array
    {
        $cacheKey = 'artist_albums_' . $artistId;
        $cached = $this->cache->get($cacheKey, 'spotify');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->makeRequest('/artists/' . urlencode($artistId) . '/albums', [
                'include_groups' => 'album,single',
                'limit'          => min(50, max(1, $limit)),
            ]);

            $albums = $result['items'] ?? [];

            $this->cache->set($cacheKey, $albums, self::DETAIL_CACHE_TTL, 'spotify');

            return $albums;

        } catch (\RuntimeException $e) {
            $this->logger->error('Spotify: Failed to fetch artist albums.', [
                'artist_id' => $artistId,
                'error'     => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get an artist's top 10 tracks.
     *
     * @param string $artistId Spotify artist ID
     * @return array Track objects with popularity
     */
    public function getArtistTopTracks(string $artistId): array
    {
        $cacheKey = 'top_tracks_' . $artistId;
        $cached = $this->cache->get($cacheKey, 'spotify');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->makeRequest('/artists/' . urlencode($artistId) . '/top-tracks');

            $tracks = $result['tracks'] ?? [];

            $this->cache->set($cacheKey, $tracks, self::SEARCH_CACHE_TTL, 'spotify');

            return $tracks;

        } catch (\RuntimeException $e) {
            $this->logger->error('Spotify: Failed to fetch artist top tracks.', [
                'artist_id' => $artistId,
                'error'     => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get related artists for a given artist (up to 20).
     *
     * @param string $artistId Spotify artist ID
     * @return array Artist objects
     */
    public function getRelatedArtists(string $artistId): array
    {
        $cacheKey = 'related_' . $artistId;
        $cached = $this->cache->get($cacheKey, 'spotify');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->makeRequest('/artists/' . urlencode($artistId) . '/related-artists');

            $artists = $result['artists'] ?? [];

            $this->cache->set($cacheKey, $artists, self::DETAIL_CACHE_TTL, 'spotify');

            return $artists;

        } catch (\RuntimeException $e) {
            $this->logger->error('Spotify: Failed to fetch related artists.', [
                'artist_id' => $artistId,
                'error'     => $e->getMessage(),
            ]);
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Browse
    // -------------------------------------------------------------------------

    /**
     * Get newly released albums (useful for "Discover" / trending section).
     *
     * @param int $limit Maximum results (1–50)
     * @return array Simplified album objects
     */
    public function getNewReleases(int $limit = 20): array
    {
        $cacheKey = 'new_releases_' . $limit;
        $cached = $this->cache->get($cacheKey, 'spotify');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->makeRequest('/browse/new-releases', [
                'limit' => min(50, max(1, $limit)),
            ]);

            $albums = $result['albums']['items'] ?? [];

            $this->cache->set($cacheKey, $albums, self::SEARCH_CACHE_TTL, 'spotify');

            $this->logger->info('Spotify: New releases fetched.', ['count' => count($albums)]);

            return $albums;

        } catch (\RuntimeException $e) {
            $this->logger->error('Spotify: Failed to fetch new releases.', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Execute an authenticated GET request against the Spotify API.
     *
     * @param string $endpoint Relative endpoint path (e.g. "/search")
     * @param array  $params   Query string parameters
     * @return array Decoded JSON response
     * @throws \RuntimeException on HTTP or parsing failure
     */
    private function makeRequest(string $endpoint, array $params = []): array
    {
        $token = $this->getAccessToken();

        try {
            $response = $this->client->get(self::BASE_URL . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'query' => $params,
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response from Spotify.');
            }

            return $data ?? [];

        } catch (GuzzleException $e) {
            $this->logger->error('Spotify: HTTP request failed.', [
                'endpoint' => $endpoint,
                'error'    => $e->getMessage(),
            ]);
            throw new \RuntimeException('Spotify API request failed: ' . $e->getMessage());
        }
    }
}
