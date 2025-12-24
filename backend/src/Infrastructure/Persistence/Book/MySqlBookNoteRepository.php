<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Repository\Book\BookNoteRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Book Note management
 * Handles user notes on books
 */
final class MySqlBookNoteRepository implements BookNoteRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function getByPage(int $userId, string $isbn): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, page_number, note_text, note_type, is_private, created_at
                FROM user_book_notes 
                WHERE user_id = :userId AND book_isbn = :isbn
                ORDER BY page_number ASC, created_at ASC
            ");
            $stmt->execute([':userId' => $userId, ':isbn' => $isbn]);

            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group by page number
            $groupedNotes = [];
            foreach ($notes as $note) {
                $pageNumber = (int) $note['page_number'];
                if (!isset($groupedNotes[$pageNumber])) {
                    $groupedNotes[$pageNumber] = [];
                }
                $groupedNotes[$pageNumber][] = $note;
            }

            return $groupedNotes;

        } catch (PDOException $e) {
            $this->logError('Error getting notes by page', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return [];
        }
    }

    public function add(
        int $userId,
        string $isbn,
        int $pageNumber,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_book_notes 
                (user_id, book_isbn, page_number, note_text, note_type, is_private, created_at) 
                VALUES (:userId, :isbn, :pageNumber, :noteText, :noteType, :isPrivate, NOW())
            ");
            $stmt->execute([
                ':userId' => $userId,
                ':isbn' => $isbn,
                ':pageNumber' => $pageNumber,
                ':noteText' => $noteText,
                ':noteType' => $noteType,
                ':isPrivate' => $isPrivate ? 1 : 0
            ]);

            $noteId = (int) $this->db->lastInsertId();
            $this->logInfo('Book note added', ['userId' => $userId, 'isbn' => $isbn, 'noteId' => $noteId]);

            return $noteId;

        } catch (PDOException $e) {
            $this->logError('Error adding book note', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not add book note: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $userId, int $noteId): bool
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM user_book_notes 
                WHERE id = :noteId AND user_id = :userId
            ");
            $stmt->execute([':noteId' => $noteId, ':userId' => $userId]);

            $deleted = $stmt->rowCount() > 0;

            if ($deleted) {
                $this->logInfo('Book note deleted', ['userId' => $userId, 'noteId' => $noteId]);
            }

            return $deleted;

        } catch (PDOException $e) {
            $this->logError('Error deleting book note', $e, ['userId' => $userId, 'noteId' => $noteId]);
            throw new RuntimeException("Could not delete book note: " . $e->getMessage(), 0, $e);
        }
    }

    public function update(
        int $userId,
        int $noteId,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_book_notes 
                SET note_text = :noteText, note_type = :noteType, is_private = :isPrivate
                WHERE id = :noteId AND user_id = :userId
            ");
            $stmt->execute([
                ':noteId' => $noteId,
                ':userId' => $userId,
                ':noteText' => $noteText,
                ':noteType' => $noteType,
                ':isPrivate' => $isPrivate ? 1 : 0
            ]);

            $updated = $stmt->rowCount() > 0;

            if ($updated) {
                $this->logInfo('Book note updated', ['userId' => $userId, 'noteId' => $noteId]);
            }

            return $updated;

        } catch (PDOException $e) {
            $this->logError('Error updating book note', $e, ['userId' => $userId, 'noteId' => $noteId]);
            throw new RuntimeException("Could not update book note: " . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
