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

            // Get edition_id from ISBN
            $editionId = $this->getEditionIdFromIsbn($isbn);
            if (!$editionId) {
                throw new RuntimeException("Edition not found for ISBN: {$isbn}");
            }

            $sessionNumber = $sessionNumber ?? $this->getNextSessionNumber($userId, $isbn);

            $sql = "
                INSERT INTO reading_sessions
                (user_id, edition_id, session_number, start_date, is_active, start_page)
                VALUES (:userId, :editionId, :sessionNumber, NOW(), TRUE, :startPage)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':editionId' => $editionId,
                ':sessionNumber' => $sessionNumber,
                ':startPage' => $startPage ?? 0
            ]);

            $sessionId = (int) $this->db->lastInsertId();

            $this->logInfo('Reading session created', [
                'sessionId' => $sessionId,
                'userId' => $userId,
                'isbn' => $isbn,
                'editionId' => $editionId,
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

            // Get edition_id from ISBN
            $editionId = $this->getEditionIdFromIsbn($isbn);
            if (!$editionId) {
                return null; // No edition found
            }

            $sql = "
                SELECT
                    *,
                    CASE
                        WHEN is_active = TRUE AND end_date IS NULL THEN 'active'
                        WHEN is_active = FALSE AND end_date IS NOT NULL THEN 'completed'
                        ELSE 'unknown'
                    END as status,
                    start_date as started_at,
                    end_date as completed_at,
                    end_page as final_page,
                    notes as session_notes
                FROM reading_sessions
                WHERE user_id = :userId
                  AND edition_id = :editionId
                  AND is_active = TRUE
                ORDER BY start_date DESC
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':editionId' => $editionId
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
                SET is_active = FALSE,
                    end_date = NOW(),
                    end_page = :finalPage
                WHERE id = :sessionId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':sessionId' => $sessionId,
                ':finalPage' => $finalPage ?? 0
            ]);

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
                SET is_active = FALSE,
                    notes = CONCAT(COALESCE(notes, ''), :reason)
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
                SET is_active = TRUE
                WHERE id = :sessionId AND is_active = FALSE
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
                SET is_active = FALSE,
                    end_date = NOW(),
                    notes = CONCAT(COALESCE(notes, ''), :reason)
                WHERE id = :sessionId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':sessionId' => $sessionId,
                ':reason' => $reason ? "\n[ABANDONED] $reason" : ''
            ]);

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

            // Get edition_id from ISBN
            $editionId = $this->getEditionIdFromIsbn($isbn);
            if (!$editionId) {
                return []; // No edition found
            }

            $sql = "
                SELECT
                    *,
                    CASE
                        WHEN is_active = TRUE AND end_date IS NULL THEN 'active'
                        WHEN is_active = FALSE AND end_date IS NOT NULL THEN 'completed'
                        ELSE 'unknown'
                    END as status,
                    start_date as started_at,
                    end_date as completed_at,
                    end_page as final_page,
                    notes as session_notes
                FROM reading_sessions
                WHERE user_id = :userId AND edition_id = :editionId
                ORDER BY start_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':editionId' => $editionId
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
                SELECT rs.*, be.title, w.authors
                FROM reading_sessions rs
                INNER JOIN book_editions be ON rs.edition_id = be.edition_id
                INNER JOIN book_works w ON be.work_id = w.work_id
                WHERE rs.user_id = :userId AND rs.is_active = TRUE
                ORDER BY rs.start_date DESC
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
                SELECT rs.*, be.title, w.authors
                FROM reading_sessions rs
                INNER JOIN book_editions be ON rs.edition_id = be.edition_id
                INNER JOIN book_works w ON be.work_id = w.work_id
                WHERE rs.user_id = :userId
            ";

            $params = [':userId' => $userId];

            // Note: is_active is boolean, so we ignore the status parameter filter
            // as the current schema doesn't support multiple status types

            $sql .= " ORDER BY rs.start_date DESC";

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

            // Get edition_id from ISBN
            $editionId = $this->getEditionIdFromIsbn($isbn);
            if (!$editionId) {
                return 1; // No edition found, start with 1
            }

            $sql = "
                SELECT COALESCE(MAX(session_number), 0) + 1 as next_number
                FROM reading_sessions
                WHERE user_id = :userId AND edition_id = :editionId
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':editionId' => $editionId
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
                    SUM(CASE WHEN end_date IS NOT NULL THEN 1 ELSE 0 END) as completed_sessions,
                    SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active_sessions,
                    SUM(CASE WHEN is_active = FALSE AND end_date IS NULL THEN 1 ELSE 0 END) as paused_sessions,
                    COUNT(DISTINCT edition_id) as unique_books_read,
                    AVG(TIMESTAMPDIFF(DAY, start_date, end_date)) as avg_days_to_complete
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

            // Get edition_id from ISBN
            $editionId = $this->getEditionIdFromIsbn($isbn);
            if (!$editionId) {
                return false; // No edition found
            }

            $sql = "
                SELECT COUNT(*) as count
                FROM reading_sessions
                WHERE user_id = :userId
                  AND edition_id = :editionId
                  AND end_date IS NOT NULL
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':editionId' => $editionId
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

            // Get edition_id from ISBN
            $editionId = $this->getEditionIdFromIsbn($isbn);
            if (!$editionId) {
                return 0; // No edition found
            }

            $sql = "
                SELECT COUNT(*) as count
                FROM reading_sessions
                WHERE user_id = :userId
                  AND edition_id = :editionId
                  AND end_date IS NOT NULL
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':editionId' => $editionId
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

    /**
     * Get edition_id from ISBN (ISBN-13 or ISBN-10)
     */
    private function getEditionIdFromIsbn(string $isbn): ?int
    {
        try {
            $this->logInfo('Looking up edition_id from ISBN', ['isbn' => $isbn]);

            $sql = "
                SELECT edition_id FROM book_editions
                WHERE isbn_13 = :isbn1 OR isbn_10 = :isbn2
                LIMIT 1
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':isbn1' => $isbn, ':isbn2' => $isbn]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logInfo('Edition lookup result', [
                'isbn' => $isbn,
                'result' => $result,
                'edition_id' => $result ? $result['edition_id'] : null
            ]);

            return $result ? (int) $result['edition_id'] : null;
        } catch (PDOException $e) {
            $this->logError('Error getting edition_id from ISBN', $e, ['isbn' => $isbn]);
            return null;
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
