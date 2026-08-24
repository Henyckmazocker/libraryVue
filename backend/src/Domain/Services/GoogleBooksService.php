<?php

declare(strict_types=1);

namespace App\Domain\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\ResilientCall;

/**
 * Service for interacting with Google Books API
 * Provides enrichment data (descriptions, HD covers, categories)
 */
class GoogleBooksService
{
    private Client $client;
    private LoggerInterface $logger;
    private CacheService $cache;
    private ResilientCall $resilient;
    private ?string $apiKey;
    private const BASE_URL = 'https://www.googleapis.com/books/v1';
    private const CACHE_TTL_ISBN = 604800; // 7 days
    private const CACHE_TTL_SEARCH = 21600; // 6 hours

    public function __construct(CacheService $cache, ResilientCall $resilient, LoggerInterface $logger)
    {
        // Get API key from environment
        $this->apiKey = $_ENV['GOOGLE_BOOKS_API_KEY'] ?? null;
        
        $this->client = new Client([
            'timeout' => 5.0, // Reduced from 10s to 5s
            'connect_timeout' => 2.0, // Max 2s to establish connection
            'headers' => [
                'User-Agent' => 'LibraryVue/1.0 (Educational Project)',
                'Accept' => 'application/json'
            ]
        ]);
        $this->cache = $cache;
        $this->resilient = $resilient;
        $this->logger = $logger;
        
        if (empty($this->apiKey)) {
            $this->logger->warning("GoogleBooks: No API key configured. Requests will have lower quota limits.");
        } else {
            $this->logger->info("GoogleBooks: API key configured", [
                'key_prefix' => substr($this->apiKey, 0, 10) . '...'
            ]);
        }
    }

    /**
     * Search books by title
     *
     * Keeps the historic contract: an empty array when the search cannot be
     * answered at all. Callers that need to know whether the answer came from a
     * degraded cache should use searchBooksResilient() instead.
     *
     * @param string $query Search query
     * @param int $maxResults Maximum number of results (max 40)
     * @param array $filters Additional filters (langRestrict, printType, orderBy, raw)
     * @return array Array of books
     */
    public function searchBooks(string $query, int $maxResults = 10, array $filters = []): array
    {
        return $this->searchBooksResilient($query, $maxResults, $filters)['data'];
    }

    /**
     * Search books, falling back to the last known results when the API fails
     *
     * The whole strategy (exact match plus broad search) is cached as one entry:
     * either of the two requests failing degrades the search as a whole, which is
     * what the caller cares about.
     *
     * @param string $query Search query
     * @param int $maxResults Maximum number of results (max 40)
     * @param array $filters Additional filters (langRestrict, printType, orderBy, raw)
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function searchBooksResilient(string $query, int $maxResults = 10, array $filters = []): array
    {
        $cacheKey = 'search_' . md5(json_encode([$query, $maxResults, $filters]));

        try {
            return $this->resilient->around(
                $cacheKey,
                'googlebooks',
                self::CACHE_TTL_SEARCH,
                fn() => $this->runSearchStrategy($query, $maxResults, $filters)
            );
        } catch (GuzzleException $e) {
            $this->logger->error("GoogleBooks search failed with no cache to fall back on", [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return ['data' => [], 'stale' => false, 'cached_at' => null];
        }
    }

    /**
     * Exact title match first, complemented with a broad search when it falls short
     *
     * @throws GuzzleException Propagated on purpose: ResilientCall decides what to do with a failure
     */
    private function runSearchStrategy(string $query, int $maxResults, array $filters): array
    {
        $this->logger->info("GoogleBooks: Searching books", [
            'query' => $query,
            'maxResults' => $maxResults
        ]);

        // Strategy: Try exact title match first, fallback to broad search
        $exactResults = $this->performSearch("intitle:\"{$query}\"", $maxResults, $filters);
        
        // If exact search found enough results, use only them
        if (count($exactResults) >= $maxResults) {
            $this->logger->info("GoogleBooks: Using exact match results", ['count' => count($exactResults)]);
            return $exactResults;
        }
        
        // Otherwise, keep exact results and complement with broad search
        $this->logger->info("GoogleBooks: Complementing exact results with broad search", [
            'exact_count' => count($exactResults)
        ]);
        
        $remaining = $maxResults - count($exactResults);
        $broadResults = $this->performSearch("intitle:{$query}", $remaining, $filters);
        
        // Merge results, exact matches first
        $merged = $exactResults;
        $seenIds = [];
        foreach ($exactResults as $result) {
            $id = is_array($result) ? ($result['id'] ?? uniqid()) : uniqid();
            $seenIds[$id] = true;
        }
        
        foreach ($broadResults as $result) {
            $id = is_array($result) ? ($result['id'] ?? uniqid()) : uniqid();
            if (!isset($seenIds[$id])) {
                $merged[] = $result;
                $seenIds[$id] = true;
            }
        }
        
        $this->logger->info("GoogleBooks: Returning merged results", [
            'exact' => count($exactResults),
            'broad' => count($broadResults),
            'merged' => count($merged)
        ]);
        
        return $merged;
    }
    
