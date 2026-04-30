<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Model\Edition;
use App\Domain\Model\Work;

/**
 * Interface for book import services
 * Allows importing book data from external sources
 */
interface BookImportServiceInterface
{
    /**
     * Import a book edition from OpenLibrary API data
     * Returns both Work and Edition entities
     *
     * @param array $openLibraryData API response from OpenLibrary /books/{ISBN}.json
     * @return array{work: Work, edition: Edition}
     */
    public function importFromOpenLibrary(array $openLibraryData): array;
}
