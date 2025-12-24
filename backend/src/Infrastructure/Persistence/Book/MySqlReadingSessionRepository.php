<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Repository\Book\ReadingSessionRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Reading Session management
 * Handles reading session tracking with status management
 */
final class MySqlReadingSessionRepository implements ReadingSessionRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function create(
        int $userId,
        string $isbn,
        ?int $sessionNumber = null,
        ?int $startPage = null
    ): int {
        try {
            $userId = (int) $userId;
            $sessionNumber = $sessionNumber ?? $this->getNextSessionNumber($userId, $isbn);

            $sql = "
                INSERT INTO reading_sessions 
                (user_id, book_isbn, session_number, started_at, status, final_page)
                VALUES (:userId, :isbn, :sessionNumber, NOW(), 'active', :startPage)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn,
                ':sessionNumber' => $sessionNumber,
                ':startPage' => $startPage
            ]);

            $sessionId = (int) $this->db->lastInsertId();

            // Update user_books active session
            $this->updateUserBookActiveSession($userId, $isbn, $sessionId);

            $this->logInfo('Reading session created', [
                'sessionId' => $sessionId,
                'userId' => $userId,
                'isbn' => $isbn,
                'sessionNumber' => $sessionNumber
            ]);

            return $sessionId;

        } catch (PDOException $e) {
            $this->logError('Error creating reading session', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            throw new RuntimeException("Could not create reading session: " . $e->getMessage(), 0, $e);
        }
    }

    public function getActive(int $userId, string $isbn): ?array
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT * FROM reading_sessions
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
            return $result ?: null;

        } catch (PDOException $e) {
            $this->logError('Error getting active session', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            throw new RuntimeException("Could not get active session: " . $e->getMessage(), 0, $e);
        }
    }

    public function complete(int $sessionId, ?int $finalPage = null): void
    {
        try {
            $sessionId = (int) $sessionId;

            $sql = "
                UPDATE reading_sessions
                SET status = 'completed',
                    completed_at = NOW(),
                    final_page = :finalPage
                WHERE id = :sessionId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':sessionId' => $sessionId,
                ':finalPage' => $finalPage
            ]);

            // Get session info to update user_books
            $session = $this->getSessionById($sessionId);
            if ($session) {
                $this->updateUserBookOnCompletion($session['user_id'], $session['book_isbn']);
            }

            $this->logInfo('Reading session completed', [
                'sessionId' => $sessionId,
                'finalPage' => $finalPage
            ]);

        } catch (PDOException $e) {
            $this->logError('Error completing session', $e, ['sessionId' => $sessionId]);
            throw new RuntimeException("Could not complete session: " . $e->getMessage(), 0, $e);
        }
    }

    public function pause(int $sessionId, ?string $reason = null): void
    {
        try {
            $sessionId = (int) $sessionId;

            $sql = "
                UPDATE reading_sessions
                SET status = 'paused',
                    session_notes = CONCAT(COALESCE(session_notes, ''), :reason)
                WHERE id = :sessionId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':sessionId' => $sessionId,
                ':reason' => $reason ? "\n[PAUSED] $reason" : ''
            ]);

            $this->logInfo('Reading session paused', [
                'sessionId' => $sessionId,
                'reason' => $reason
            ]);

        } catch (PDOException $e) {
            $this->logError('Error pausing session', $e, ['sessionId' => $sessionId]);
            throw new RuntimeException("Could not pause session: " . $e->getMessage(), 0, $e);
        }
    }

    public function resume(int $sessionId): void
    {
        try {
            $sessionId = (int) $sessionId;

            $sql = "
                UPDATE reading_sessions
                SET status = 'active'
                WHERE id = :sessionId AND status = 'paused'
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':sessionId' => $sessionId]);

            $this->logInfo('Reading session resumed', ['sessionId' => $sessionId]);

        } catch (PDOException $e) {
            $this->logError('Error resuming session', $e, ['sessionId' => $sessionId]);
            throw new RuntimeException("Could not resume session: " . $e->getMessage(), 0, $e);
        }
    }

    public function abandon(int $sessionId, ?string $reason = null): void
    {
        try {
            $sessionId = (int) $sessionId;

            $sql = "
                UPDATE reading_sessions
                SET status = 'abandoned',
                    completed_at = NOW(),
                    session_notes = CONCAT(COALESCE(session_notes, ''), :reason)
                WHERE id = :sessionId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':sessionId' => $sessionId,
                ':reason' => $reason ? "\n[ABANDONED] $reason" : ''
            ]);

            // Clear active session from user_books
            $session = $this->getSessionById($sessionId);
            if ($session) {
                $this->clearUserBookActiveSession($session['user_id'], $session['book_isbn']);
            }

            $this->logInfo('Reading session abandoned', [
                'sessionId' => $sessionId,
                'reason' => $reason
            ]);

        } catch (PDOException $e) {
            $this->logError('Error abandoning session', $e, ['sessionId' => $sessionId]);
            throw new RuntimeException("Could not abandon session: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $sessionId, bool $keepHistory = true): void
    {
        try {
            $sessionId = (int) $sessionId;

            // Get session info before deletion
            $session = $this->getSessionById($sessionId);

            if (!$keepHistory) {
                $deleteSql = "
                    DELETE FROM reading_progress_history
                    WHERE reading_session_id = :sessionId
                ";
                $stmt = $this->db->prepare($deleteSql);
                $stmt->execute([':sessionId' => $sessionId]);
            } else {
                // Nullify session reference but keep history
                $updateSql = "
                    UPDATE reading_progress_history
                    SET reading_session_id = NULL
                    WHERE reading_session_id = :sessionId
                ";
                $stmt = $this->db->prepare($updateSql);
                $stmt->execute([':sessionId' => $sessionId]);
            }

            // Delete the session
            $sql = "DELETE FROM reading_sessions WHERE id = :sessionId";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':sessionId' => $sessionId]);

            // Clear active session reference if it matches
            if ($session) {
                $this->clearUserBookActiveSession($session['user_id'], $session['book_isbn'], $sessionId);
            }

            $this->logInfo('Reading session deleted', [
                'sessionId' => $sessionId,
                'keepHistory' => $keepHistory
            ]);

        } catch (PDOException $e) {
            $this->logError('Error deleting session', $e, ['sessionId' => $sessionId]);
            throw new RuntimeException("Could not delete session: " . $e->getMessage(), 0, $e);
        }
    }

    public function getHistory(int $userId, string $isbn): array
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT * FROM reading_sessions
                WHERE user_id = :userId AND book_isbn = :isbn
                ORDER BY started_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->logError('Error getting session history', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            throw new RuntimeException("Could not get session history: " . $e->getMessage(), 0, $e);
        }
    }

    public function getProgress(int $sessionId): array
    {
        try {
            $sessionId = (int) $sessionId;

            $sql = "
                SELECT 
                    COUNT(*) as total_entries,
                    MIN(logged_at) as first_entry,
                    MAX(logged_at) as last_entry,
                    MAX(current_page) as highest_page,
                    SUM(CASE WHEN progress_type = 'advance' THEN 1 ELSE 0 END) as advances,
                    SUM(CASE WHEN progress_type = 'backtrack' THEN 1 ELSE 0 END) as backtracks
                FROM reading_progress_history
                WHERE reading_session_id = :sessionId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':sessionId' => $sessionId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [];

        } catch (PDOException $e) {
            $this->logError('Error getting session progress', $e, ['sessionId' => $sessionId]);
            throw new RuntimeException("Could not get session progress: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserActiveSessions(int $userId): array
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT rs.*, b.title, b.author, b.total_pages
                FROM reading_sessions rs
                INNER JOIN books b ON rs.book_isbn = b.isbn
                WHERE rs.user_id = :userId AND rs.status = 'active'
                ORDER BY rs.started_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->logError('Error getting user active sessions', $e, ['userId' => $userId]);
            throw new RuntimeException("Could not get user active sessions: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserSessions(int $userId, ?string $status = null): array
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT rs.*, b.title, b.author
                FROM reading_sessions rs
                INNER JOIN books b ON rs.book_isbn = b.isbn
                WHERE rs.user_id = :userId
            ";

            $params = [':userId' => $userId];

            if ($status !== null) {
                $sql .= " AND rs.status = :status";
                $params[':status'] = $status;
            }

            $sql .= " ORDER BY rs.started_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->logError('Error getting user sessions', $e, [
                'userId' => $userId,
                'status' => $status
            ]);
            throw new RuntimeException("Could not get user sessions: " . $e->getMessage(), 0, $e);
        }
    }

    public function getNextSessionNumber(int $userId, string $isbn): int
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT COALESCE(MAX(session_number), 0) + 1 as next_number
                FROM reading_sessions
                WHERE user_id = :userId AND book_isbn = :isbn
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['next_number'] ?? 1);

        } catch (PDOException $e) {
            $this->logError('Error getting next session number', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            return 1; // Fallback to 1
        }
    }

    public function getSessionStats(int $userId): array
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT 
                    COUNT(*) as total_sessions,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_sessions,
                    SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned_sessions,
                    SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused_sessions,
                    COUNT(DISTINCT book_isbn) as unique_books_read,
                    AVG(TIMESTAMPDIFF(DAY, started_at, completed_at)) as avg_days_to_complete
                FROM reading_sessions
                WHERE user_id = :userId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [];

        } catch (PDOException $e) {
            $this->logError('Error getting session stats', $e, ['userId' => $userId]);
            throw new RuntimeException("Could not get session stats: " . $e->getMessage(), 0, $e);
        }
    }

    public function hasCompletedBook(int $userId, string $isbn): bool
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT COUNT(*) as count
                FROM reading_sessions
                WHERE user_id = :userId 
                  AND book_isbn = :isbn 
                  AND status = 'completed'
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ((int) $result['count']) > 0;

        } catch (PDOException $e) {
            $this->logError('Error checking book completion', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            return false;
        }
    }

    public function getBookCompletionCount(int $userId, string $isbn): int
    {
        try {
            $userId = (int) $userId;

            $sql = "
                SELECT COUNT(*) as count
                FROM reading_sessions
                WHERE user_id = :userId 
                  AND book_isbn = :isbn 
                  AND status = 'completed'
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);

        } catch (PDOException $e) {
            $this->logError('Error getting completion count', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            return 0;
        }
    }

    public function updateBookStatusesBasedOnSessions(int $userId, string $isbn): void
    {
        try {
            $userId = (int) $userId;
            $hasCompleted = $this->hasCompletedBook($userId, $isbn);
            $hasActive = $this->getActive($userId, $isbn) !== null;

            // This would interact with UserBookRepository to update statuses
            // Implementation depends on status management strategy
            $this->logInfo('Book statuses update requested', [
                'userId' => $userId,
                'isbn' => $isbn,
                'hasCompleted' => $hasCompleted,
                'hasActive' => $hasActive
            ]);

        } catch (PDOException $e) {
            $this->logError('Error updating book statuses', $e, [
                'userId' => $userId,
                'isbn' => $isbn
            ]);
            throw new RuntimeException("Could not update book statuses: " . $e->getMessage(), 0, $e);
        }
    }

    // Helper methods

    private function getSessionById(int $sessionId): ?array
    {
        $sql = "SELECT * FROM reading_sessions WHERE id = :sessionId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':sessionId' => $sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function updateUserBookActiveSession(int $userId, string $isbn, int $sessionId): void
    {
        $sql = "
            UPDATE user_books
            SET active_reading_session_id = :sessionId
            WHERE user_id = :userId AND book_isbn = :isbn
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sessionId' => $sessionId,
            ':userId' => $userId,
            ':isbn' => $isbn
        ]);
    }

    private function clearUserBookActiveSession(int $userId, string $isbn, ?int $onlyIfMatches = null): void
    {
        $sql = "
            UPDATE user_books
            SET active_reading_session_id = NULL
            WHERE user_id = :userId AND book_isbn = :isbn
        ";

        $params = [':userId' => $userId, ':isbn' => $isbn];

        if ($onlyIfMatches !== null) {
            $sql .= " AND active_reading_session_id = :sessionId";
            $params[':sessionId'] = $onlyIfMatches;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function updateUserBookOnCompletion(int $userId, string $isbn): void
    {
        $sql = "
            UPDATE user_books
            SET total_sessions_completed = total_sessions_completed + 1,
                last_session_completed_at = NOW(),
                active_reading_session_id = NULL
            WHERE user_id = :userId AND book_isbn = :isbn
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':userId' => $userId,
            ':isbn' => $isbn
        ]);
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