    /**
     * Perform actual search against Google Books API
     *
     * @throws GuzzleException Deliberately not swallowed: returning [] here would
     *         hide the failure from ResilientCall, which would then cache the
     *         empty list as if it were a good answer.
     */
    private function performSearch(string $queryString, int $maxResults, array $filters): array
    {
        $queryParams = [
            'q' => $queryString,
            'maxResults' => min($maxResults, 40), // Google Books max is 40
            'printType' => $filters['printType'] ?? 'books',
            'orderBy' => $filters['orderBy'] ?? 'relevance',
        ];

        // Add optional filters
        if (!empty($filters['langRestrict'])) {
            $queryParams['langRestrict'] = $filters['langRestrict'];
        }

        // Add API key if configured
        $queryParams = $this->addApiKey($queryParams);

        $response = $this->client->get(self::BASE_URL . '/volumes', [
            'query' => $queryParams
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $items = $data['items'] ?? [];

        // Return raw items if requested
        if (!empty($filters['raw'])) {
            return $items;
        }

        // Transform books for legacy compatibility
        $books = [];
        foreach ($items as $item) {
            $book = $this->transformBook($item);
            if ($book) {
                $books[] = $book;
            }
        }

        return $books;
    }

    /**
     * Get book details by volume ID
     *
     * @param string $volumeId Google Books volume ID
     * @return array|null Book details or null if not found
     */
    public function getVolume(string $volumeId): ?array
    {
        try {
            $this->logger->info("GoogleBooks: Getting volume", ['volume_id' => $volumeId]);

            $queryParams = $this->addApiKey([]);
            $response = $this->client->get(self::BASE_URL . "/volumes/{$volumeId}", [
                'query' => $queryParams
            ]);
            $data = json_decode($response->getBody()->getContents(), true);

            return $this->transformBook($data);

        } catch (GuzzleException $e) {
            $this->logger->warning("GoogleBooks get volume failed", [
                'volume_id' => $volumeId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Search book by ISBN
     *
     * @param string $isbn ISBN-10 or ISBN-13
     * @return array|null Book details or null if not found
     */
    public function searchByISBN(string $isbn): ?array
    {
        try {
            // Generate cache key
            $cacheKey = "isbn_{$isbn}";
            
            // Try to get from cache
            $cached = $this->cache->get($cacheKey, 'googlebooks');
            if ($cached !== null) {
                $this->logger->info("GoogleBooks: Returning cached ISBN search", ['isbn' => $isbn]);
                return $cached;
            }
            
            $this->logger->info("GoogleBooks: Searching by ISBN from API", [
                'isbn' => $isbn,
                'has_api_key' => !empty($this->apiKey)
            ]);

            $queryParams = $this->addApiKey([
                'q' => "isbn:{$isbn}",
                'maxResults' => 1
            ]);

            $this->logger->debug("GoogleBooks: Request params", [
                'params' => $queryParams,
                'url' => self::BASE_URL . '/volumes'
            ]);

            $response = $this->client->get(self::BASE_URL . '/volumes', [
                'query' => $queryParams
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $items = $data['items'] ?? [];

            if (empty($items)) {
                // Cache null result for shorter time (1 hour) to avoid repeated failed lookups
                $this->cache->set($cacheKey, null, 3600, 'googlebooks');
                return null;
            }

            $result = $this->transformBook($items[0]);
            
            // Store in cache for 7 days
            $this->cache->set($cacheKey, $result, self::CACHE_TTL_ISBN, 'googlebooks');

            return $result;

        } catch (GuzzleException $e) {
            $this->logger->warning("GoogleBooks search by ISBN failed", [
                'isbn' => $isbn,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Enrich work with Google Books data
     * Provides better descriptions, HD covers, and categories
     *
     * @param array $work Work data from OpenLibrary
     * @return array Work enriched with Google Books data
     */
    public function enrichWork(array $work): array
    {
        // Try to find in Google Books by title
        $query = $work['title'];
        if (!empty($work['authors'])) {
            $firstAuthor = $work['authors'][0];
            
            // Extract author name properly
            if (is_array($firstAuthor)) {
                $authorName = $firstAuthor['name'] ?? '';
            } else {
                $authorName = (string)$firstAuthor;
            }
            
            if (!empty($authorName)) {
                $query .= " {$authorName}";
            }
        }

        $books = $this->searchBooks($query, 1);
        
        if (empty($books)) {
            return $work;
        }

        $googleBook = $books[0];

        // Enrich with Google Books data
        if (!empty($googleBook['description']) && empty($work['description'])) {
            $work['description'] = $googleBook['description'];
        }

        if (!empty($googleBook['cover_url_large'])) {
            $work['cover_url_hd'] = $googleBook['cover_url_large'];
        }

        if (!empty($googleBook['categories'])) {
            $work['google_categories'] = $googleBook['categories'];
        }

        $work['enriched_with_google'] = true;

        return $work;
    }

    /**
     * Transform Google Books item to normalized format
     *
     * @param array $item Raw Google Books item
     * @return array|null Transformed book data
     */
    private function transformBook(array $item): ?array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];

        if (empty($volumeInfo)) {
            return null;
        }

        // Extract ISBNs
        $isbn13 = null;
        $isbn10 = null;
        if (!empty($volumeInfo['industryIdentifiers'])) {
            foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
                if ($identifier['type'] === 'ISBN_13') {
                    $isbn13 = $identifier['identifier'];
                } elseif ($identifier['type'] === 'ISBN_10') {
                    $isbn10 = $identifier['identifier'];
                }
            }
        }

        // Extract covers
        $imageLinks = $volumeInfo['imageLinks'] ?? [];
        $coverSmall = $this->httpsUrl($imageLinks['thumbnail'] ?? null);
        $coverMedium = $this->httpsUrl($imageLinks['small'] ?? $imageLinks['medium'] ?? null);
        $coverLarge = $this->httpsUrl($imageLinks['large'] ?? $imageLinks['extraLarge'] ?? null);

        return [
            'google_books_id' => $item['id'] ?? null,
            'isbn_13' => $isbn13,
            'isbn_10' => $isbn10,
            'title' => $volumeInfo['title'] ?? 'Unknown Title',
            'subtitle' => $volumeInfo['subtitle'] ?? null,
            'authors' => $volumeInfo['authors'] ?? [],
            'publisher' => $volumeInfo['publisher'] ?? null,
            'published_date' => $volumeInfo['publishedDate'] ?? null,
            'description' => $volumeInfo['description'] ?? null,
            'page_count' => !empty($volumeInfo['pageCount']) ? (int)$volumeInfo['pageCount'] : null,
            'categories' => $volumeInfo['categories'] ?? [],
            'language' => $volumeInfo['language'] ?? null,
            'cover_url_small' => $coverSmall,
            'cover_url_medium' => $coverMedium,
            'cover_url_large' => $coverLarge,
            'preview_link' => $volumeInfo['previewLink'] ?? null,
            'info_link' => $volumeInfo['infoLink'] ?? null,
            'average_rating' => $volumeInfo['averageRating'] ?? null,
            'ratings_count' => $volumeInfo['ratingsCount'] ?? null,
        ];
    }

    /**
     * Ensure URL uses HTTPS
     *
     * @param string|null $url URL
     * @return string|null HTTPS URL
     */
    private function httpsUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        return str_replace('http:', 'https:', $url);
    }

    /**
     * Add API key to query parameters if configured
     *
     * @param array $params Query parameters
     * @return array Parameters with API key if available
     */
    private function addApiKey(array $params): array
    {
        if (!empty($this->apiKey)) {
            $params['key'] = $this->apiKey;
        }
        return $params;
    }
}
