<?php

declare(strict_types=1);

namespace App\Domain\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\ResilientCall;
use App\Infrastructure\Http\HttpClientFactory;

/**
 * Service for interacting with the Last.fm REST API.
 *
 * All data is fetched on-demand and cached server-side.
 * No user OAuth is required — the public API key is sufficient for
 * read-only stats on any public Last.fm profile.
 *
 * API docs: https://www.last.fm/api
 */
class LastFmService
{
    private Client $client;
    private ?string $apiKey;

    private const BASE_URL = 'https://ws.audioscrobbler.com/2.0/';
    private const CACHE_NS  = 'lastfm';

    // Cache TTLs (seconds)
    private const TTL_USER_INFO    = 3600;   // 1 h
    private const TTL_TOP          = 21600;  // 6 h
    private const TTL_RECENT       = 900;    // 15 min  – changes often
    private const TTL_LOVED        = 3600;   // 1 h
    private const TTL_WEEKLY_CHART = 86400;  // 24 h  – weekly, very stable

    public function __construct(
        private readonly CacheService $cache,
        private readonly ResilientCall $resilient,
        private readonly LoggerInterface $logger,
        HttpClientFactory $http
    ) {
        $this->apiKey = $_ENV['LASTFM_API_KEY'] ?? null;

        $this->client = $http->create(HttpClientFactory::PROFILE_WEB, 'LibraryVue/1.0 (Educational Project)', [
            'timeout'         => 10.0,
            'connect_timeout' => 3.0,
            'headers'         => ['Accept' => 'application/json'],
        ]);

        if (empty($this->apiKey)) {
            $this->logger->warning('LastFm: LASTFM_API_KEY not configured.');
        }
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Get basic info about a Last.fm user.
     */
    public function getUserInfo(string $username): array
    {
        return $this->getUserInfoResilient($username)['data'];
    }

    /**
     * Same call, keeping the freshness of what is being served.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function getUserInfoResilient(string $username): array
    {
        return $this->cachedCallResilient(
            "user_info_{$username}",
            self::TTL_USER_INFO,
            fn() => $this->request(['method' => 'user.getInfo', 'user' => $username])
        );
    }

    /**
     * Get user's top albums.
     *
     * @param string $period  overall | 7day | 1month | 3month | 6month | 12month
     */
    public function getTopAlbums(string $username, string $period = 'overall', int $limit = 20): array
    {
        return $this->getTopAlbumsResilient($username, $period, $limit)['data'];
    }

    /**
     * Same call, keeping the freshness of what is being served.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function getTopAlbumsResilient(string $username, string $period = 'overall', int $limit = 20): array
    {
        return $this->cachedCallResilient(
            "top_albums_{$username}_{$period}_{$limit}",
            self::TTL_TOP,
            fn() => $this->request([
                'method' => 'user.getTopAlbums',
                'user'   => $username,
                'period' => $period,
                'limit'  => $limit,
            ])
        );
    }

    /**
     * Get user's top artists.
     *
     * @param string $period  overall | 7day | 1month | 3month | 6month | 12month
     */
    public function getTopArtists(string $username, string $period = 'overall', int $limit = 20): array
    {
        return $this->getTopArtistsResilient($username, $period, $limit)['data'];
    }

    /**
     * Same call, keeping the freshness of what is being served.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function getTopArtistsResilient(string $username, string $period = 'overall', int $limit = 20): array
    {
        return $this->cachedCallResilient(
            "top_artists_{$username}_{$period}_{$limit}",
            self::TTL_TOP,
            fn() => $this->request([
                'method' => 'user.getTopArtists',
                'user'   => $username,
                'period' => $period,
                'limit'  => $limit,
            ])
        );
    }

    /**
     * Get user's top tracks.
     *
     * @param string $period  overall | 7day | 1month | 3month | 6month | 12month
     */
    public function getTopTracks(string $username, string $period = 'overall', int $limit = 20): array
    {
        return $this->getTopTracksResilient($username, $period, $limit)['data'];
    }

    /**
     * Same call, keeping the freshness of what is being served.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function getTopTracksResilient(string $username, string $period = 'overall', int $limit = 20): array
    {
        return $this->cachedCallResilient(
            "top_tracks_{$username}_{$period}_{$limit}",
            self::TTL_TOP,
            fn() => $this->request([
                'method' => 'user.getTopTracks',
                'user'   => $username,
                'period' => $period,
                'limit'  => $limit,
            ])
        );
    }

    /**
     * Get recent tracks (scrobbles) for the user.
     */
    public function getRecentTracks(string $username, int $limit = 20): array
    {
        return $this->getRecentTracksResilient($username, $limit)['data'];
    }

    /**
     * Same call, keeping the freshness of what is being served.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function getRecentTracksResilient(string $username, int $limit = 20): array
    {
        return $this->cachedCallResilient(
            "recent_tracks_{$username}_{$limit}",
            self::TTL_RECENT,
            fn() => $this->request([
                'method'    => 'user.getRecentTracks',
                'user'      => $username,
                'limit'     => $limit,
                'extended'  => 1,
            ])
        );
    }

    /**
     * Get tracks the user has loved/starred.
     */
    public function getLovedTracks(string $username, int $limit = 20): array
    {
        return $this->getLovedTracksResilient($username, $limit)['data'];
    }

    /**
     * Same call, keeping the freshness of what is being served.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function getLovedTracksResilient(string $username, int $limit = 20): array
    {
        return $this->cachedCallResilient(
            "loved_tracks_{$username}_{$limit}",
            self::TTL_LOVED,
            fn() => $this->request([
                'method' => 'user.getLovedTracks',
                'user'   => $username,
                'limit'  => $limit,
            ])
        );
    }

    /**
     * Get the user's weekly album chart.
     */
    public function getWeeklyAlbumChart(string $username): array
    {
        return $this->getWeeklyAlbumChartResilient($username)['data'];
    }

    /**
     * Same call, keeping the freshness of what is being served.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function getWeeklyAlbumChartResilient(string $username): array
    {
        return $this->cachedCallResilient(
            "weekly_album_chart_{$username}",
            self::TTL_WEEKLY_CHART,
            fn() => $this->request([
                'method' => 'user.getWeeklyAlbumChart',
                'user'   => $username,
            ])
        );
    }

    /**
     * Get the user's weekly artist chart.
     */
    public function getWeeklyArtistChart(string $username): array
    {
        return $this->getWeeklyArtistChartResilient($username)['data'];
    }

    /**
     * Same call, keeping the freshness of what is being served.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function getWeeklyArtistChartResilient(string $username): array
    {
        return $this->cachedCallResilient(
            "weekly_artist_chart_{$username}",
            self::TTL_WEEKLY_CHART,
            fn() => $this->request([
                'method' => 'user.getWeeklyArtistChart',
                'user'   => $username,
            ])
        );
    }

    /**
     * Get info for a specific album (global stats + optional personal playcount).
     *
     * @param string      $artist   Artist name
     * @param string      $album    Album title
     * @param string|null $username Last.fm username to include personal playcount (optional)
     */
    public function getAlbumInfo(string $artist, string $album, ?string $username = null): array
    {
        return $this->getAlbumInfoResilient($artist, $album, $username)['data'];
    }

    /**
     * Same call, keeping the freshness of what is being served.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function getAlbumInfoResilient(string $artist, string $album, ?string $username = null): array
    {
        $key = 'album_info_' . md5($artist . '|' . $album . '|' . ($username ?? ''));
        return $this->cachedCallResilient(
            $key,
            self::TTL_USER_INFO,
            function () use ($artist, $album, $username) {
                $params = [
                    'method' => 'album.getInfo',
                    'artist' => $artist,
                    'album'  => $album,
                ];
                if ($username) {
                    $params['username'] = $username;
                }
                return $this->request($params);
            }
        );
    }

    // -------------------------------------------------------------------------
    // Normalised data accessors (extract from raw Last.fm response)
    // -------------------------------------------------------------------------

    /**
     * Return a flat array of top albums, each with:
     *   name, artist, playcount, url, image (largest available)
     */
    public function parseTopAlbums(array $raw): array
    {
        $albums = $raw['topalbums']['album'] ?? [];
        if (!is_array($albums)) return [];

        return array_map(function (array $a) {
            return [
                'name'      => $a['name'] ?? '',
                'artist'    => $a['artist']['name'] ?? '',
                'playcount' => (int) ($a['playcount'] ?? 0),
                'url'       => $a['url'] ?? '',
                'image'     => $this->bestImage($a['image'] ?? []),
                'mbid'      => $a['mbid'] ?? null,
            ];
        }, $albums);
    }

    /**
     * Return a flat array of top artists.
     */
    public function parseTopArtists(array $raw): array
    {
        $artists = $raw['topartists']['artist'] ?? [];
        if (!is_array($artists)) return [];

        return array_map(function (array $a) {
            return [
                'name'      => $a['name'] ?? '',
                'playcount' => (int) ($a['playcount'] ?? 0),
                'url'       => $a['url'] ?? '',
                'image'     => $this->bestImage($a['image'] ?? []),
                'mbid'      => $a['mbid'] ?? null,
            ];
        }, $artists);
    }

    /**
     * Return a flat array of top tracks.
     */
    public function parseTopTracks(array $raw): array
    {
        $tracks = $raw['toptracks']['track'] ?? [];
        if (!is_array($tracks)) return [];

        return array_map(function (array $t) {
            return [
                'name'      => $t['name'] ?? '',
                'artist'    => $t['artist']['name'] ?? '',
                'playcount' => (int) ($t['playcount'] ?? 0),
                'duration'  => (int) ($t['duration'] ?? 0),
                'url'       => $t['url'] ?? '',
                'image'     => $this->bestImage($t['image'] ?? []),
                'mbid'      => $t['mbid'] ?? null,
            ];
        }, $tracks);
    }

    /**
     * Return a flat array of recent scrobbles.
     */
    public function parseRecentTracks(array $raw): array
    {
        $tracks = $raw['recenttracks']['track'] ?? [];
        if (!is_array($tracks)) return [];

        return array_map(function (array $t) {
            $nowPlaying = isset($t['@attr']['nowplaying']) && $t['@attr']['nowplaying'] === 'true';
            return [
                'name'       => $t['name'] ?? '',
                'artist'     => is_array($t['artist']) ? ($t['artist']['#text'] ?? ($t['artist']['name'] ?? '')) : ($t['artist'] ?? ''),
                'album'      => is_array($t['album']) ? ($t['album']['#text'] ?? '') : ($t['album'] ?? ''),
                'url'        => $t['url'] ?? '',
                'image'      => $this->bestImage($t['image'] ?? []),
                'date'       => $t['date']['uts'] ?? null,
                'date_text'  => $t['date']['#text'] ?? ($nowPlaying ? 'Now playing' : ''),
                'now_playing'=> $nowPlaying,
                'loved'      => isset($t['loved']) && $t['loved'] === '1',
            ];
        }, $tracks);
    }

    /**
     * Return parsed user info summary.
     */
    public function parseUserInfo(array $raw): array
    {
        $u = $raw['user'] ?? [];
        return [
            'name'        => $u['name'] ?? '',
            'realname'    => $u['realname'] ?? '',
            'url'         => $u['url'] ?? '',
            'country'     => $u['country'] ?? '',
            'age'         => $u['age'] ?? null,
            'playcount'   => (int) ($u['playcount'] ?? 0),
            'artist_count'=> (int) ($u['artist_count'] ?? 0),
            'track_count' => (int) ($u['track_count'] ?? 0),
            'album_count' => (int) ($u['album_count'] ?? 0),
            'image'       => $this->bestImage($u['image'] ?? []),
            'registered'  => $u['registered']['#text'] ?? null,
        ];
    }

    /**
     * Parse album.getInfo response into a clean array.
     */
    public function parseAlbumInfo(array $raw): array
    {
        $a    = $raw['album'] ?? [];
        $tags = $a['tags']['tag'] ?? [];
        if (!is_array($tags)) {
            $tags = [];
        }

        return [
            'name'          => $a['name'] ?? '',
            'artist'        => $a['artist'] ?? '',
            'url'           => $a['url'] ?? null,
            'image'         => $this->bestImage($a['image'] ?? []),
            'listeners'     => (int) ($a['listeners'] ?? 0),
            'playcount'     => (int) ($a['playcount'] ?? 0),
            'userplaycount' => isset($a['userplaycount']) ? (int) $a['userplaycount'] : null,
            'tags'          => array_slice(
                array_map(
                    fn($t) => ['name' => $t['name'] ?? '', 'url' => $t['url'] ?? null],
                    $tags
                ),
                0, 5
            ),
            'wiki_summary'  => isset($a['wiki']['summary'])
                ? trim(strip_tags(explode('<a', $a['wiki']['summary'])[0] ?? ''))
                : null,
        ];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Make an authenticated GET request to the Last.fm API.
     *
     * @throws \RuntimeException on HTTP or API-level errors
     */
    private function request(array $params): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Last.fm API key not configured.');
        }

        $params = array_merge($params, [
            'api_key' => $this->apiKey,
            'format'  => 'json',
        ]);

        try {
            $response = $this->client->get(self::BASE_URL, ['query' => $params]);
            $body = json_decode((string) $response->getBody(), true);

            if (isset($body['error'])) {
                throw new \RuntimeException(
                    "Last.fm API error {$body['error']}: " . ($body['message'] ?? 'Unknown error')
                );
            }

            return $body;
        } catch (GuzzleException $e) {
            $this->logger->error('LastFm: HTTP request failed', [
                'message' => $e->getMessage(),
                'method'  => $params['method'] ?? 'unknown',
            ]);
            throw new \RuntimeException('Last.fm request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Return cached result, or fetch and cache if missing.
     *
     * Delegates to ResilientCall so every Last.fm call in this service falls back
     * to its last known answer when the API is down, instead of propagating the
     * RuntimeException that request() throws. Note there is no cache->get() left
     * here: it deletes the expired entry, which is the very copy we want to keep.
     */
    private function cachedCall(string $key, int $ttl, callable $fetch): array
    {
        return $this->cachedCallResilient($key, $ttl, $fetch)['data'];
    }

    /**
     * The same call, without throwing away the two flags ResilientCall computes.
     *
     * Being private and shared by every public method is what makes this cheap:
     * each `…Resilient()` sibling is its flat twin with this call swapped in.
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    private function cachedCallResilient(string $key, int $ttl, callable $fetch): array
    {
        return $this->resilient->around($key, self::CACHE_NS, $ttl, $fetch);
    }

    /**
     * Pick the largest image URL from a Last.fm image array.
     * Last.fm image arrays are ordered: small, medium, large, extralarge, mega.
     */
    private function bestImage(array $images): ?string
    {
        if (empty($images)) return null;

        // Prefer extralarge or mega, fall back to whatever is available
        $preferred = ['mega', 'extralarge', 'large', 'medium', 'small'];
        $indexed = [];
        foreach ($images as $img) {
            $indexed[$img['size'] ?? ''] = $img['#text'] ?? '';
        }

        foreach ($preferred as $size) {
            if (!empty($indexed[$size])) return $indexed[$size];
        }

        // Return last non-empty
        foreach (array_reverse($images) as $img) {
            if (!empty($img['#text'])) return $img['#text'];
        }

        return null;
    }
}
