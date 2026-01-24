<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book\Mappers;

use App\Domain\Model\Book;
use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

/**
 * Maps Book entity between Domain and Persistence layers
 * Handles Value Objects conversion
 */
final class BookDataMapper
{
    use HydrationHelpersTrait;

    /**
     * Convert database row to Book domain entity
     */
    public function toDomain(array $dbRow): Book
    {
        // Extract and convert ISBN
        $isbn = ISBN::fromString($dbRow['isbn'] ?? $dbRow['id'] ?? '');
        
        // Extract basic fields
        $title = (string) ($dbRow['title'] ?? '');
        $author = (string) ($dbRow['author'] ?? '');
        
        // Convert publication_year to nullable int
        $publicationYear = isset($dbRow['publication_year']) && $dbRow['publication_year'] !== null
            ? (int) $dbRow['publication_year']
            : null;
        
        // Convert pages to nullable int
        $pages = isset($dbRow['pages']) && $dbRow['pages'] !== null
            ? (int) $dbRow['pages']
            : null;
        
        // Convert genre to Genre VO (nullable)
        $genre = isset($dbRow['genre']) && !empty($dbRow['genre'])
            ? Genre::fromString((string) $dbRow['genre'])
            : null;
        
        // Extract optional string fields
        $description = $this->extractOptionalString($dbRow, 'description');
        $coverUrl = $this->extractOptionalString($dbRow, 'coverUrl');
        $publisher = $this->extractOptionalString($dbRow, 'publisher');
        $language = $this->extractOptionalString($dbRow, 'language');
        
        // Convert ratings to Rating VOs (nullable)
        $rating = isset($dbRow['rating']) && $dbRow['rating'] !== null
            ? Rating::fromNullableFloat((float) $dbRow['rating'])
            : null;
        
        $userRating = isset($dbRow['user_rating']) && $dbRow['user_rating'] !== null
            ? Rating::fromNullableFloat((float) $dbRow['user_rating'])
            : null;
        
        // Convert addedTimestamp
        $addedTimestamp = isset($dbRow['addedTimestamp']) && $dbRow['addedTimestamp'] !== null
            ? Timestamp::fromUnixTimestamp((int) $dbRow['addedTimestamp'])
            : Timestamp::now();
        
        // Parse genres (JSON string to array)
        $genres = [];
        if (isset($dbRow['genres']) && !empty($dbRow['genres'])) {
            if (is_string($dbRow['genres'])) {
                $decoded = json_decode($dbRow['genres'], true);
                $genres = is_array($decoded) ? $decoded : [];
            } elseif (is_array($dbRow['genres'])) {
                $genres = $dbRow['genres'];
            }
        }
        
        // Parse user_statuses (comma-separated string to array)
        $userStatuses = [];
        if (isset($dbRow['user_statuses'])) {
            if (is_array($dbRow['user_statuses'])) {
                // Ensure it's a flat array of strings only
                $userStatuses = array_values(array_filter($dbRow['user_statuses'], function($item) {
                    return is_string($item) && !empty($item);
                }));
            } elseif (is_string($dbRow['user_statuses']) && $dbRow['user_statuses'] !== '') {
                $userStatuses = array_filter(array_map('trim', explode(',', $dbRow['user_statuses'])));
            }
        }
        
        // Extract user-specific fields
        $currentPage = isset($dbRow['current_page']) && $dbRow['current_page'] !== null
            ? (int) $dbRow['current_page']
            : null;
        
        $personalNotes = $this->extractOptionalString($dbRow, 'personal_notes');
        $consumedAt = $this->extractOptionalString($dbRow, 'consumed_at');
        
        // Extract allowedStatuses from data or use empty array
        $allowedStatuses = isset($dbRow['allowedStatuses']) && is_array($dbRow['allowedStatuses'])
            ? $dbRow['allowedStatuses']
            : [];
        
        return Book::fromArray([
            'isbn' => $isbn->toString(),
            'title' => $title,
            'author' => $author,
            'publication_year' => $publicationYear,
            'pages' => $pages,
            'genre' => $genre?->toString(),
            'genres' => $genres,
            'description' => $description,
            'cover_url' => $coverUrl,
            'publisher' => $publisher,
            'language' => $language,
            'rating' => $rating?->toFloat(),
            'user_rating' => $userRating?->toFloat(),
            'addedTimestamp' => $addedTimestamp->toUnixTimestamp(),
            'userStatuses' => $userStatuses,
            'allowedStatuses' => $allowedStatuses,
            'current_page' => $currentPage,
            'personal_notes' => $personalNotes,
            'consumed_at' => $consumedAt
        ]);
    }

    /**
     * Convert Book domain entity to database persistence array
     */
    public function toPersistence(Book $book): array
    {
        $bookArray = $book->toArray();
        
        return [
            'isbn' => $bookArray['isbn'],
            'title' => $bookArray['title'],
            'author' => $bookArray['author'],
            'publication_year' => $bookArray['publication_year'],
            'pages' => $bookArray['pages'],
            'genre' => $bookArray['genre'],
            'description' => $bookArray['description'],
            'cover_url' => $bookArray['cover_url'],
            'publisher' => $bookArray['publisher'],
            'language' => $bookArray['language'],
            'rating' => $bookArray['rating'],
            'addedTimestamp' => $bookArray['addedTimestamp']
        ];
    }

    /**
     * Extract optional string field from database row
     */
    private function extractOptionalString(array $row, string $key): ?string
    {
        if (!isset($row[$key]) || $row[$key] === null || $row[$key] === '') {
            return null;
        }
        return (string) $row[$key];
    }
}
