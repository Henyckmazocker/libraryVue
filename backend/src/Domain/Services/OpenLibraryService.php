<?php

declare(strict_types=1);

namespace App\Domain\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\ResilientCall;

/**
 * Service for interacting with OpenLibrary API
 * Handles work and edition searches, data retrieval, and aggregation
 */
class OpenLibraryService
{
    private Client $client;
    private LoggerInterface $logger;
    private CacheService $cache;
    private ResilientCall $resilient;
    private const BASE_URL = 'https://openlibrary.org';
    private const COVERS_URL = 'https://covers.openlibrary.org/b';
    private const CACHE_TTL_EDITIONS = 86400; // 24 hours
    private const CACHE_TTL_SEARCH = 21600; // 6 hours

    public function __construct(CacheService $cache, ResilientCall $resilient, LoggerInterface $logger)
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
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
    }

    /**
     * Search for works by title
     * Returns works already grouped by OpenLibrary
     *
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @return array Array of works with basic info
     */
    public function searchWorks(string $query, int $limit = 20): array
    {
        return $this->searchWorksResilient($query, $limit)['data'];
    }

    /**
     * Search works, falling back to the last known results when the API fails
     *
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @return array{data: array, stale: bool, cached_at: int|null}
     */
    public function searchWorksResilient(string $query, int $limit = 20): array
    {
        $cacheKey = 'search_' . md5(json_encode([$query, $limit]));

        try {
            return $this->resilient->around(
                $cacheKey,
                'openlibrary',
                self::CACHE_TTL_SEARCH,
                fn() => $this->runWorkSearch($query, $limit)
            );
        } catch (GuzzleException $e) {
            $this->logger->error("OpenLibrary search failed with no cache to fall back on", [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return ['data' => [], 'stale' => false, 'cached_at' => null];
        }
    }

    /**
     * @throws GuzzleException Propagated on purpose: ResilientCall decides what to do with a failure
     */
    private function runWorkSearch(string $query, int $limit): array
    {
        $this->logger->info("OpenLibrary: Searching works", ['query' => $query, 'limit' => $limit]);

        // Fetch more results than needed to filter and sort properly
        $fetchLimit = min($limit * 3, 100);

        $response = $this->client->get('/search.json', [
            'query' => [
                'q' => $query, // Use general search 'q' instead of 'title' for better results
                'limit' => $fetchLimit,
                'fields' => 'key,title,author_name,first_publish_year,edition_count,cover_i,subject,language,isbn,has_fulltext,ratings_average'
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $docs = $data['docs'] ?? [];

        $works = [];
        foreach ($docs as $doc) {
            $work = $this->transformWorkFromSearch($doc);
            if ($work) {
                $works[] = $work;
            }
        }

        $this->logger->info("OpenLibrary: Found works", ['count' => count($works)]);
        return $works;
    }

    /**
     * Get detailed information about a specific work
     *
     * @param string $workKey Work key (e.g., "OL82563W" or "/works/OL82563W")
     * @return array|null Work details or null if not found
     */
    public function getWork(string $workKey): ?array
    {
        try {
            // Normalize work key
            $workKey = $this->normalizeWorkKey($workKey);
            
            $this->logger->info("OpenLibrary: Getting work details", ['work_key' => $workKey]);

            $response = $this->client->get("/works/{$workKey}.json");
            $data = json_decode($response->getBody()->getContents(), true);

            return $this->transformWorkDetails($data);

        } catch (GuzzleException $e) {
            $this->logger->error("OpenLibrary get work failed", [
                'work_key' => $workKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all editions for a specific work
     *
     * @param string $workKey Work key
     * @param int $limit Maximum number of editions
     * @param int $offset Offset for pagination
     * @return array Array with editions and metadata
     */
    public function getWorkEditions(string $workKey, int $limit = 50, int $offset = 0): array
    {
        try {
            $workKey = $this->normalizeWorkKey($workKey);
            
            // Generate cache key
            $cacheKey = "work_editions_{$workKey}_limit{$limit}_offset{$offset}";
            
            // Try to get from cache
            $cached = $this->cache->get($cacheKey, 'openlibrary');
            if ($cached !== null) {
                $this->logger->info("OpenLibrary: Returning cached work editions", [
                    'work_key' => $workKey,
                    'limit' => $limit,
                    'offset' => $offset
                ]);
                return $cached;
            }
            
            $this->logger->info("OpenLibrary: Fetching work editions from API", [
                'work_key' => $workKey,
                'limit' => $limit,
                'offset' => $offset
            ]);

            $response = $this->client->get("/works/{$workKey}/editions.json", [
                'query' => [
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $entries = $data['entries'] ?? [];

            $editions = [];
            foreach ($entries as $entry) {
                $edition = $this->transformEdition($entry);
                if ($edition) {
                    $editions[] = $edition;
                }
            }

            $result = [
                'editions' => $editions,
                'total' => $data['size'] ?? count($editions),
                'offset' => $offset,
                'limit' => $limit
            ];
            
            // Store in cache for 24 hours
            $this->cache->set($cacheKey, $result, self::CACHE_TTL_EDITIONS, 'openlibrary');

            return $result;

        } catch (GuzzleException $e) {
            $this->logger->error("OpenLibrary get editions failed", [
                'work_key' => $workKey,
                'error' => $e->getMessage()
            ]);
            return ['editions' => [], 'total' => 0];
        }
    }

    /**
     * Get edition details by ISBN
     *
     * @param string $isbn ISBN-10 or ISBN-13
     * @return array|null Edition details or null if not found
     */
    public function getEditionByISBN(string $isbn): ?array
    {
        try {
            $this->logger->info("OpenLibrary: Getting edition by ISBN", ['isbn' => $isbn]);

            // A 404 here means "no such ISBN", not "Open Library is down", so
            // ResilientCall rethrows it and the catch below turns it into null.
            return $this->resilient->around(
                "isbn_{$isbn}",
                'openlibrary',
                self::CACHE_TTL_EDITIONS,
                function () use ($isbn) {
                    $response = $this->client->get("/isbn/{$isbn}.json");
                    $data = json_decode($response->getBody()->getContents(), true);

                    return $this->transformEdition($data);
                }
            )['data'];

        } catch (GuzzleException $e) {
            $this->logger->warning("OpenLibrary get edition by ISBN failed", [
                'isbn' => $isbn,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Transform work data from search results
     *
     * @param array $doc Raw work document from search
     * @return array|null Transformed work data
     */
    private function transformWorkFromSearch(array $doc): ?array
    {
        // Skip if no key (invalid work)
        if (empty($doc['key'])) {
            return null;
        }

        $workKey = str_replace('/works/', '', $doc['key']);

        return [
            'work_key' => $workKey,
            'title' => $doc['title'] ?? 'Unknown Title',
            'authors' => $doc['author_name'] ?? [],
            'first_publish_year' => $doc['first_publish_year'] ?? null,
            'editions_count' => $doc['edition_count'] ?? 0,
            'cover_id' => $doc['cover_i'] ?? null,
            'cover_url' => $this->getCoverUrl($doc['cover_i'] ?? null, 'M'),
            'subjects' => array_slice($doc['subject'] ?? [], 0, 10), // Limit subjects
            'languages' => $doc['language'] ?? [],
            'sample_isbn' => $doc['isbn'][0] ?? null, // First ISBN for reference
        ];
    }

    /**
     * Transform detailed work data
     *
     * @param array $data Raw work data
     * @return array Transformed work data
     */
    private function transformWorkDetails(array $data): array
    {
        $workKey = str_replace('/works/', '', $data['key'] ?? '');

        // Extract authors
        $authors = [];
        if (!empty($data['authors'])) {
            foreach ($data['authors'] as $author) {
                if (isset($author['author']['key'])) {
                    $authors[] = [
                        'key' => str_replace('/authors/', '', $author['author']['key']),
                        'name' => null // Will need separate call to get name
                    ];
                }
            }
        }

        // Extract description
        $description = null;
        if (isset($data['description'])) {
            $description = is_array($data['description']) 
                ? ($data['description']['value'] ?? null)
                : $data['description'];
        }

        return [
            'work_key' => $workKey,
            'title' => $data['title'] ?? 'Unknown Title',
            'subtitle' => $data['subtitle'] ?? null,
            'authors' => $authors,
            'description' => $description,
            'subjects' => $data['subjects'] ?? [],
            'first_publish_date' => $data['first_publish_date'] ?? null,
            'covers' => $data['covers'] ?? [],
            'cover_url' => $this->getCoverUrl($data['covers'][0] ?? null, 'L'),
        ];
    }

    /**
     * Transform edition data
     *
     * @param array $data Raw edition data
     * @return array|null Transformed edition data
     */
    private function transformEdition(array $data): ?array
    {
        // Skip if no key
        if (empty($data['key'])) {
            return null;
        }

        $editionKey = str_replace('/books/', '', $data['key']);

        // Get work keys
        $workKeys = [];
        if (!empty($data['works'])) {
            foreach ($data['works'] as $work) {
                if (isset($work['key'])) {
                    $workKeys[] = str_replace('/works/', '', $work['key']);
                }
            }
        }

        // Extract ISBNs
        $isbn13 = null;
        $isbn10 = null;
        if (!empty($data['isbn_13'])) {
            $isbn13 = $data['isbn_13'][0];
        }
        if (!empty($data['isbn_10'])) {
            $isbn10 = $data['isbn_10'][0];
        }

        return [
            'edition_key' => $editionKey,
            'work_keys' => $workKeys,
            'isbn_13' => $isbn13,
            'isbn_10' => $isbn10,
            'title' => $data['title'] ?? 'Unknown Title',
            'subtitle' => $data['subtitle'] ?? null,
            'publishers' => $data['publishers'] ?? [],
            'publish_date' => $data['publish_date'] ?? null,
            'publish_year' => $this->extractYear($data['publish_date'] ?? null),
            'number_of_pages' => $data['number_of_pages'] ?? (isset($data['pagination']) ? (int) filter_var($data['pagination'], FILTER_SANITIZE_NUMBER_INT) ?: null : null),
            'physical_format' => $data['physical_format'] ?? null,
            'languages' => $data['languages'] ?? [],
            'covers' => $data['covers'] ?? [],
            'cover_url' => $this->getCoverUrl($data['covers'][0] ?? null, 'M'),
        ];
    }

    /**
     * Get cover URL from cover ID
     *
     * @param int|null $coverId Cover ID
     * @param string $size Size (S, M, L)
     * @return string|null Cover URL
     */
    private function getCoverUrl(?int $coverId, string $size = 'M'): ?string
    {
        if (!$coverId) {
            return null;
        }

        return self::COVERS_URL . "/id/{$coverId}-{$size}.jpg";
    }

    /**
     * Get full book data by ISBN using the OpenLibrary Books API.
     * Also fetches the edition to extract the work key.
     *
     * Returns an array with keys: edition (from /isbn/{isbn}.json) and book (from /api/books).
     *
     * @param string $isbn ISBN-10 or ISBN-13
     * @return array|null Combined data or null if not found
     */
    public function getBookByISBN(string $isbn): ?array
    {
        $cacheKey = 'book_isbn_' . $isbn;
        $cached   = $this->cache->get($cacheKey, 'openlibrary');
        if ($cached !== null) {
            return $cached;
        }

        $result = [];

        // 1. Edition data (work_key extraction)
        try {
            $editionResponse = $this->client->get("/isbn/{$isbn}.json");
            $result['edition'] = json_decode($editionResponse->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            $this->logger->debug('OpenLibrary: could not fetch edition for ISBN', [
                'isbn'  => $isbn,
                'error' => $e->getMessage(),
            ]);
            $result['edition'] = [];
        }

        // 2. Full book metadata via Books API
        try {
            $booksClient  = new \GuzzleHttp\Client([
                'timeout'         => 5.0,
                'connect_timeout' => 2.0,
                'headers'         => [
                    'User-Agent' => 'LibraryVue/1.0 (Educational Project)',
                    'Accept'     => 'application/json',
                ],
            ]);
            $bookResponse = $booksClient->get('https://openlibrary.org/api/books', [
                'query' => [
                    'bibkeys' => "ISBN:{$isbn}",
                    'format'  => 'json',
                    'jscmd'   => 'data',
                ],
            ]);
            $bookData     = json_decode($bookResponse->getBody()->getContents(), true) ?? [];
            $result['book'] = $bookData["ISBN:{$isbn}"] ?? null;
        } catch (GuzzleException $e) {
            $this->logger->error('OpenLibrary: Books API failed for ISBN', [
                'isbn'  => $isbn,
                'error' => $e->getMessage(),
            ]);
            $result['book'] = null;
        }

        if (empty($result['edition']) && $result['book'] === null) {
            return null;
        }

        $this->cache->set($cacheKey, $result, self::CACHE_TTL_EDITIONS, 'openlibrary');
        return $result;
    }

    /**
     * Normalize work key (remove /works/ prefix if present)
     *
     * @param string $workKey Work key
     * @return string Normalized work key
     */
    private function normalizeWorkKey(string $workKey): string
    {
        return str_replace('/works/', '', $workKey);
    }

    /**
     * Extract year from publish date string
     *
     * @param string|null $publishDate Publish date
     * @return int|null Year
     */
    private function extractYear(?string $publishDate): ?int
    {
        if (!$publishDate) {
            return null;
        }

        // Try to extract 4-digit year
        if (preg_match('/\d{4}/', $publishDate, $matches)) {
            return (int)$matches[0];
        }

        return null;
    }
}
