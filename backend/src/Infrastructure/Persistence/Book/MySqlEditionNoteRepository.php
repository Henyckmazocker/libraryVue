<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Model\EditionNote;
use App\Domain\Repository\Book\EditionNoteRepositoryInterface;
use App\Infrastructure\Persistence\Book\Mappers\EditionNoteDataMapper;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for EditionNote repository
 * Handles user notes on book editions
 */
final class MySqlEditionNoteRepository implements EditionNoteRepositoryInterface
{
    public function __construct(
        private readonly PDO $db,
        private readonly EditionNoteDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function findByUserEdition(
        int $userId,
        int $userEditionId,
        ?string $noteType = null,
        ?int $pageNumber = null
    ): array {
        try {
            $sql = 'SELECT * FROM user_edition_notes 
                    WHERE user_id = :user_id AND user_edition_id = :user_edition_id';
            
            $params = [
                ':user_id' => $userId,
                ':user_edition_id' => $userEditionId
            ];

            if ($noteType !== null) {
                $sql .= ' AND note_type = :note_type';
                $params[':note_type'] = $noteType;
            }

            if ($pageNumber !== null) {
                $sql .= ' AND page_number = :page_number';
                $params[':page_number'] = $pageNumber;
            }

            $sql .= ' ORDER BY page_number ASC, created_at ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->mapper->toDomainCollection($rows);

        } catch (PDOException $e) {
            $this->logger->error('Error finding edition notes', [
                'user_id' => $userId,
                'user_edition_id' => $userEditionId,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Could not find edition notes: " . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $noteId, int $userId): ?EditionNote
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM user_edition_notes 
                 WHERE id = :id AND user_id = :user_id 
                 LIMIT 1'
            );
            $stmt->execute([
                ':id' => $noteId,
                ':user_id' => $userId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logger->error('Error finding edition note by ID', [
                'note_id' => $noteId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Could not find edition note: " . $e->getMessage(), 0, $e);
        }
    }

    public function add(EditionNote $note): EditionNote
    {
        try {
            $data = $this->mapper->toDatabase($note);

            $stmt = $this->db->prepare(
                'INSERT INTO user_edition_notes 
                 (user_id, user_edition_id, page_number, note_text, note_type, is_private) 
                 VALUES 
                 (:user_id, :user_edition_id, :page_number, :note_text, :note_type, :is_private)'
            );

            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':user_edition_id' => $data['user_edition_id'],
                ':page_number' => $data['page_number'],
                ':note_text' => $data['note_text'],
                ':note_type' => $data['note_type'],
                ':is_private' => $data['is_private']
            ]);

            $noteId = (int) $this->db->lastInsertId();
            $note->setId($noteId);

            $this->logger->info('Edition note added', [
                'note_id' => $noteId,
                'user_edition_id' => $note->getUserEditionId(),
                'page_number' => $note->getPageNumber()
            ]);

            return $note;

        } catch (PDOException $e) {
            $this->logger->error('Error adding edition note', [
                'user_edition_id' => $note->getUserEditionId(),
                'page_number' => $note->getPageNumber(),
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Could not add edition note: " . $e->getMessage(), 0, $e);
        }
    }

    public function update(EditionNote $note): EditionNote
    {
        try {
            $data = $this->mapper->toDatabase($note);

            $stmt = $this->db->prepare(
                'UPDATE user_edition_notes 
                 SET page_number = :page_number,
                     note_text = :note_text,
                     note_type = :note_type,
                     is_private = :is_private,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id'
            );

            $stmt->execute([
                ':id' => $data['id'],
                ':user_id' => $data['user_id'],
                ':page_number' => $data['page_number'],
                ':note_text' => $data['note_text'],
                ':note_type' => $data['note_type'],
                ':is_private' => $data['is_private']
            ]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Note not found or user is not the owner');
            }

            $this->logger->info('Edition note updated', [
                'note_id' => $note->getId(),
                'user_edition_id' => $note->getUserEditionId()
            ]);

            return $note;

        } catch (PDOException $e) {
            $this->logger->error('Error updating edition note', [
                'note_id' => $note->getId(),
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Could not update edition note: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $noteId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM user_edition_notes 
                 WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                ':id' => $noteId,
                ':user_id' => $userId
            ]);

            $success = $stmt->rowCount() > 0;

            if ($success) {
                $this->logger->info('Edition note deleted', [
                    'note_id' => $noteId,
                    'user_id' => $userId
                ]);
            }

            return $success;

        } catch (PDOException $e) {
            $this->logger->error('Error deleting edition note', [
                'note_id' => $noteId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Could not delete edition note: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteAllByUserEdition(int $userEditionId): bool
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM user_edition_notes WHERE user_edition_id = :user_edition_id'
            );
            $stmt->execute([':user_edition_id' => $userEditionId]);

            $this->logger->info('All edition notes deleted for user edition', [
                'user_edition_id' => $userEditionId,
                'count' => $stmt->rowCount()
            ]);

            return true;

        } catch (PDOException $e) {
            $this->logger->error('Error deleting all edition notes', [
                'user_edition_id' => $userEditionId,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Could not delete edition notes: " . $e->getMessage(), 0, $e);
        }
    }

    public function countByUserEdition(int $userEditionId): int
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) as count 
                 FROM user_edition_notes 
                 WHERE user_edition_id = :user_edition_id'
            );
            $stmt->execute([':user_edition_id' => $userEditionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) ($result['count'] ?? 0);

        } catch (PDOException $e) {
            $this->logger->error('Error counting edition notes', [
                'user_edition_id' => $userEditionId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}
