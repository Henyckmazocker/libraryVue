<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Repository\Book\EditionRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for searching and managing literary works
 * 
 * Search Strategy: Google Books primary, OpenLibrary for edition management
 * - searchWorks(): Uses Google Books API with client-side grouping by title+author
 * - getWorkEditions(): Uses OpenLibrary for detailed edition selection (not available in Google Books)
 * - getWorkDetails(): Uses OpenLibrary for work-level metadata
 */
class WorkSearchService
{
    private OpenLibraryService $openLibraryService;
    private GoogleBooksService $googleBooksService;
    private EditionRepositoryInterface $editionRepository;
    private LoggerInterface $logger;

    public function __construct(
        OpenLibraryService $openLibraryService,
        GoogleBooksService $googleBooksService,
        EditionRepositoryInterface $editionRepository,
        LoggerInterface $logger
    ) {
        $this->openLibraryService = $openLibraryService;
        $this->googleBooksService = $googleBooksService;
        $this->editionRepository = $editionRepository;
        $this->logger = $logger;
    }

    /**
     * Get OpenLibrary service
     */
    public function getOpenLibraryService(): OpenLibraryService
    {
        return $this->openLibraryService;
    }

    /**
     * Get Google Books service
     */
    public function getGoogleBooksService(): GoogleBooksService
    {
        return $this->googleBooksService;
    }

    /**
     * Get Edition repository
     */
    public function getEditionRepository(): EditionRepositoryInterface
    {
        return $this->editionRepository;
    }

    /**
     * Search for works by title (Google Books Strategy)
     * 
     * Strategy: Single Google Books API call with intelligent grouping
     * Google Books returns editions as separate volumes, we group them by work
     *
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @param bool $enrichWithGoogle Whether to include full Google Books data (always true now)
     * @return array Array of works with aggregated data
     */
    public function searchWorks(string $query, int $limit = 20, bool $enrichWithGoogle = true): array
    {
        $startTime = microtime(true);
        
        $this->logger->info("WorkSearchService: Searching works (Google Books strategy)", [
            'query' => $query,
            'limit' => $limit
        ]);

        // Search Google Books with intitle: for focused title search
        // maxResults=40 is the API limit, orderBy=relevance for best results
        $googleResults = $this->googleBooksService->searchBooks($query, 40, [
            'orderBy' => 'relevance',
            'raw' => true  // Get raw API response for grouping
        ]);
        
        $this->logger->info("WorkSearchService: Google Books search completed", [
            'found' => count($googleResults)
        ]);

        // Group volumes by work (title + author combination)
        $works = $this->groupVolumesByWork($googleResults);
        
        // Sort by relevance (Google's relevance + edition count)
        usort($works, function($a, $b) {
            // Primary: Google's position (already sorted by relevance)
            $positionDiff = ($a['google_position'] ?? 999) - ($b['google_position'] ?? 999);
            if ($positionDiff !== 0) {
                return $positionDiff;
            }
            // Secondary: Edition count (more editions = more popular)
            return ($b['editions_count'] ?? 0) - ($a['editions_count'] ?? 0);
        });
        
        // Limit to requested number of results
        $works = array_slice($works, 0, $limit);
        
        // Enrich works for display
        $enrichedWorks = array_map([$this, 'enrichWorkForDisplay'], $works);
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        $this->logger->info("WorkSearchService: Returning works", [
            'count' => count($enrichedWorks),
            'execution_time_ms' => $executionTime,
            'source' => 'google_books'
        ]);

        return $enrichedWorks;
    }

