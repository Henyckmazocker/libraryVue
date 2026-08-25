<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Movie;

use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Movie Note management
 * Handles user-specific movie notes
 */
final class MySqlMovieNoteRepository implements MovieNoteRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function getByPage(int $userId, string $movieId): array
    {
        try {
            $sql = 'SELECT id, page_number, note_text, note_type, is_private, created_at
                    FROM user_movie_notes
                    WHERE user_id = :userId AND movie_isbn = :movieId
                    ORDER BY page_number ASC, created_at DESC';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieId
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('DB Error getting movie notes by page', $e, [
                'userId' => $userId,
                'movieId' => $movieId
            ]);
            throw new RuntimeException('Could not get movie notes by page. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * El parámetro se llama `$movieIsbn` y NO es cosmético: PHP liga los
     * argumentos nombrados a los de la **clase concreta**, no a los de la
     * interfaz. Con `$movieId` aquí y `$movieIsbn` en
     * `MovieNoteRepositoryInterface:34`, la llamada de
     * `AddMovieNoteUseCase:51-57` moría con
     * `Error: Unknown named parameter $movieIsbn` — es decir, **añadir una nota
     * a una película estaba roto en producción**. Los tests unitarios no lo
     * veían porque mockean la interfaz, y los mocks usan sus nombres. Lo
     * destapó la suite de integración el 2026-08-25.
     */
    public function add(int $userId, string $movieIsbn, string $noteText, string $noteType = 'note', bool $isPrivate = true): int
    {
        try {
            $sql = 'INSERT INTO user_movie_notes (user_id, movie_isbn, note_text, note_type, is_private) 
                    VALUES (:userId, :movieId, :noteText, :noteType, :isPrivate)';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId' => $userId,
                ':movieId' => $movieIsbn,
                ':noteText' => $noteText,
                ':noteType' => $noteType,
                ':isPrivate' => $isPrivate ? 1 : 0
            ]);
            
            $noteId = (int)$this->db->lastInsertId();
            
            $this->logInfo('Movie note added successfully', [
                'user_id' => $userId,
                'movie_id' => $movieIsbn,
                'note_id' => $noteId,
                'note_type' => $noteType
            ]);
            
            return $noteId;
        } catch (PDOException $e) {
            $this->logError('DB Error adding movie note', $e, [
                'userId' => $userId,
                'movieId' => $movieIsbn
            ]);
            throw new RuntimeException('Could not add movie note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $noteId, int $userId): bool
    {
        try {
            $sql = 'DELETE FROM user_movie_notes WHERE id = :noteId AND user_id = :userId';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':noteId' => $noteId,
                ':userId' => $userId
            ]);
            
            $deleted = $stmt->rowCount() > 0;
            
            if ($deleted) {
                $this->logInfo('Movie note deleted successfully', [
                    'user_id' => $userId,
                    'note_id' => $noteId
                ]);
            }
            
            return $deleted;
        } catch (PDOException $e) {
            $this->logError('DB Error deleting movie note', $e, [
                'userId' => $userId,
                'noteId' => $noteId
            ]);
            throw new RuntimeException('Could not delete movie note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(int $noteId, int $userId, string $noteText, ?string $noteType = null, ?bool $isPrivate = null): bool
    {
        try {
            $updates = ['note_text = :noteText'];
            $params = [
                ':noteText' => $noteText,
                ':noteId' => $noteId,
                ':userId' => $userId
            ];
            
            if ($noteType !== null) {
                $updates[] = 'note_type = :noteType';
                $params[':noteType'] = $noteType;
            }
            
            if ($isPrivate !== null) {
                $updates[] = 'is_private = :isPrivate';
                $params[':isPrivate'] = $isPrivate ? 1 : 0;
            }
            
            $sql = 'UPDATE user_movie_notes 
                    SET ' . implode(', ', $updates) . '
                    WHERE id = :noteId AND user_id = :userId';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $updated = $stmt->rowCount() > 0;
            
            if ($updated) {
                $this->logInfo('Movie note updated successfully', [
                    'user_id' => $userId,
                    'note_id' => $noteId
                ]);
            }
            
            return $updated;
        } catch (PDOException $e) {
            $this->logError('DB Error updating movie note', $e, [
                'userId' => $userId,
                'noteId' => $noteId
            ]);
            throw new RuntimeException('Could not update movie note. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
