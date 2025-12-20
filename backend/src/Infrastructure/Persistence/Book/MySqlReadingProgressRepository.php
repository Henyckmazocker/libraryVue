<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Repository\Book\ReadingProgressRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Reading Progress management
 * Handles page progress tracking and statistics
 */
final class MySqlReadingProgressRepository implements ReadingProgressRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function updateWithSession(
        int $userId,
        string $isbn,
        int $currentPage,
        string $progressType = 'advance',
        ?string $notes = null
    ): void {
        try {
            $userId = (int) $userId;
            $currentPage = (int) $currentPage;

            // Get previous page from user_books
            $previousPage = $this->getCurrentPageFromUserBooks($userId, $isbn);

            // Get active session ID if exists
            $sessionId = $this->getActiveSessionId($userId, $isbn);

            // Add to history
            $this->addHistory($userId, $isbn, $currentPage, $previousPage, $sessionId);

            // Update user_books current_page
            $this->updateCurrentPage($userId, $isbn, $currentPage);

            if ($notes !== null) {
                $this->updateProgressNotes($userId, $isbn, $currentPage, $notes);
            }

            $this->logInfo('Reading progress updated', [
                'userId' => $userId,
                'isbn' => $isbn,
                'currentPage' => $currentPage,
                'progressType' => $progressType
            ]);

        } catch (PDOException $e) {
            $this->logError('Error updating reading progress', $e, [
                'userId' => $userId,
                'isbn' => $isbn,
                'currentPage' => $currentPage
            ]);
            throw new RuntimeException("Could not update reading progress: " . $e->getMessage(), 0, $e);
        }
    }

    public function addHistory(
        int $userId,
        string $isbn,
        int $currentPage,
        int $previousPage,
        ?int $sessionId = null
    ): void {
        try {
            $userId = (int) $userId;
            $currentPage = (int) $currentPage;
            $previousPage = (int) $previousPage;

            // Determine progress type
            $progressType = $this->determineProgressType($currentPage, $previousPage);

            $sql = "
                INSERT INTO reading_progress_history 
                (user_id, book_isbn, reading_session_id, current_page, previous_page, progress_type, logged_at)
                VALUES (:userId, :isbn, :sessionId, :currentPage, :previousPage, :progressType, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn,
                ':sessionId' => $sessionId,
                ':currentPage' => $currentPage,
                ':previousPage' => $previousPage,
                ':progressType' => $progressType
            ]);

            $this->logInfo('Progress history entry added', [
                'userId' => $userId,
                'isbn' => $isbn,
                'currentPage' => $currentPage,
                'progressType' => $progressType
            ]);

        } catch (PDOException $e) {
            $this->logError('Error adding progress history', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            throw new RuntimeException("Could not add progress history: " . $e->getMessage(), 0, $e);
        }
    }

    public function getHistory(int $userId, string $isbn): array
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT 
                    rph.*,
                    rs.session_number,
                    rs.status as session_status
                FROM reading_progress_history rph
                LEFT JOIN reading_sessions rs ON rph.reading_session_id = rs.id
                WHERE rph.user_id = :userId AND rph.book_isbn = :isbn
                ORDER BY rph.logged_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->logError('Error getting progress history', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            throw new RuntimeException("Could not get progress history: " . $e->getMessage(), 0, $e);
        }
    }

    public function getMonthlyStats(int $userId, int $months = 12): array
    {
        try {
            $userId = (int) $userId;
            $months = (int) $months;

            $sql = "
                SELECT 
                    DATE_FORMAT(logged_at, '%Y-%m') as month,
                    COUNT(*) as total_updates,
                    SUM(CASE WHEN progress_type = 'advance' THEN (current_page - previous_page) ELSE 0 END) as pages_read,
                    COUNT(DISTINCT book_isbn) as unique_books,
                    AVG(current_page - previous_page) as avg_pages_per_update
                FROM reading_progress_history
                WHERE user_id = :userId
                  AND logged_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                  AND progress_type = 'advance'
                GROUP BY DATE_FORMAT(logged_at, '%Y-%m')
                ORDER BY month DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':months' => $months
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->logError('Error getting monthly stats', $e, ['userId' => $userId]);
            throw new RuntimeException("Could not get monthly stats: " . $e->getMessage(), 0, $e);
        }
    }

    public function getLastProgressPage(int $userId, string $isbn): int
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT current_page
                FROM reading_progress_history
                WHERE user_id = :userId AND book_isbn = :isbn
                ORDER BY logged_at DESC
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['current_page'] ?? 0);

        } catch (PDOException $e) {
            $this->logError('Error getting last progress page', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            return 0;
        }
    }

    public function getBookReadingSummary(int $userId, int $bookId): array
    {
        try {
            $userId = (int) $userId;
            $bookId = (int) $bookId;

            // Note: This method uses book_id instead of ISBN
            // May need to join with books table or adjust based on actual usage
            $sql = "
                SELECT 
                    COUNT(*) as total_updates,
                    MIN(logged_at) as first_update,
                    MAX(logged_at) as last_update,
                    MAX(current_page) as highest_page_reached,
                    SUM(CASE WHEN progress_type = 'advance' THEN (current_page - previous_page) ELSE 0 END) as total_pages_read,
                    COUNT(DISTINCT reading_session_id) as total_sessions,
                    AVG(current_page - previous_page) as avg_progress_per_update
                FROM reading_progress_history rph
                INNER JOIN books b ON rph.book_isbn = b.isbn
                WHERE rph.user_id = :userId AND b.id = :bookId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':bookId' => $bookId
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [];

        } catch (PDOException $e) {
            $this->logError('Error getting book reading summary', $e, [
                'userId' => $userId,
                'bookId' => $bookId
            ]);
            throw new RuntimeException("Could not get book reading summary: " . $e->getMessage(), 0, $e);
        }
    }

    public function getDetailedProgressHistory(int $userId, int $bookId): array
    {
        try {
            $userId = (int) $userId;
            $bookId = (int) $bookId;

            $sql = "
                SELECT 
                    rph.*,
                    rs.session_number,
                    rs.status as session_status,
                    rs.started_at as session_start,
                    b.title as book_title,
                    b.total_pages
                FROM reading_progress_history rph
                INNER JOIN books b ON rph.book_isbn = b.isbn
                LEFT JOIN reading_sessions rs ON rph.reading_session_id = rs.id
                WHERE rph.user_id = :userId AND b.id = :bookId
                ORDER BY rph.logged_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':bookId' => $bookId
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->logError('Error getting detailed progress history', $e, [
                'userId' => $userId,
                'bookId' => $bookId
            ]);
            throw new RuntimeException("Could not get detailed progress history: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserReadingStats(int $userId): array
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT 
                    COUNT(*) as total_progress_updates,
                    COUNT(DISTINCT book_isbn) as unique_books_tracked,
                    SUM(CASE WHEN progress_type = 'advance' THEN (current_page - previous_page) ELSE 0 END) as total_pages_read,
                    AVG(CASE WHEN progress_type = 'advance' THEN (current_page - previous_page) ELSE NULL END) as avg_pages_per_session,
                    MIN(logged_at) as first_reading_date,
                    MAX(logged_at) as last_reading_date,
                    COUNT(DISTINCT DATE(logged_at)) as days_reading
                FROM reading_progress_history
                WHERE user_id = :userId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [];

        } catch (PDOException $e) {
            $this->logError('Error getting user reading stats', $e, ['userId' => $userId]);
            throw new RuntimeException("Could not get user reading stats: " . $e->getMessage(), 0, $e);
        }
    }

    public function getCurrentReadingSessions(int $userId): array
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT 
                    rs.id as session_id,
                    rs.book_isbn,
                    rs.session_number,
                    rs.started_at,
                    rs.status,
                    b.title,
                    b.author,
                    b.total_pages,
                    ub.current_page,
                    COUNT(rph.id) as progress_entries,
                    MAX(rph.logged_at) as last_update,
                    ROUND((ub.current_page / b.total_pages) * 100, 2) as completion_percentage
                FROM reading_sessions rs
                INNER JOIN books b ON rs.book_isbn = b.isbn
                INNER JOIN user_books ub ON rs.user_id = ub.user_id AND rs.book_isbn = ub.book_isbn
                LEFT JOIN reading_progress_history rph ON rs.id = rph.reading_session_id
                WHERE rs.user_id = :userId AND rs.status = 'active'
                GROUP BY rs.id, rs.book_isbn, rs.session_number, rs.started_at, rs.status,
                         b.title, b.author, b.total_pages, ub.current_page
                ORDER BY rs.started_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->logError('Error getting current reading sessions', $e, ['userId' => $userId]);
            throw new RuntimeException("Could not get current reading sessions: " . $e->getMessage(), 0, $e);
        }
    }

    // Helper methods

    private function getCurrentPageFromUserBooks(int $userId, string $isbn): int
    {
        $sql = "
            SELECT current_page
            FROM user_books
            WHERE user_id = :userId AND book_isbn = :isbn
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':userId' => $userId,
            ':isbn' => $isbn
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['current_page'] ?? 0);
    }

    private function getActiveSessionId(int $userId, string $isbn): ?int
    {
        $sql = "
            SELECT id
            FROM reading_sessions
            WHERE user_id = :userId 
              AND book_isbn = :isbn 
              AND status = 'active'
            ORDER BY started_at DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':userId' => $userId,
            ':isbn' => $isbn
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['id'] : null;
    }

    private function updateCurrentPage(int $userId, string $isbn, int $currentPage): void
    {
        $sql = "
            UPDATE user_books
            SET current_page = :currentPage
            WHERE user_id = :userId AND book_isbn = :isbn
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':currentPage' => $currentPage,
            ':userId' => $userId,
            ':isbn' => $isbn
        ]);
    }

    private function updateProgressNotes(int $userId, string $isbn, int $currentPage, string $notes): void
    {
        $sql = "
            UPDATE reading_progress_history
            SET notes = :notes
            WHERE user_id = :userId 
              AND book_isbn = :isbn 
              AND current_page = :currentPage
            ORDER BY logged_at DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':notes' => $notes,
            ':userId' => $userId,
            ':isbn' => $isbn,
            ':currentPage' => $currentPage
        ]);
    }

    private function determineProgressType(int $currentPage, int $previousPage): string
    {
        if ($currentPage === 0 && $previousPage > 0) {
            return 'restart';
        }
        if ($currentPage < $previousPage) {
            return 'backtrack';
        }
        return 'advance';
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
