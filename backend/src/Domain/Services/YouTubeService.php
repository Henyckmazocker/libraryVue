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
 * Service for interacting with YouTube Data API v3
 *
 * Provides video search and metadata retrieval.
 * Uses API Key auth (no OAuth needed for public data).
 * Cache namespace: 'youtube'
 */
class YouTubeService
{
    private Client $client;
    private ?string $apiKey;

    private const BASE_URL      = 'https://www.googleapis.com/youtube/v3';
    private const CACHE_SEARCH  = 1800;  // 30 min for search results
    private const CACHE_DETAILS = 86400; // 24 h for video details

    public function __construct(
        private readonly CacheService $cache,
        private readonly ResilientCall $resilient,
        private readonly LoggerInterface $logger,
        HttpClientFactory $http
    ) {
        $this->apiKey = $_ENV['YOUTUBE_API_KEY'] ?? null;

        $this->client = $http->create(HttpClientFactory::PROFILE_WEB, 'LibraryVue/1.0 (Educational Project)', [
            'timeout'         => 10.0,
            'connect_timeout' => 3.0,
            'headers'         => ['Accept' => 'application/json'],
        ]);

        if (empty($this->apiKey)) {
            $this->logger->warning('YouTube: No API key configured (YOUTUBE_API_KEY missing).');
        }
    }

    /**
     * Search YouTube videos by query string
     *
     * @param string $query
     * @param int    $maxResults 1-50
     * @return array Normalized video objects
     */
    public function searchVideos(string $query, int $maxResults = 10): array
    {
        return $this->searchVideosResilient($query, $maxResults)['data'];
    }

    /**
     * Search videos, keeping the freshness of what is being served
     *
     * Same fallback behaviour as searchVideos() -- an empty list rather than an
     * exception when there is nothing cached -- but without throwing away the
     * two flags ResilientCall computes. Note the two empty-list exits are never
     * stale: a missing API key and an unreachable API with no cached copy are
     * both "no results", not "old results".
     *
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function searchVideosResilient(string $query, int $maxResults = 10): array
    {
        if (empty($this->apiKey)) {
            return ['data' => [], 'stale' => false, 'cached_at' => null];
        }

        // No cache->get() here on purpose: it deletes the entry when it finds it
        // expired, which is exactly the copy ResilientCall needs as a fallback.
        $cacheKey = 'search_' . md5($query . '_' . $maxResults);

        try {
            return $this->resilient->around(
                $cacheKey,
                'youtube',
                self::CACHE_SEARCH,
                function () use ($query, $maxResults) {
                    $response = $this->client->get(self::BASE_URL . '/search', [
                        'query' => [
                            'key'        => $this->apiKey,
                            'q'          => $query,
                            'part'       => 'snippet',
                            'type'       => 'video',
                            'maxResults' => min(50, max(1, $maxResults)),
                        ],
                    ]);

                    $data  = json_decode($response->getBody()->getContents(), true);
                    $items = $data['items'] ?? [];

                    return array_map(fn($item) => $this->normalizeSearchItem($item), $items);
                }
            );
        } catch (GuzzleException $e) {
            $this->logger->error('YouTube search failed with no cache to fall back on', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return ['data' => [], 'stale' => false, 'cached_at' => null];
        }
    }

    /**
     * Get detailed metadata for a single video by YouTube ID
     *
     * @param string $videoId 11-char YouTube video ID
     * @return array|null Normalized video data, or null on error
     */
    public function getVideoDetails(string $videoId): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $cacheKey = 'details_' . $videoId;
        $cached   = $this->cache->get($cacheKey, 'youtube');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->client->get(self::BASE_URL . '/videos', [
                'query' => [
                    'key'  => $this->apiKey,
                    'id'   => $videoId,
                    'part' => 'snippet,contentDetails,statistics',
                ],
            ]);

            $data  = json_decode($response->getBody()->getContents(), true);
            $items = $data['items'] ?? [];

            if (empty($items)) {
                return null;
            }

            $result = $this->normalizeDetailItem($items[0]);

            $this->cache->set($cacheKey, $result, self::CACHE_DETAILS, 'youtube');

            return $result;
        } catch (GuzzleException $e) {
            $this->logger->error('YouTube getVideoDetails failed', [
                'videoId' => $videoId,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function normalizeSearchItem(array $item): array
    {
        $snippet   = $item['snippet']   ?? [];
        $videoId   = $item['id']['videoId'] ?? '';
        $thumbnails = $snippet['thumbnails'] ?? [];

        return [
            'youtube_id'   => $videoId,
            'title'        => $snippet['title']       ?? '',
            'channel_name' => $snippet['channelTitle'] ?? '',
            'channel_id'   => $snippet['channelId']   ?? '',
            'cover_url'    => $this->getBestThumbnail($thumbnails),
            'published_at' => $snippet['publishedAt'] ?? null,
            'description'  => $snippet['description'] ?? '',
        ];
    }

    private function normalizeDetailItem(array $item): array
    {
        $snippet        = $item['snippet']        ?? [];
        $contentDetails = $item['contentDetails'] ?? [];
        $statistics     = $item['statistics']     ?? [];
        $thumbnails     = $snippet['thumbnails']  ?? [];
        $duration       = $contentDetails['duration'] ?? null;
        $seconds        = $duration ? $this->isoDurationToSeconds($duration) : null;

        return [
            'youtube_id'       => $item['id'] ?? '',
            'title'            => $snippet['title']        ?? '',
            'channel_name'     => $snippet['channelTitle'] ?? '',
            'channel_id'       => $snippet['channelId']   ?? '',
            'cover_url'        => $this->getBestThumbnail($thumbnails),
            // Legible, no el ISO 8601 que manda YouTube: esto se pinta en la
            // ficha y se guarda tal cual en `videos.duration` al dar de alta.
            'duration'         => $seconds !== null ? $this->formatDuration($seconds) : null,
            'duration_seconds' => $seconds,
            'view_count'       => isset($statistics['viewCount'])  ? (int)$statistics['viewCount']  : null,
            'like_count'       => isset($statistics['likeCount'])  ? (int)$statistics['likeCount']  : null,
            'published_at'     => $snippet['publishedAt'] ?? null,
            'description'      => $snippet['description'] ?? '',
            'categories'       => $snippet['tags']        ?? [],
        ];
    }

    /**
     * Pick the highest-quality thumbnail URL available
     */
    private function getBestThumbnail(array $thumbnails): string
    {
        foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $quality) {
            if (isset($thumbnails[$quality]['url'])) {
                return $thumbnails[$quality]['url'];
            }
        }
        return '';
    }

    /**
     * Format a duration in seconds the way YouTube shows it: m:ss, or h:mm:ss
     * once it reaches an hour.
     */
    private function formatDuration(int $seconds): string
    {
        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest    = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }

    /**
     * Convert ISO 8601 duration string (e.g. "PT4M13S") to total seconds
     */
    private function isoDurationToSeconds(string $duration): int
    {
        $seconds = 0;

        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches)) {
            $seconds  = (int)($matches[1] ?? 0) * 3600;
            $seconds += (int)($matches[2] ?? 0) * 60;
            $seconds += (int)($matches[3] ?? 0);
        }

        return $seconds;
    }
}
