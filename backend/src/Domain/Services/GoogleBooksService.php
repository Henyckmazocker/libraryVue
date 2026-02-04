<?php

declare(strict_types=1);

namespace App\Domain\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Service for interacting with Google Books API
 * Provides enrichment data (descriptions, HD covers, categories)
 */
class GoogleBooksService
{
    private Client $client;
    private LoggerInterface $logger;
    private ?string $apiKey;
    private const BASE_URL = 'https://www.googleapis.com/books/v1';

    public function __construct(LoggerInterface $logger)
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
     * @param string $query Search query
     * @param int $maxResults Maximum number of results (max 40)
     * @param array $filters Additional filters (langRestrict, printType, orderBy, raw)
     * @return array Array of books
     */
    public function searchBooks(string $query, int $maxResults = 10, array $filters = []): array
    {
        try {
            $this->logger->info("GoogleBooks: Searching books", [
                'query' => $query,
                'maxResults' => $maxResults
            ]);

            $queryParams = [
                'q' => "intitle:{$query}",
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
                $this->logger->info("GoogleBooks: Returning raw items", ['count' => count($items)]);
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

            $this->logger->info("GoogleBooks: Found books", ['count' => count($books)]);
            return $books;

        } catch (GuzzleException $e) {
            $this->logger->error("GoogleBooks search failed", [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
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
            $this->logger->info("GoogleBooks: Searching by ISBN", [
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
                return null;
            }

            return $this->transformBook($items[0]);

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
            'page_count' => $volumeInfo['pageCount'] ?? null,
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
