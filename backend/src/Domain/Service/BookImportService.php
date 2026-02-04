<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Model\Edition;
use App\Domain\Model\Work;
use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\WorkRepositoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service for importing book data from OpenLibrary API
 * Orchestrates creation/retrieval of Work and Edition entities
 */
final class BookImportService
{
    public function __construct(
        private readonly WorkRepositoryInterface $workRepository,
        private readonly EditionRepositoryInterface $editionRepository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Import a book edition from OpenLibrary API data
     * Returns both Work and Edition entities
     *
     * @param array $openLibraryData API response from OpenLibrary /books/{ISBN}.json
     * @return array{work: Work, edition: Edition}
     */
    public function importFromOpenLibrary(array $openLibraryData): array
    {
        $this->logger->info('Starting book import from OpenLibrary', [
            'data_keys' => array_keys($openLibraryData)
        ]);

        // Extract work key from edition data
        $workKey = $this->extractWorkKey($openLibraryData);
        $work = $this->findOrCreateWork($workKey, $openLibraryData);

        // Extract ISBN to check if edition exists
        $isbn13 = $this->extractIsbn13($openLibraryData);
        $isbn10 = $this->extractIsbn10($openLibraryData);
        $openlibraryKey = $this->extractOpenLibraryKey($openLibraryData);

        $edition = $this->findOrCreateEdition(
            $work,
            $openLibraryData,
            $isbn13,
            $isbn10,
            $openlibraryKey
        );

        $this->logger->info('Book import completed', [
            'work_id' => $work->getWorkId(),
            'edition_id' => $edition->getEditionId()
        ]);

        return [
            'work' => $work,
            'edition' => $edition
        ];
    }

    /**
     * Extract OpenLibrary work key from edition data
     * Example: "/works/OL45804W" -> "OL45804W"
     */
    private function extractWorkKey(array $data): ?string
    {
        if (isset($data['works'][0]['key'])) {
            return basename($data['works'][0]['key']);
        }

        return null;
    }

    /**
     * Find existing work or create a new one
     */
    private function findOrCreateWork(?string $workKey, array $editionData): Work
    {
        // Try to find existing work by OpenLibrary key
        if ($workKey !== null) {
            $existingWork = $this->workRepository->findByOpenLibraryKey($workKey);
            if ($existingWork !== null) {
                $this->logger->info('Found existing work', ['work_id' => $existingWork->getWorkId()]);
                return $existingWork;
            }
        }

        // Extract work-level information
        $title = $editionData['title'] ?? 'Unknown Title';
        $authors = $this->extractAuthors($editionData);
        $subjects = $editionData['subjects'] ?? [];
        $firstPublishYear = $this->extractPublishYear($editionData);

        // Check for duplicate work by title and authors
        if (!empty($authors)) {
            $existingWork = $this->workRepository->findByTitleAndAuthors($title, $authors);
            if ($existingWork !== null) {
                $this->logger->info('Found duplicate work by title and authors', [
                    'work_id' => $existingWork->getWorkId()
                ]);
                return $existingWork;
            }
        }

        // Create new work
        $work = Work::fromArray([
            'openlibraryWorkKey' => $workKey,
            'title' => $title,
            'authors' => $authors,
            'subjects' => $subjects,
            'firstPublishYear' => $firstPublishYear,
            'isSynthetic' => $workKey === null
        ]);

        $savedWork = $this->workRepository->save($work);
        $this->logger->info('Created new work', ['work_id' => $savedWork->getWorkId()]);

        return $savedWork;
    }

    /**
     * Find existing edition or create a new one
     */
    private function findOrCreateEdition(
        Work $work,
        array $editionData,
        ?string $isbn13,
        ?string $isbn10,
        ?string $openlibraryKey
    ): Edition {
        // Try to find by ISBN first
        if ($isbn13 !== null) {
            $existing = $this->editionRepository->findByIsbn13($isbn13);
            if ($existing !== null) {
                $this->logger->info('Found existing edition by ISBN-13', [
                    'edition_id' => $existing->getEditionId()
                ]);
                return $existing;
            }
        }

        if ($isbn10 !== null) {
            $existing = $this->editionRepository->findByIsbn10($isbn10);
            if ($existing !== null) {
                $this->logger->info('Found existing edition by ISBN-10', [
                    'edition_id' => $existing->getEditionId()
                ]);
                return $existing;
            }
        }

        // Try to find by OpenLibrary key
        if ($openlibraryKey !== null) {
            $existing = $this->editionRepository->findByOpenLibraryKey($openlibraryKey);
            if ($existing !== null) {
                $this->logger->info('Found existing edition by OL key', [
                    'edition_id' => $existing->getEditionId()
                ]);
                return $existing;
            }
        }

        // Create new edition
        $edition = Edition::fromArray([
            'work_id' => $work->getWorkId(),
            'openlibrary_edition_key' => $openlibraryKey,
            'isbn_13' => $isbn13,
            'isbn_10' => $isbn10,
            'title' => $editionData['title'] ?? $work->getTitle(),
            'description' => $editionData['description'] ?? null,
            'publisher' => $editionData['publishers'][0] ?? null,
            'publish_year' => $this->extractPublishYear($editionData),
            'publish_date' => $editionData['publish_date'] ?? null,
            'number_of_pages' => $editionData['number_of_pages'] ?? null,
            'format' => $editionData['physical_format'] ?? null,
            'languages' => $this->extractLanguages($editionData),
            'illustrators' => $this->extractContributors($editionData, 'illustrators'),
            'translators' => $this->extractContributors($editionData, 'translators'),
            'covers' => $editionData['covers'] ?? [],
            'cover_urls' => $this->buildCoverUrls($editionData),
            'series' => $editionData['series'] ?? null,
            'notes' => $editionData['notes'] ?? null,
            'weight' => $editionData['weight'] ?? null,
            'dimensions' => $editionData['physical_dimensions'] ?? null,
            'goodreads_id' => null,
            'amazon_id' => null,
            'last_synced_at' => date('Y-m-d H:i:s')
        ]);

        $savedEdition = $this->editionRepository->save($edition);
        $this->logger->info('Created new edition', ['edition_id' => $savedEdition->getEditionId()]);

        return $savedEdition;
    }

    /**
     * Extract OpenLibrary edition key from data
     * Example: "/books/OL27479811M" -> "OL27479811M"
     */
    private function extractOpenLibraryKey(array $data): ?string
    {
        if (isset($data['key'])) {
            return basename($data['key']);
        }

        return null;
    }

    /**
     * Extract ISBN-13 from OpenLibrary data
     */
    private function extractIsbn13(array $data): ?string
    {
        if (!empty($data['isbn_13']) && is_array($data['isbn_13'])) {
            $isbn = $data['isbn_13'][0];
            try {
                $isbnVO = ISBN::fromString($isbn);
                return $isbnVO->toString();
            } catch (\InvalidArgumentException $e) {
                $this->logger->warning('Invalid ISBN-13', ['isbn' => $isbn]);
                return null;
            }
        }

        return null;
    }

    /**
     * Extract ISBN-10 from OpenLibrary data
     */
    private function extractIsbn10(array $data): ?string
    {
        if (!empty($data['isbn_10']) && is_array($data['isbn_10'])) {
            $isbn = $data['isbn_10'][0];
            try {
                $isbnVO = ISBN::fromString($isbn);
                return $isbnVO->toString();
            } catch (\InvalidArgumentException $e) {
                $this->logger->warning('Invalid ISBN-10', ['isbn' => $isbn]);
                return null;
            }
        }

        return null;
    }

    /**
     * Extract authors array from OpenLibrary data
     */
    private function extractAuthors(array $data): array
    {
        if (!isset($data['authors'])) {
            return [];
        }

        $authors = [];
        foreach ($data['authors'] as $author) {
            if (isset($author['name'])) {
                $authors[] = $author['name'];
            } elseif (is_string($author)) {
                $authors[] = $author;
            }
        }

        return $authors;
    }

    /**
     * Extract contributors (illustrators, translators, etc.)
     */
    private function extractContributors(array $data, string $type): array
    {
        if (!isset($data['contributions'])) {
            return [];
        }

        $contributors = [];
        foreach ($data['contributions'] as $contribution) {
            if (isset($contribution['role']) && str_contains(strtolower($contribution['role']), $type)) {
                $contributors[] = $contribution['name'];
            }
        }

        return $contributors;
    }

    /**
     * Extract languages array
     */
    private function extractLanguages(array $data): array
    {
        if (!isset($data['languages'])) {
            return [];
        }

        $languages = [];
        foreach ($data['languages'] as $lang) {
            if (isset($lang['key'])) {
                $languages[] = basename($lang['key']);
            } elseif (is_string($lang)) {
                $languages[] = $lang;
            }
        }

        return $languages;
    }

    /**
     * Extract publish year from various fields
     */
    private function extractPublishYear(array $data): ?int
    {
        if (isset($data['publish_date'])) {
            if (preg_match('/\d{4}/', $data['publish_date'], $matches)) {
                return (int) $matches[0];
            }
        }

        if (isset($data['first_publish_year'])) {
            return (int) $data['first_publish_year'];
        }

        return null;
    }

    /**
     * Build cover URLs from OpenLibrary data
     */
    private function buildCoverUrls(array $data): array
    {
        $urls = [];

        if (!empty($data['covers']) && is_array($data['covers'])) {
            $coverId = $data['covers'][0];
            $urls['S'] = "https://covers.openlibrary.org/b/id/{$coverId}-S.jpg";
            $urls['M'] = "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg";
            $urls['L'] = "https://covers.openlibrary.org/b/id/{$coverId}-L.jpg";
        }

        return $urls;
    }
}
