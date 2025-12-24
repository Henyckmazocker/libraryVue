<?php
declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;

/**
 * Domain service for aggregating user library statistics
 * 
 * Aggregates data from multiple repositories to provide
 * comprehensive library statistics for a user.
 */
class UserLibraryStatisticsService
{
    public function __construct(
        private readonly UserBookRepositoryInterface $bookRepository,
        private readonly UserMovieRepositoryInterface $movieRepository
    ) {
    }

    /**
     * Get comprehensive library statistics for a user
     *
     * @param int $userId
     * @return array Statistics array with books and movies breakdowns
     */
    public function getUserLibraryStats(int $userId): array
    {
        $stats = [
            'books' => $this->bookRepository->countByStatus($userId),
            'movies' => $this->movieRepository->countByStatus($userId),
            'total_books' => $this->bookRepository->count($userId),
            'total_movies' => $this->movieRepository->count($userId)
        ];

        // Add totals summary
        $stats['total_items'] = $stats['total_books'] + $stats['total_movies'];

        // Add percentage calculations
        if ($stats['total_items'] > 0) {
            $stats['books_percentage'] = round(
                ($stats['total_books'] / $stats['total_items']) * 100,
                2
            );
            $stats['movies_percentage'] = round(
                ($stats['total_movies'] / $stats['total_items']) * 100,
                2
            );
        } else {
            $stats['books_percentage'] = 0;
            $stats['movies_percentage'] = 0;
        }

        return $stats;
    }

    /**
     * Get book statistics only
     *
     * @param int $userId
     * @return array
     */
    public function getBookStats(int $userId): array
    {
        return [
            'by_status' => $this->bookRepository->countByStatus($userId),
            'total' => $this->bookRepository->count($userId)
        ];
    }

    /**
     * Get movie statistics only
     *
     * @param int $userId
     * @return array
     */
    public function getMovieStats(int $userId): array
    {
        return [
            'by_status' => $this->movieRepository->countByStatus($userId),
            'total' => $this->movieRepository->count($userId)
        ];
    }

    /**
     * Check if user has any content in their library
     *
     * @param int $userId
     * @return bool
     */
    public function hasAnyContent(int $userId): bool
    {
        return $this->bookRepository->count($userId) > 0 
            || $this->movieRepository->count($userId) > 0;
    }

    /**
     * Get the most active content type for the user
     *
     * @param int $userId
     * @return string 'books', 'movies', or 'none'
     */
    public function getMostActiveContentType(int $userId): string
    {
        $bookCount = $this->bookRepository->count($userId);
        $movieCount = $this->movieRepository->count($userId);

        if ($bookCount === 0 && $movieCount === 0) {
            return 'none';
        }

        return $bookCount >= $movieCount ? 'books' : 'movies';
    }
}