    /**
     * Group Google Books volumes by work (title + author)
     * Multiple editions of the same book are grouped together
     *
     * @param array $volumes Array of Google Books volumes
     * @return array Array of works with grouped editions
     */
    private function groupVolumesByWork(array $volumes): array
    {
        $works = [];
        $workKeys = [];
        
        foreach ($volumes as $index => $volume) {
            $volumeInfo = $volume['volumeInfo'] ?? [];
            $title = $volumeInfo['title'] ?? 'Unknown Title';
            $authors = $volumeInfo['authors'] ?? ['Unknown Author'];
            
            // Create a normalized key for grouping (lowercase, no special chars)
            $normalizedTitle = $this->normalizeForGrouping($title);
            $normalizedAuthor = $this->normalizeForGrouping($authors[0] ?? '');
            $workKey = $normalizedTitle . '|' . $normalizedAuthor;
            
            if (!isset($workKeys[$workKey])) {
                // First time seeing this work
                $workKeys[$workKey] = count($works);
                $isbn13 = $this->extractISBN($volumeInfo, 'ISBN_13');
                $isbn10 = $this->extractISBN($volumeInfo, 'ISBN_10');
                $coverUrl = $this->extractCoverUrl($volumeInfo);
                
                $works[] = [
                    // Core fields for frontend compatibility
                    'work_key' => 'google:' . ($volume['id'] ?? uniqid()),
                    'title' => $title,
                    'author' => implode(', ', $authors),
                    'authors' => $authors,
                    'authors_display' => implode(', ', $authors),
                    'first_publish_year' => $this->extractYearFromVolume($volumeInfo),
                    'editions_count' => 1,
                    
                    // Cover data
                    'cover_url' => $coverUrl,
                    'cover_id' => null, // Google Books doesn't use OpenLibrary IDs
                    'has_cover' => !empty($coverUrl),
                    
                    // Additional metadata
                    'description' => $volumeInfo['description'] ?? null,
                    'has_description' => !empty($volumeInfo['description']),
                    'subjects' => $volumeInfo['categories'] ?? [],
                    'languages' => !empty($volumeInfo['language']) ? [$volumeInfo['language']] : [],
                    
                    // ISBN data
                    'sample_isbn' => $isbn13 ?? $isbn10,
                    'isbn_13' => $isbn13,
                    'isbn_10' => $isbn10,
                    
                    // Publisher data
                    'publisher' => $volumeInfo['publisher'] ?? null,
                    
                    // Google Books specific
                    'google_id' => $volume['id'] ?? null,
                    'google_position' => $index,
                    'source' => 'google_books'
                ];
            } else {
                // This work already exists, increment edition count
                $workIndex = $workKeys[$workKey];
                $works[$workIndex]['editions_count']++;
                
                // Update with better data if available
                if (empty($works[$workIndex]['cover_url'])) {
                    $coverUrl = $this->extractCoverUrl($volumeInfo);
                    $works[$workIndex]['cover_url'] = $coverUrl;
                    $works[$workIndex]['has_cover'] = !empty($coverUrl);
                }
                if (empty($works[$workIndex]['description'])) {
                    $desc = $volumeInfo['description'] ?? null;
                    $works[$workIndex]['description'] = $desc;
                    $works[$workIndex]['has_description'] = !empty($desc);
                }
                if (empty($works[$workIndex]['sample_isbn'])) {
                    $isbn13 = $this->extractISBN($volumeInfo, 'ISBN_13');
                    $isbn10 = $this->extractISBN($volumeInfo, 'ISBN_10');
                    $works[$workIndex]['sample_isbn'] = $isbn13 ?? $isbn10;
                    if ($isbn13) $works[$workIndex]['isbn_13'] = $isbn13;
                    if ($isbn10) $works[$workIndex]['isbn_10'] = $isbn10;
                }
            }
        }
        
        return $works;
    }

