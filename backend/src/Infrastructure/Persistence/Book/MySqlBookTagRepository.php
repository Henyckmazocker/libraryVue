<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Repository\Book\BookTagRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Book Tag management
 * Handles user-defined tags for books
 */
final class MySqlBookTagRepository implements BookTagRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function getByUser(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, color 
                FROM user_book_tags 
                WHERE user_id = :userId 
                ORDER BY name ASC
            ");
            $stmt->execute([':userId' => $userId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->logError('Error getting tags by user', $e, ['userId' => $userId]);
            return [];
        }
    }

    public function getByBook(int $userId, string $isbn): array
    {
        try {
            // First find the edition_id from ISBN
            $stmtEdition = $this->db->prepare("
                SELECT edition_id FROM book_editions 
                WHERE isbn_13 = :isbn OR isbn_10 = :isbn2 
                LIMIT 1
            ");
            $stmtEdition->execute([':isbn' => $isbn, ':isbn2' => $isbn]);
            $edition = $stmtEdition->fetch(PDO::FETCH_ASSOC);
            
            if (!$edition) {
                return [];
            }
            
            $editionId = (int) $edition['edition_id'];
            
            $stmt = $this->db->prepare("
                SELECT ubt.id, ubt.name, ubt.color 
                FROM user_book_tags ubt
                INNER JOIN user_book_tag_assignments ubta 
                    ON ubt.id = ubta.tag_id
                WHERE ubt.user_id = :userId AND ubta.edition_id = :editionId
                ORDER BY ubt.name ASC
            ");
            $stmt->execute([':userId' => $userId, ':editionId' => $editionId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->logError('Error getting tags by book', $e, ['userId' => $userId, 'isbn' => $isbn]);
            return [];
        }
    }

    public function create(int $userId, string $name, string $color = '#007bff'): int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_book_tags (user_id, name, color) 
                VALUES (:userId, :name, :color)
            ");
            $stmt->execute([
                ':userId' => $userId,
                ':name' => $name,
                ':color' => $color
            ]);

            $tagId = (int) $this->db->lastInsertId();
            $this->logInfo('Book tag created', ['userId' => $userId, 'tagId' => $tagId, 'name' => $name]);

            return $tagId;

        } catch (PDOException $e) {
            // Handle duplicate tag name (return existing ID)
            if ($e->getCode() === '23000') {
                $stmt = $this->db->prepare("
                    SELECT id FROM user_book_tags 
                    WHERE user_id = :userId AND name = :name
                ");
                $stmt->execute([':userId' => $userId, ':name' => $name]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    $this->logInfo('Tag already exists, returning existing ID', ['userId' => $userId, 'name' => $name]);
                    return (int) $result['id'];
                }
            }

            $this->logError('Error creating book tag', $e, ['userId' => $userId, 'name' => $name]);
            throw new RuntimeException("Could not create book tag: " . $e->getMessage(), 0, $e);
        }
    }

    public function assign(int $userId, string $isbn, int $tagId): void
    {
        try {
            // First find the edition_id from ISBN
            $stmtEdition = $this->db->prepare("
                SELECT edition_id FROM book_editions 
                WHERE isbn_13 = :isbn OR isbn_10 = :isbn2 
                LIMIT 1
            ");
            $stmtEdition->execute([':isbn' => $isbn, ':isbn2' => $isbn]);
            $edition = $stmtEdition->fetch(PDO::FETCH_ASSOC);
            
            if (!$edition) {
                throw new RuntimeException("Edition not found for ISBN: {$isbn}");
            }
            
            $editionId = (int) $edition['edition_id'];
            
            $stmt = $this->db->prepare("
                INSERT INTO user_book_tag_assignments (user_id, edition_id, tag_id) 
                VALUES (:userId, :editionId, :tagId)
            ");
            $stmt->execute([
                ':userId' => $userId,
                ':editionId' => $editionId,
                ':tagId' => $tagId
            ]);

            $this->logInfo('Tag assigned to book', ['userId' => $userId, 'isbn' => $isbn, 'tagId' => $tagId]);

        } catch (PDOException $e) {
            // Silently ignore duplicate assignments
            if ($e->getCode() !== '23000') {
                $this->logError('Error assigning tag to book', $e, ['userId' => $userId, 'isbn' => $isbn, 'tagId' => $tagId]);
                throw new RuntimeException("Could not assign tag to book: " . $e->getMessage(), 0, $e);
            }
        }
    }

    public function removeAll(int $userId, string $isbn): void
    {
        try {
            // First find the edition_id from ISBN
            $stmtEdition = $this->db->prepare("
                SELECT edition_id FROM book_editions 
                WHERE isbn_13 = :isbn OR isbn_10 = :isbn2 
                LIMIT 1
            ");
            $stmtEdition->execute([':isbn' => $isbn, ':isbn2' => $isbn]);
            $edition = $stmtEdition->fetch(PDO::FETCH_ASSOC);
            
            if (!$edition) {
                // If no edition found, nothing to remove
                $this->logInfo('No edition found for ISBN, nothing to remove', ['userId' => $userId, 'isbn' => $isbn]);
                return;
            }
            
            $editionId = (int) $edition['edition_id'];
            
            $stmt = $this->db->prepare("
                DELETE FROM user_book_tag_assignments 
                WHERE user_id = :userId AND edition_id = :editionId
            ");
            $stmt->execute([':userId' => $userId, ':editionId' => $editionId]);

            $this->logInfo('All tags removed from book', ['userId' => $userId, 'isbn' => $isbn]);

        } catch (PDOException $e) {
            $this->logError('Error removing tags from book', $e, ['userId' => $userId, 'isbn' => $isbn]);
            throw new RuntimeException("Could not remove tags from book: " . $e->getMessage(), 0, $e);
        }
    }

    public function getAllowedTags(int $userId): array
    {
        return $this->getByUser($userId);
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
