<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Model\Work;
use App\Domain\Repository\Book\WorkRepositoryInterface;
use App\Infrastructure\Persistence\Book\Mappers\WorkDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Work entity CRUD operations
 */
final class MySqlWorkRepository implements WorkRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly WorkDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function findByOpenLibraryKey(string $workKey): ?Work
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM book_works WHERE openlibrary_work_key = :work_key LIMIT 1'
            );
            $stmt->execute([':work_key' => $workKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding work by OpenLibrary key', $e, ['work_key' => $workKey]);
            throw new RuntimeException("Could not find work: " . $e->getMessage(), 0, $e);
        }
    }

    public function findBySyntheticKey(string $syntheticKey): ?Work
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM book_works WHERE synthetic_work_key = :synthetic_key LIMIT 1'
            );
            $stmt->execute([':synthetic_key' => $syntheticKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding work by synthetic key', $e, ['synthetic_key' => $syntheticKey]);
            throw new RuntimeException("Could not find work: " . $e->getMessage(), 0, $e);
        }
    }

    public function findByTitleAndAuthors(string $title, array $authors): ?Work
    {
        try {
            $authorsJson = json_encode($authors);
            
            $stmt = $this->db->prepare(
                'SELECT * FROM book_works 
                 WHERE title = :title 
                 AND JSON_CONTAINS(authors, :authors, \'$\')
                 LIMIT 1'
            );
            $stmt->execute([
                ':title' => $title,
                ':authors' => $authorsJson
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding work by title and authors', $e, [
                'title' => $title,
                'authors' => $authors
            ]);
            throw new RuntimeException("Could not find work: " . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $workId): ?Work
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM book_works WHERE work_id = :work_id LIMIT 1');
            $stmt->execute([':work_id' => $workId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding work by ID', $e, ['work_id' => $workId]);
            throw new RuntimeException("Could not find work: " . $e->getMessage(), 0, $e);
        }
    }

    public function save(Work $work): Work
    {
        try {
            $data = $this->mapper->toDatabase($work);

            if ($work->getWorkId() === null) {
                // Insert new work
                $stmt = $this->db->prepare(
                    'INSERT INTO book_works (
                        openlibrary_work_key,
                        synthetic_work_key,
                        title,
                        subtitle,
                        authors,
                        description,
                        subjects,
                        first_publish_year,
                        original_language,
                        is_synthetic,
                        needs_review,
                        manually_edited,
                        manually_edited_fields
                    ) VALUES (
                        :openlibrary_work_key,
                        :synthetic_work_key,
                        :title,
                        :subtitle,
                        :authors,
                        :description,
                        :subjects,
                        :first_publish_year,
                        :original_language,
                        :is_synthetic,
                        :needs_review,
                        :manually_edited,
                        :manually_edited_fields
                    )'
                );

                $stmt->execute([
                    ':openlibrary_work_key' => $data['openlibrary_work_key'],
                    ':synthetic_work_key' => $data['synthetic_work_key'],
                    ':title' => $data['title'],
                    ':subtitle' => $data['subtitle'],
                    ':authors' => $data['authors'],
                    ':description' => $data['description'],
                    ':subjects' => $data['subjects'],
                    ':first_publish_year' => $data['first_publish_year'],
                    ':original_language' => $data['original_language'],
                    ':is_synthetic' => $data['is_synthetic'],
                    ':needs_review' => $data['needs_review'],
                    ':manually_edited' => $data['manually_edited'],
                    ':manually_edited_fields' => $data['manually_edited_fields'],
                ]);

                $work->setWorkId((int) $this->db->lastInsertId());
                $this->logInfo('Work created', ['work_id' => $work->getWorkId()]);

            } else {
                // Update existing work
                $stmt = $this->db->prepare(
                    'UPDATE book_works SET
                        openlibrary_work_key = :openlibrary_work_key,
                        synthetic_work_key = :synthetic_work_key,
                        title = :title,
                        subtitle = :subtitle,
                        authors = :authors,
                        description = :description,
                        subjects = :subjects,
                        first_publish_year = :first_publish_year,
                        original_language = :original_language,
                        is_synthetic = :is_synthetic,
                        needs_review = :needs_review,
                        manually_edited = :manually_edited,
                        manually_edited_fields = :manually_edited_fields,
                        updated_at = NOW()
                     WHERE work_id = :work_id'
                );

                $stmt->execute([
                    ':work_id' => $work->getWorkId(),
                    ':openlibrary_work_key' => $data['openlibrary_work_key'],
                    ':synthetic_work_key' => $data['synthetic_work_key'],
                    ':title' => $data['title'],
                    ':subtitle' => $data['subtitle'],
                    ':authors' => $data['authors'],
                    ':description' => $data['description'],
                    ':subjects' => $data['subjects'],
                    ':first_publish_year' => $data['first_publish_year'],
                    ':original_language' => $data['original_language'],
                    ':is_synthetic' => $data['is_synthetic'],
                    ':needs_review' => $data['needs_review'],
                    ':manually_edited' => $data['manually_edited'],
                    ':manually_edited_fields' => $data['manually_edited_fields'],
                ]);

                $this->logInfo('Work updated', ['work_id' => $work->getWorkId()]);
            }

            return $work;

        } catch (PDOException $e) {
            $this->logError('Error saving work', $e, ['work' => $work->toArray()]);
            throw new RuntimeException("Could not save work: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $workId): bool
    {
        try {
            // Check if work has editions
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM book_editions WHERE work_id = :work_id');
            $stmt->execute([':work_id' => $workId]);
            $editionCount = (int) $stmt->fetchColumn();

            if ($editionCount > 0) {
                throw new RuntimeException("Cannot delete work with existing editions");
            }

            $stmt = $this->db->prepare('DELETE FROM book_works WHERE work_id = :work_id');
            $stmt->execute([':work_id' => $workId]);

            $deleted = $stmt->rowCount() > 0;
            if ($deleted) {
                $this->logInfo('Work deleted', ['work_id' => $workId]);
            }

            return $deleted;

        } catch (PDOException $e) {
            $this->logError('Error deleting work', $e, ['work_id' => $workId]);
            throw new RuntimeException("Could not delete work: " . $e->getMessage(), 0, $e);
        }
    }

    public function search(string $query, int $limit = 20): array
    {
        try {
            $searchTerm = '%' . $query . '%';
            
            $stmt = $this->db->prepare(
                'SELECT * FROM book_works 
                 WHERE title LIKE :query 
                 OR JSON_SEARCH(authors, \'one\', :query) IS NOT NULL
                 ORDER BY title ASC
                 LIMIT :limit'
            );
            
            $stmt->bindValue(':query', $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map([$this->mapper, 'toDomain'], $rows);

        } catch (PDOException $e) {
            $this->logError('Error searching works', $e, ['query' => $query]);
            throw new RuntimeException("Could not search works: " . $e->getMessage(), 0, $e);
        }
    }
}
