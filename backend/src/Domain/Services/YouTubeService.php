<?php

declare(strict_types=1);

namespace App\Domain\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Cache\CacheService;

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
        private readonly LoggerInterface $logger
    ) {
        $this->apiKey = $_ENV['YOUTUBE_API_KEY'] ?? null;

        $this->client = new Client([
            'timeout'         => 10.0,
            'connect_timeout' => 3.0,
            'headers'         => [
                'Accept'     => 'application/json',
                'User-Agent' => 'LibraryVue/1.0 (Educational Project)',
            ],
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
        if (empty($this->apiKey)) {
            return [];
        }

        $cacheKey = 'search_' . md5($query . '_' . $maxResults);
        $cached   = $this->cache->get($cacheKey, 'youtube');
        if ($cached !== null) {
            return $cached;
        }

        try {
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

            $results = array_map(fn($item) => $this->normalizeSearchItem($item), $items);

            $this->cache->set($cacheKey, $results, self::CACHE_SEARCH, 'youtube');

            return $results;
        } catch (GuzzleException $e) {
            $this->logger->error('YouTube search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
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

        return [
            'youtube_id'       => $item['id'] ?? '',
            'title'            => $snippet['title']        ?? '',
            'channel_name'     => $snippet['channelTitle'] ?? '',
            'channel_id'       => $snippet['channelId']   ?? '',
            'cover_url'        => $this->getBestThumbnail($thumbnails),
            'duration'         => $duration,
            'duration_seconds' => $duration ? $this->isoDurationToSeconds($duration) : null,
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
