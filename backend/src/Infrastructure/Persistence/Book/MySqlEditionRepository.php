<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Model\Edition;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Infrastructure\Persistence\Book\Mappers\EditionDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Edition entity CRUD operations
 */
final class MySqlEditionRepository implements EditionRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly EditionDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function findByOpenLibraryKey(string $editionKey): ?Edition
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM book_editions WHERE openlibrary_edition_key = :edition_key LIMIT 1'
            );
            $stmt->execute([':edition_key' => $editionKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding edition by OpenLibrary key', $e, ['edition_key' => $editionKey]);
            throw new RuntimeException("Could not find edition: " . $e->getMessage(), 0, $e);
        }
    }

    public function findByIsbn13(string $isbn13): ?Edition
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM book_editions WHERE isbn_13 = :isbn13 LIMIT 1');
            $stmt->execute([':isbn13' => $isbn13]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding edition by ISBN-13', $e, ['isbn13' => $isbn13]);
            throw new RuntimeException("Could not find edition: " . $e->getMessage(), 0, $e);
        }
    }

    public function findByIsbn10(string $isbn10): ?Edition
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM book_editions WHERE isbn_10 = :isbn10 LIMIT 1');
            $stmt->execute([':isbn10' => $isbn10]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding edition by ISBN-10', $e, ['isbn10' => $isbn10]);
            throw new RuntimeException("Could not find edition: " . $e->getMessage(), 0, $e);
        }
    }

    public function findByIsbn(string $isbn): ?Edition
    {
        // Try ISBN-13 first (more common)
        $edition = $this->findByIsbn13($isbn);
        if ($edition) {
            return $edition;
        }

        // Try ISBN-10
        return $this->findByIsbn10($isbn);
    }

    public function findById(int $editionId): ?Edition
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM book_editions WHERE edition_id = :edition_id LIMIT 1');
            $stmt->execute([':edition_id' => $editionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding edition by ID', $e, ['edition_id' => $editionId]);
            throw new RuntimeException("Could not find edition: " . $e->getMessage(), 0, $e);
        }
    }

    public function findByWorkId(int $workId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM book_editions 
                 WHERE work_id = :work_id 
                 ORDER BY publish_year DESC, edition_id ASC'
            );
            $stmt->execute([':work_id' => $workId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map([$this->mapper, 'toDomain'], $rows);

        } catch (PDOException $e) {
            $this->logError('Error finding editions by work ID', $e, ['work_id' => $workId]);
            throw new RuntimeException("Could not find editions: " . $e->getMessage(), 0, $e);
        }
    }

    public function getFirstEditionForWork(int $workId): ?Edition
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM book_editions 
                 WHERE work_id = :work_id 
                 ORDER BY publish_year ASC, edition_id ASC 
                 LIMIT 1'
            );
            $stmt->execute([':work_id' => $workId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding first edition for work', $e, ['work_id' => $workId]);
            throw new RuntimeException("Could not find first edition: " . $e->getMessage(), 0, $e);
        }
    }

    public function save(Edition $edition): Edition
    {
        try {
            $data = $this->mapper->toDatabase($edition);

            if ($edition->getEditionId() === null) {
                // Insert new edition
                $stmt = $this->db->prepare(
                    'INSERT INTO book_editions (
                        work_id,
                        openlibrary_edition_key,
                        isbn_13,
                        isbn_10,
                        google_books_id,
                        title,
                        subtitle,
                        publisher,
                        publish_date,
                        publish_year,
                        publish_place,
                        format,
                        pages,
                        description,
                        languages,
                        illustrators,
                        translators,
                        cover_url_small,
                        cover_url_medium,
                        cover_url_large,
                        covers,
                        data_source
                    ) VALUES (
                        :work_id,
                        :openlibrary_edition_key,
                        :isbn_13,
                        :isbn_10,
                        :google_books_id,
                        :title,
                        :subtitle,
                        :publisher,
                        :publish_date,
                        :publish_year,
                        :publish_place,
                        :format,
                        :pages,
                        :description,
                        :languages,
                        :illustrators,
                        :translators,
                        :cover_url_small,
                        :cover_url_medium,
                        :cover_url_large,
                        :covers,
                        :data_source
                    )'
                );

                $stmt->execute([
                    ':work_id' => $data['work_id'],
                    ':openlibrary_edition_key' => $data['openlibrary_edition_key'],
                    ':isbn_13' => $data['isbn_13'],
                    ':isbn_10' => $data['isbn_10'],
                    ':google_books_id' => $data['google_books_id'],
                    ':title' => $data['title'],
                    ':subtitle' => $data['subtitle'],
                    ':publisher' => $data['publisher'],
                    ':publish_date' => $data['publish_date'],
                    ':publish_year' => $data['publish_year'],
                    ':publish_place' => $data['publish_place'],
                    ':format' => $data['format'],
                    ':pages' => $data['pages'],
                    ':description' => $data['description'],
                    ':languages' => $data['languages'],
                    ':illustrators' => $data['illustrators'],
                    ':translators' => $data['translators'],
                    ':cover_url_small' => $data['cover_url_small'],
                    ':cover_url_medium' => $data['cover_url_medium'],
                    ':cover_url_large' => $data['cover_url_large'],
                    ':covers' => $data['covers'],
                    ':data_source' => $data['data_source'],
                ]);

                $edition->setEditionId((int) $this->db->lastInsertId());
                $this->logInfo('Edition created', ['edition_id' => $edition->getEditionId()]);

            } else {
                // Update existing edition
                $stmt = $this->db->prepare(
                    'UPDATE book_editions SET
                        work_id = :work_id,
                        openlibrary_edition_key = :openlibrary_edition_key,
                        isbn_13 = :isbn_13,
                        isbn_10 = :isbn_10,
                        google_books_id = :google_books_id,
                        title = :title,
                        subtitle = :subtitle,
                        publisher = :publisher,
                        publish_date = :publish_date,
                        publish_year = :publish_year,
                        publish_place = :publish_place,
                        format = :format,
                        pages = :pages,
                        description = :description,
                        languages = :languages,
                        illustrators = :illustrators,
                        translators = :translators,
                        cover_url_small = :cover_url_small,
                        cover_url_medium = :cover_url_medium,
                        cover_url_large = :cover_url_large,
                        covers = :covers,
                        data_source = :data_source,
                        updated_at = NOW()
                     WHERE edition_id = :edition_id'
                );

                $stmt->execute([
                    ':edition_id' => $edition->getEditionId(),
                    ':work_id' => $data['work_id'],
                    ':openlibrary_edition_key' => $data['openlibrary_edition_key'],
                    ':isbn_13' => $data['isbn_13'],
                    ':isbn_10' => $data['isbn_10'],
                    ':google_books_id' => $data['google_books_id'],
                    ':title' => $data['title'],
                    ':subtitle' => $data['subtitle'],
                    ':publisher' => $data['publisher'],
                    ':publish_date' => $data['publish_date'],
                    ':publish_year' => $data['publish_year'],
                    ':publish_place' => $data['publish_place'],
                    ':format' => $data['format'],
                    ':pages' => $data['pages'],
                    ':description' => $data['description'],
                    ':languages' => $data['languages'],
                    ':illustrators' => $data['illustrators'],
                    ':translators' => $data['translators'],
                    ':cover_url_small' => $data['cover_url_small'],
                    ':cover_url_medium' => $data['cover_url_medium'],
                    ':cover_url_large' => $data['cover_url_large'],
                    ':covers' => $data['covers'],
                    ':data_source' => $data['data_source'],
                ]);

                $this->logInfo('Edition updated', ['edition_id' => $edition->getEditionId()]);
            }

            return $edition;

        } catch (PDOException $e) {
            $this->logError('Error saving edition', $e, ['edition' => $edition->toArray()]);
            throw new RuntimeException("Could not save edition: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $editionId): bool
    {
        try {
            // Check if edition is referenced in user_book_editions
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_book_editions WHERE edition_id = :edition_id');
            $stmt->execute([':edition_id' => $editionId]);
            $userEditionCount = (int) $stmt->fetchColumn();

            if ($userEditionCount > 0) {
                throw new RuntimeException("Cannot delete edition that is in user libraries");
            }

            $stmt = $this->db->prepare('DELETE FROM book_editions WHERE edition_id = :edition_id');
            $stmt->execute([':edition_id' => $editionId]);

            $deleted = $stmt->rowCount() > 0;
            if ($deleted) {
                $this->logInfo('Edition deleted', ['edition_id' => $editionId]);
            }

            return $deleted;

        } catch (PDOException $e) {
            $this->logError('Error deleting edition', $e, ['edition_id' => $editionId]);
            throw new RuntimeException("Could not delete edition: " . $e->getMessage(), 0, $e);
        }
    }
}
