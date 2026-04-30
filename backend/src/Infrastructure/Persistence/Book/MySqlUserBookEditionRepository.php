<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book;

use App\Domain\Model\UserBookEdition;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Infrastructure\Persistence\Book\Mappers\UserBookEditionDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use InvalidArgumentException;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for UserBookEdition entity operations
 * Handles user's personal library of book editions
 */
final class MySqlUserBookEditionRepository implements UserBookEditionRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE = 'book_statuses';
    private const STATUS_LINK_TABLE = 'user_book_statuses';
    private const STATUS_COLUMN = 'user_edition_id';

    public function __construct(
        private readonly PDO $db,
        private readonly UserBookEditionDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    protected function getDatabase(): PDO
    {
        return $this->db;
    }

    protected function getStatusTableName(): string
    {
        return self::STATUS_TABLE;
    }

    protected function getEntityStatusTableName(): string
    {
        return self::STATUS_LINK_TABLE;
    }

    protected function getEntityIdColumnName(): string
    {
        return self::STATUS_COLUMN;
    }

    public function findByUserAndEdition(int $userId, int $editionId): ?UserBookEdition
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT ube.*, iof.id AS ownership_format_id, iof.value AS ownership_format_value, iof.label AS ownership_format_label
                 FROM user_book_editions ube
                 LEFT JOIN item_owned_formats iof ON iof.id = ube.ownership_format_id
                 WHERE ube.user_id = :user_id AND ube.edition_id = :edition_id 
                 LIMIT 1'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':edition_id' => $editionId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding user book edition', $e, [
                'user_id' => $userId,
                'edition_id' => $editionId
            ]);
            throw new RuntimeException("Could not find user book edition: " . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $id): ?UserBookEdition
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT ube.*, iof.id AS ownership_format_id, iof.value AS ownership_format_value, iof.label AS ownership_format_label
                 FROM user_book_editions ube
                 LEFT JOIN item_owned_formats iof ON iof.id = ube.ownership_format_id
                 WHERE ube.id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $this->mapper->toDomain($row) : null;

        } catch (PDOException $e) {
            $this->logError('Error finding user book edition by ID', $e, ['id' => $id]);
            throw new RuntimeException("Could not find user book edition: " . $e->getMessage(), 0, $e);
        }
    }

    public function findByUser(int $userId, array $filters = []): array
    {
        try {
            $sql = 'SELECT ube.*, iof.id AS ownership_format_id, iof.value AS ownership_format_value, iof.label AS ownership_format_label
                    FROM user_book_editions ube
                    LEFT JOIN item_owned_formats iof ON iof.id = ube.ownership_format_id
                    WHERE ube.user_id = :user_id';
            $params = [':user_id' => $userId];

            // Apply filters if needed
            if (isset($filters['ownership_type'])) {
                $sql .= ' AND ube.ownership_type = :ownership_type';
                $params[':ownership_type'] = $filters['ownership_type'];
            }

            $sql .= ' ORDER BY ube.added_at DESC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map([$this->mapper, 'toDomain'], $rows);

        } catch (PDOException $e) {
            $this->logError('Error finding user book editions', $e, ['user_id' => $userId]);
            throw new RuntimeException("Could not find user book editions: " . $e->getMessage(), 0, $e);
        }
    }

    public function hasEdition(int $userId, int $editionId): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) as count 
                 FROM user_book_editions 
                 WHERE user_id = :userId AND edition_id = :edition_id'
            );
            $stmt->execute([':userId' => $userId, ':edition_id' => $editionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && (int) $result['count'] > 0;

        } catch (PDOException $e) {
            $this->logError('Error checking if user has edition', $e, [
                'user_id' => $userId,
                'edition_id' => $editionId
            ]);
            return false;
        }
    }

    public function add(int $userId, int $editionId, array $statuses = [], ?int $ownershipFormatId = null): UserBookEdition
    {
        $this->db->beginTransaction();
        try {
            // Verify edition exists
            $checkEdition = $this->db->prepare('SELECT edition_id FROM book_editions WHERE edition_id = :edition_id');
            $checkEdition->execute([':edition_id' => $editionId]);

            if (!$checkEdition->fetch()) {
                throw new RuntimeException("Edition with ID {$editionId} does not exist.");
            }

            // Create user book edition
            $stmt = $this->db->prepare(
                'INSERT INTO user_book_editions (user_id, edition_id, added_at, current_page, ownership_format_id) 
                 VALUES (:userId, :edition_id, NOW(), 0, :ownership_format_id)'
            );
            $stmt->execute([':userId' => $userId, ':edition_id' => $editionId, ':ownership_format_id' => $ownershipFormatId]);

            $userBookEditionId = (int) $this->db->lastInsertId();

            // Add statuses if provided
            if (!empty($statuses)) {
                $this->updateStatuses($userId, $editionId, $statuses);
            }

            $this->db->commit();
            $this->logInfo('User book edition added', [
                'user_id' => $userId,
                'edition_id' => $editionId,
                'id' => $userBookEditionId
            ]);

            // Fetch and return the created entity
            return $this->findById($userBookEditionId);

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error adding user book edition', $e, [
                'user_id' => $userId,
                'edition_id' => $editionId
            ]);
            throw new RuntimeException("Could not add edition to user library: " . $e->getMessage(), 0, $e);
        }
    }

    public function save(UserBookEdition $userBookEdition): UserBookEdition
    {
        try {
            $data = $this->mapper->toDatabase($userBookEdition);

            if ($userBookEdition->getId() === null) {
                // Insert new
                $stmt = $this->db->prepare(
                    'INSERT INTO user_book_editions (
                        user_id,
                        edition_id,
                        added_at,
                        consumed_at,
                        current_page,
                        active_reading_session_id,
                        edition_rating,
                        work_rating,
                        ownership_type,
                        `condition`,
                        location,
                        is_digital,
                        total_sessions_completed,
                        personal_notes,
                        ownership_format_id
                    ) VALUES (
                        :user_id,
                        :edition_id,
                        :added_at,
                        :consumed_at,
                        :current_page,
                        :active_reading_session_id,
                        :edition_rating,
                        :work_rating,
                        :ownership_type,
                        :condition,
                        :location,
                        :is_digital,
                        :total_sessions_completed,
                        :personal_notes,
                        :ownership_format_id
                    )'
                );

                $stmt->execute([
                    ':user_id' => $data['user_id'],
                    ':edition_id' => $data['edition_id'],
                    ':added_at' => $data['added_at'],
                    ':consumed_at' => $data['consumed_at'],
                    ':current_page' => $data['current_page'],
                    ':active_reading_session_id' => $data['active_reading_session_id'],
                    ':edition_rating' => $data['edition_rating'],
                    ':work_rating' => $data['work_rating'],
                    ':ownership_type' => $data['ownership_type'],
                    ':condition' => $data['condition'],
                    ':location' => $data['location'],
                    ':is_digital' => $data['is_digital'],
                    ':total_sessions_completed' => $data['total_sessions_completed'],
                    ':personal_notes' => $data['personal_notes'],
                    ':ownership_format_id' => $data['ownership_format_id'],
                ]);

                $userBookEdition->setId((int) $this->db->lastInsertId());
                $this->logInfo('UserBookEdition created', ['id' => $userBookEdition->getId()]);

            } else {
                // Update existing
                $stmt = $this->db->prepare(
                    'UPDATE user_book_editions SET
                        consumed_at = :consumed_at,
                        current_page = :current_page,
                        active_reading_session_id = :active_reading_session_id,
                        edition_rating = :edition_rating,
                        work_rating = :work_rating,
                        ownership_type = :ownership_type,
                        `condition` = :condition,
                        location = :location,
                        is_digital = :is_digital,
                        total_sessions_completed = :total_sessions_completed,
                        personal_notes = :personal_notes,
                        ownership_format_id = :ownership_format_id
                     WHERE id = :id'
                );

                $stmt->execute([
                    ':id' => $userBookEdition->getId(),
                    ':consumed_at' => $data['consumed_at'],
                    ':current_page' => $data['current_page'],
                    ':active_reading_session_id' => $data['active_reading_session_id'],
                    ':edition_rating' => $data['edition_rating'],
                    ':work_rating' => $data['work_rating'],
                    ':ownership_type' => $data['ownership_type'],
                    ':condition' => $data['condition'],
                    ':location' => $data['location'],
                    ':is_digital' => $data['is_digital'],
                    ':total_sessions_completed' => $data['total_sessions_completed'],
                    ':personal_notes' => $data['personal_notes'],
                    ':ownership_format_id' => $data['ownership_format_id'],
                ]);

                $this->logInfo('UserBookEdition updated', ['id' => $userBookEdition->getId()]);
            }

            return $userBookEdition;

        } catch (PDOException $e) {
            $this->logError('Error saving user book edition', $e, ['user_book_edition' => $userBookEdition->toArray()]);
            throw new RuntimeException("Could not save user book edition: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateRating(int $userId, int $editionId, ?float $workRating, ?float $editionRating = null): void
    {
        try {
            $stmt = $this->db->prepare(
                'UPDATE user_book_editions 
                 SET work_rating = :work_rating, edition_rating = :edition_rating
                 WHERE user_id = :user_id AND edition_id = :edition_id'
            );

            $stmt->execute([
                ':user_id' => $userId,
                ':edition_id' => $editionId,
                ':work_rating' => $workRating,
                ':edition_rating' => $editionRating,
            ]);

            $this->logInfo('Rating updated', [
                'user_id' => $userId,
                'edition_id' => $editionId,
                'work_rating' => $workRating,
                'edition_rating' => $editionRating
            ]);

        } catch (PDOException $e) {
            $this->logError('Error updating rating', $e, [
                'user_id' => $userId,
                'edition_id' => $editionId
            ]);
            throw new RuntimeException("Could not update rating: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateProgress(int $userId, int $editionId, int $currentPage): void
    {
        try {
            $stmt = $this->db->prepare(
                'UPDATE user_book_editions 
                 SET current_page = :current_page
                 WHERE user_id = :user_id AND edition_id = :edition_id'
            );

            $stmt->execute([
                ':user_id' => $userId,
                ':edition_id' => $editionId,
                ':current_page' => $currentPage,
            ]);

            $this->logInfo('Progress updated', [
                'user_id' => $userId,
                'edition_id' => $editionId,
                'current_page' => $currentPage
            ]);

        } catch (PDOException $e) {
            $this->logError('Error updating progress', $e, [
                'user_id' => $userId,
                'edition_id' => $editionId
            ]);
            throw new RuntimeException("Could not update progress: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateStatuses(int $userId, int $editionId, array $statuses): void
    {
        // Get user_book_edition id
        $userBookEdition = $this->findByUserAndEdition($userId, $editionId);
        if (!$userBookEdition) {
            throw new RuntimeException("User book edition not found");
        }

        // Validar lógica de estados excluyentes
        $this->validateStatusLogic($statuses);

        $userEditionId = $userBookEdition->getId();

        try {
            // Delete old statuses
            $this->db->prepare('DELETE FROM user_book_statuses WHERE user_edition_id = :user_edition_id')
                ->execute([':user_edition_id' => $userEditionId]);

            // Insert new statuses
            if (!empty($statuses)) {
                $stmt = $this->db->prepare(
                    'INSERT INTO user_book_statuses (user_edition_id, status_id)
                     VALUES (:user_edition_id, :status_id)'
                );

                foreach ($statuses as $statusName) {
                    $statusId = $this->getStatusId($statusName);
                    if ($statusId !== null) {
                        $stmt->execute([
                            ':user_edition_id' => $userEditionId,
                            ':status_id' => $statusId
                        ]);
                    }
                }
            }

            $this->logInfo('Statuses updated', [
                'user_id' => $userId,
                'edition_id' => $editionId,
                'statuses' => $statuses
            ]);

        } catch (PDOException $e) {
            $this->logError('Error updating statuses', $e, [
                'user_id' => $userId,
                'edition_id' => $editionId
            ]);
            throw new RuntimeException("Could not update statuses: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Valida que los estados sean lógicamente compatibles
     *
     * Reglas:
     * - 'read' puede coexistir con cualquier otro estado (es histórico)
     * - Solo uno de: 'to-read', 'reading', 're-reading', 'paused', 'abandoned' (estado actual de lectura)
     * - Solo uno de: 'owned', 'want-to-buy' (estado de propiedad)
     *
     * @throws InvalidArgumentException si hay estados incompatibles
     */
    private function validateStatusLogic(array $statuses): void
    {
        // Categorías de estados
        $readingStates = ['to-read', 'reading', 're-reading', 'paused', 'abandoned'];
        $ownershipStates = ['owned', 'want-to-buy'];

        // Validar estados de lectura (solo uno permitido)
        $selectedReadingStates = array_intersect($statuses, $readingStates);
        if (count($selectedReadingStates) > 1) {
            throw new InvalidArgumentException(
                "Solo se permite un estado de actividad de lectura simultáneamente. " .
                "Recibidos: " . implode(', ', $selectedReadingStates)
            );
        }

        // Validar estados de propiedad (solo uno permitido)
        $selectedOwnershipStates = array_intersect($statuses, $ownershipStates);
        if (count($selectedOwnershipStates) > 1) {
            throw new InvalidArgumentException(
                "Solo se permite un estado de propiedad simultáneamente. " .
                "Recibidos: " . implode(', ', $selectedOwnershipStates)
            );
        }
    }

    public function getStatusesForEdition(int $userId, int $editionId): array
    {
        try {
            // Get user_book_edition id
            $userBookEdition = $this->findByUserAndEdition($userId, $editionId);
            if (!$userBookEdition) {
                return [];
            }

            $userEditionId = $userBookEdition->getId();

            // Use the trait method to fetch status names
            return $this->fetchStatusNames($userEditionId);

        } catch (PDOException $e) {
            $this->logError('Error getting statuses for edition', $e, [
                'user_id' => $userId,
                'edition_id' => $editionId
            ]);
            return [];
        }
    }

    public function remove(int $userId, int $editionId): bool
    {
        $this->db->beginTransaction();
        try {
            // Get user_book_edition id
            $userBookEdition = $this->findByUserAndEdition($userId, $editionId);
            if (!$userBookEdition) {
                return false;
            }

            $userEditionId = $userBookEdition->getId();

            // Delete related data (CASCADE should handle most, but being explicit)
            $this->db->prepare('DELETE FROM reading_progress_history WHERE user_id = :user_id AND edition_id = :edition_id')
                ->execute([':user_id' => $userId, ':edition_id' => $editionId]);

            $this->db->prepare('DELETE FROM user_edition_notes WHERE user_edition_id = :user_edition_id')
                ->execute([':user_edition_id' => $userEditionId]);

            $this->db->prepare('DELETE FROM user_book_tag_assignments WHERE user_id = :user_id AND edition_id = :edition_id')
                ->execute([':user_id' => $userId, ':edition_id' => $editionId]);

            $this->db->prepare('DELETE FROM user_book_statuses WHERE user_edition_id = :user_edition_id')
                ->execute([':user_edition_id' => $userEditionId]);

            // Delete main relationship
            $stmt = $this->db->prepare('DELETE FROM user_book_editions WHERE id = :id');
            $stmt->execute([':id' => $userEditionId]);

            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();

            if ($deleted) {
                $this->logInfo('User book edition removed', [
                    'user_id' => $userId,
                    'edition_id' => $editionId
                ]);
            }

            return $deleted;

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('Error removing user book edition', $e, [
                'user_id' => $userId,
                'edition_id' => $editionId
            ]);
            throw new RuntimeException("Could not remove edition from user library: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM user_book_editions WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $deleted = $stmt->rowCount() > 0;
            if ($deleted) {
                $this->logInfo('User book edition deleted', ['id' => $id]);
            }

            return $deleted;

        } catch (PDOException $e) {
            $this->logError('Error deleting user book edition', $e, ['id' => $id]);
            throw new RuntimeException("Could not delete user book edition: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get status ID by name
     */
    private function getStatusId(string $statusName): ?int
    {
        try {
            $stmt = $this->db->prepare('SELECT id FROM book_statuses WHERE name = :name LIMIT 1');
            $stmt->execute([':name' => $statusName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (int) $result['id'] : null;

        } catch (PDOException $e) {
            $this->logError('Error getting status ID', $e, ['status_name' => $statusName]);
            return null;
        }
    }
}