    /**
     * Normalize string for grouping (remove articles, special chars, lowercase)
     *
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    private function normalizeForGrouping(string $text): string
    {
        $text = mb_strtolower($text);
        // Remove common articles and prepositions
        $text = preg_replace('/\b(the|el|la|los|las|de|of|a|an|un|una)\b/', '', $text);
        // Remove special characters
        $text = preg_replace('/[^a-z0-9\s]/u', '', $text);
        // Collapse multiple spaces
        $text = trim(preg_replace('/\s+/', ' ', $text));
        return $text;
    }

    /**
     * Extract publication year from Google Books volume
     *
     * @param array $volumeInfo Volume info
     * @return int|null Publication year
     */
    private function extractYearFromVolume(array $volumeInfo): ?int
    {
        $publishedDate = $volumeInfo['publishedDate'] ?? null;
        if ($publishedDate && preg_match('/^(\d{4})/', $publishedDate, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Extract cover URL from Google Books volume
     *
     * @param array $volumeInfo Volume info
     * @return string|null Cover URL
     */
    private function extractCoverUrl(array $volumeInfo): ?string
    {
        $imageLinks = $volumeInfo['imageLinks'] ?? [];
        $url = $imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? null;
        if ($url && str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }
        return $url;
    }

    /**
     * Extract ISBN from Google Books volume
     *
     * @param array $volumeInfo Volume info
     * @param string $type ISBN type (ISBN_10 or ISBN_13)
     * @return string|null ISBN
     */
    private function extractISBN(array $volumeInfo, string $type): ?string
    {
        $identifiers = $volumeInfo['industryIdentifiers'] ?? [];
        foreach ($identifiers as $identifier) {
            if (($identifier['type'] ?? '') === $type) {
                return $identifier['identifier'] ?? null;
            }
        }
        return null;
    }

    /**
     * Get detailed work information with all editions
     *
     * @param string $workKey OpenLibrary work key
     * @param bool $enrichWithGoogle Whether to enrich with Google Books
     * @return array|null Work details with editions
     */
    public function getWorkDetails(string $workKey, bool $enrichWithGoogle = true): ?array
    {
        $this->logger->info("WorkSearchService: Getting work details", [
            'work_key' => $workKey,
            'enrich' => $enrichWithGoogle
        ]);

        // Get work details from OpenLibrary
        $work = $this->openLibraryService->getWork($workKey);

        if (!$work) {
            $this->logger->warning("WorkSearchService: Work not found", ['work_key' => $workKey]);
            return null;
        }

        // Enrich with Google Books if requested
        if ($enrichWithGoogle) {
            $work = $this->googleBooksService->enrichWork($work);
        }

        return $work;
    }

    /**
     * Get editions for a specific work with filters
     *
     * @param string $workKey OpenLibrary work key
     * @param array $filters Filters to apply (format, language, year_from, year_to, etc.)
     * @param int $page Page number (1-indexed)
     * @param int $limit Results per page
     * @return array Editions with pagination metadata
     */
    public function getWorkEditions(
        string $workKey,
        array $filters = [],
        int $page = 1,
        int $limit = 20
    ): array {
        $this->logger->info("WorkSearchService: Getting work editions", [
            'work_key' => $workKey,
            'filters' => $filters,
            'page' => $page,
            'limit' => $limit
        ]);

        // Calculate offset
        $offset = ($page - 1) * $limit;

        // Get editions from OpenLibrary
        // Fetch 1.5x more to allow for filtering while minimizing API calls
        $fetchLimit = (int)ceil($limit * 1.5);
        $result = $this->openLibraryService->getWorkEditions($workKey, $fetchLimit, 0);
        $allEditions = $result['editions'] ?? [];

        // Apply filters
        $filteredEditions = $this->applyFilters($allEditions, $filters);

        // Apply pagination
        $total = count($filteredEditions);
        $paginatedEditions = array_slice($filteredEditions, $offset, $limit);

        // Calculate available filters from all editions
        $availableFilters = $this->calculateAvailableFilters($allEditions);

        return [
            'editions' => $paginatedEditions,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int)ceil($total / $limit),
            'available_filters' => $availableFilters
        ];
    }

    /**
     * Validate ISBN and get work key
     * Used for importing editions
     *
     * @param string $isbn ISBN-10 or ISBN-13
     * @return array|null Array with work_key and edition info
     */
    public function validateAndGetWorkFromISBN(string $isbn): ?array
    {
        $this->logger->info("WorkSearchService: Validating ISBN", ['isbn' => $isbn]);

        // Try OpenLibrary first
        $edition = $this->openLibraryService->getEditionByISBN($isbn);

        if (!$edition) {
            $this->logger->info("WorkSearchService: ISBN not found in OpenLibrary, trying Google Books");
            
            // Try Google Books as fallback
            $googleBook = $this->googleBooksService->searchByISBN($isbn);
            
            if (!$googleBook) {
                return null;
            }

            // Return synthetic work (needs manual review)
            return [
                'is_synthetic' => true,
                'edition' => $googleBook,
                'work_key' => null,
                'needs_review' => true
            ];
        }

        // Get work key from edition
        $workKeys = $edition['work_keys'] ?? [];
        $workKey = !empty($workKeys) ? $workKeys[0] : null;

        return [
            'is_synthetic' => false,
            'edition' => $edition,
            'work_key' => $workKey,
            'needs_review' => false
        ];
    }

    /**
     * Enrich work data for display
     * Adds computed fields and formats data for frontend
     *
     * @param array $work Work data
     * @return array Enriched work data
     */
    private function enrichWorkForDisplay(array $work): array
    {
        // Format authors for display
        if (isset($work['author']) && is_string($work['author'])) {
            $work['authors_display'] = $work['author'];
        } elseif (isset($work['authors']) && is_array($work['authors'])) {
            $work['authors_display'] = implode(', ', $work['authors']);
        } else {
            $work['authors_display'] = 'Unknown Author';
        }

        // Add metadata flags
        $work['has_cover'] = !empty($work['cover_url']);
        $work['has_description'] = !empty($work['description']);

        return $work;
    }

    /**
     * Apply filters to editions
     *
     * @param array $editions Array of editions
     * @param array $filters Filters to apply
     * @return array Filtered editions
     */
    private function applyFilters(array $editions, array $filters): array
    {
        if (empty($filters)) {
            return $editions;
        }

        return array_values(array_filter($editions, function($edition) use ($filters) {
            // Filter by format
            if (!empty($filters['format'])) {
                $format = strtolower($edition['physical_format'] ?? '');
                $filterFormat = strtolower($filters['format']);
                if (strpos($format, $filterFormat) === false) {
                    return false;
                }
            }

            // Filter by language
            if (!empty($filters['language'])) {
                $languages = $edition['languages'] ?? [];
                $languageCodes = array_map(function($lang) {
                    return is_array($lang) ? ($lang['key'] ?? '') : $lang;
                }, $languages);
                
                if (!in_array($filters['language'], $languageCodes)) {
                    return false;
                }
            }

            // Filter by year range
            if (!empty($filters['year_from']) && !empty($edition['publish_year'])) {
                if ($edition['publish_year'] < $filters['year_from']) {
                    return false;
                }
            }

            if (!empty($filters['year_to']) && !empty($edition['publish_year'])) {
                if ($edition['publish_year'] > $filters['year_to']) {
                    return false;
                }
            }

            // Filter by ISBN presence
            if (!empty($filters['has_isbn'])) {
                if (empty($edition['isbn_13']) && empty($edition['isbn_10'])) {
                    return false;
                }
            }

            // Filter by page range
            if (!empty($filters['min_pages']) && !empty($edition['number_of_pages'])) {
                if ($edition['number_of_pages'] < $filters['min_pages']) {
                    return false;
                }
            }

            if (!empty($filters['max_pages']) && !empty($edition['number_of_pages'])) {
                if ($edition['number_of_pages'] > $filters['max_pages']) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Extract authors from edition and Google Books data
     *
     * @param array $edition OpenLibrary edition data
     * @param array $googleBook Google Books data
     * @return array Array of author names
     */
    private function extractAuthorsFromEdition(array $edition, array $googleBook): array
    {
        // Try edition authors first
        if (!empty($edition['authors'])) {
            return array_map(function($author) {
                return is_array($author) ? ($author['name'] ?? 'Unknown') : (string)$author;
            }, $edition['authors']);
        }
        
        // Fallback to Google Books authors
        return $googleBook['authors'] ?? ['Unknown'];
    }

    /**
     * Extract year from publish date string
     *
     * @param string $publishDate Date string (various formats)
     * @return int|null Year or null if cannot extract
     */
    private function extractYear(string $publishDate): ?int
    {
        // Try to extract 4-digit year
        if (preg_match('/\b(19|20)\d{2}\b/', $publishDate, $matches)) {
            return (int)$matches[0];
        }
        return null;
    }

    /**
     * Calculate available filter options from editions
     *
     * @param array $editions Array of editions
     * @return array Available filter values
     */
    private function calculateAvailableFilters(array $editions): array
    {
        $formats = [];
        $languages = [];
        $years = [];
        $publishers = [];

        foreach ($editions as $edition) {
            // Collect formats
            if (!empty($edition['physical_format'])) {
                $formats[] = $edition['physical_format'];
            }

            // Collect languages
            if (!empty($edition['languages'])) {
                foreach ($edition['languages'] as $lang) {
                    $langCode = is_array($lang) ? ($lang['key'] ?? '') : $lang;
                    if ($langCode) {
                        $languages[] = $langCode;
                    }
                }
            }

            // Collect years
            if (!empty($edition['publish_year'])) {
                $years[] = $edition['publish_year'];
            }

            // Collect publishers
            if (!empty($edition['publishers'])) {
                foreach ($edition['publishers'] as $publisher) {
                    $publishers[] = $publisher;
                }
            }
        }

        return [
            'formats' => array_values(array_unique($formats)),
            'languages' => array_values(array_unique($languages)),
            'year_range' => [
                'min' => !empty($years) ? min($years) : null,
                'max' => !empty($years) ? max($years) : null
            ],
            'publishers' => array_values(array_unique($publishers))
        ];
    }
}
