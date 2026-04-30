<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Game;

use App\Domain\Model\Game;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Infrastructure\Persistence\Game\Mappers\GameDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Game repository
 * Handles Game CRUD operations only
 */
final class MySqlGameRepository implements GameRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE = 'game_statuses';
    private const STATUS_LINK_TABLE = 'game_has_statuses';
    private const STATUS_COLUMN = 'game_id';

    public function __construct(
        private readonly PDO $db,
        private readonly GameDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function findById(int $id): ?Game
    {
        try {
            $sql = "SELECT * FROM games WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                return null;
            }

            return $this->mapper->toDomain($data);
        } catch (PDOException $e) {
            $this->logError('DB Find Error', $e, [
                'id' => $id,
                'operation' => 'find_by_id'
            ]);
            throw new RuntimeException("Could not find game. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function findBySlug(string $slug): ?Game
    {
        try {
            $sql = "SELECT * FROM games WHERE slug = :slug";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':slug' => $slug]);
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                return null;
            }

            return $this->mapper->toDomain($data);
        } catch (PDOException $e) {
            $this->logError('DB Find By Slug Error', $e, [
                'slug' => $slug,
                'operation' => 'find_by_slug'
            ]);
            throw new RuntimeException("Could not find game by slug. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function findAll(array $filters = []): array
    {
        try {
            $sql = "SELECT DISTINCT g.* FROM games g";
            $params = [];

            if (!empty($filters['userStatus'])) {
                $statusName = $filters['userStatus'];
                $statusId = $this->getStatusId($statusName);
                if ($statusId === null) {
                    return [];
                }
                $sql .= " JOIN game_has_statuses ghs ON g.id = ghs.game_id";
                $sql .= " WHERE ghs.status_id = :statusId";
                $params[':statusId'] = $statusId;
            }
            
            $sql .= " ORDER BY g.addedTimestamp DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $gamesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->mapper->toDomainCollection($gamesData);
        } catch (PDOException $e) {
            $this->logError('DB FindAll Error', $e, [
                'filters' => $filters,
                'operation' => 'find_all'
            ]);
            throw new RuntimeException("Could not fetch games. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function save(Game $game): Game
    {
        $this->db->beginTransaction();
        try {
            $persistenceData = $this->mapper->toPersistence($game);
            
            $sql = "INSERT INTO games (id, slug, title, release_date, developer, publisher, coverUrl, backgroundUrl, rating, description, platforms, genres, esrb_rating, playtime, metacritic_score, addedTimestamp) " .
                   "VALUES (:id, :slug, :title, :release_date, :developer, :publisher, :coverUrl, :backgroundUrl, :rating, :description, :platforms, :genres, :esrb_rating, :playtime, :metacritic_score, :addedTimestamp) " .
                   "ON DUPLICATE KEY UPDATE " .
                   "slug = VALUES(slug), title = VALUES(title), release_date = VALUES(release_date), developer = VALUES(developer), publisher = VALUES(publisher), coverUrl = VALUES(coverUrl), backgroundUrl = VALUES(backgroundUrl), " .
                   "rating = VALUES(rating), description = VALUES(description), platforms = VALUES(platforms), genres = VALUES(genres), esrb_rating = VALUES(esrb_rating), playtime = VALUES(playtime), metacritic_score = VALUES(metacritic_score), addedTimestamp = VALUES(addedTimestamp)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $persistenceData['id'],
                ':slug' => $persistenceData['slug'],
                ':title' => $persistenceData['title'],
                ':release_date' => $persistenceData['release_date'],
                ':developer' => $persistenceData['developer'],
                ':publisher' => $persistenceData['publisher'],
                ':coverUrl' => $persistenceData['coverUrl'],
                ':backgroundUrl' => $persistenceData['backgroundUrl'],
                ':rating' => $persistenceData['rating'],
                ':description' => $persistenceData['description'],
                ':platforms' => $persistenceData['platforms'],
                ':genres' => $persistenceData['genres'],
                ':esrb_rating' => $persistenceData['esrb_rating'],
                ':playtime' => $persistenceData['playtime'],
                ':metacritic_score' => $persistenceData['metacritic_score'],
                ':addedTimestamp' => $persistenceData['addedTimestamp']
            ]);

            $this->db->commit();
            
            $this->logInfo('Game saved successfully', [
                'game_id' => $persistenceData['id'],
                'operation' => 'save'
            ]);
            
            return $game;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Save Error', $e, [
                'game_data' => $game->toArray(),
                'operation' => 'save_game'
            ]);
            throw new RuntimeException("Could not save game. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logError('Generic Error during save', $e, [
                'game_data' => $game->toArray(),
                'operation' => 'save_game'
            ]);
            throw new RuntimeException("An unexpected error occurred while saving game: " . $e->getMessage(), 0, $e);
        }
    }

    public function update(Game $game): bool
    {
        try {
            $persistenceData = $this->mapper->toPersistence($game);
            
            $sql = "UPDATE games SET 
                    slug = :slug,
                    title = :title, 
                    release_date = :release_date, 
                    developer = :developer, 
                    publisher = :publisher,
                    coverUrl = :coverUrl,
                    backgroundUrl = :backgroundUrl, 
                    rating = :rating, 
                    description = :description, 
                    platforms = :platforms,
                    genres = :genres,
                    esrb_rating = :esrb_rating,
                    playtime = :playtime,
                    metacritic_score = :metacritic_score
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $updated = $stmt->execute([
                ':id' => $persistenceData['id'],
                ':slug' => $persistenceData['slug'],
                ':title' => $persistenceData['title'],
                ':release_date' => $persistenceData['release_date'],
                ':developer' => $persistenceData['developer'],
                ':publisher' => $persistenceData['publisher'],
                ':coverUrl' => $persistenceData['coverUrl'],
                ':backgroundUrl' => $persistenceData['backgroundUrl'],
                ':rating' => $persistenceData['rating'],
                ':description' => $persistenceData['description'],
                ':platforms' => $persistenceData['platforms'],
                ':genres' => $persistenceData['genres'],
                ':esrb_rating' => $persistenceData['esrb_rating'],
                ':playtime' => $persistenceData['playtime'],
                ':metacritic_score' => $persistenceData['metacritic_score']
            ]);

            if ($updated) {
                $this->logInfo('Game updated successfully', [
                    'game_id' => $persistenceData['id'],
                    'operation' => 'update'
                ]);
            }
            
            return $updated;
        } catch (PDOException $e) {
            $this->logError('DB Update Error', $e, [
                'game_data' => $game->toArray(),
                'operation' => 'update_game'
            ]);
            throw new RuntimeException("Could not update game. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            // Delete related statuses first
            $stmtDeleteLinks = $this->db->prepare("DELETE FROM game_has_statuses WHERE game_id = :id");
            $stmtDeleteLinks->execute([':id' => $id]);
            
            // Delete game
            $stmtDeleteGame = $this->db->prepare("DELETE FROM games WHERE id = :id");
            $stmtDeleteGame->execute([':id' => $id]);
            
            $deleted = $stmtDeleteGame->rowCount() > 0;
            $this->db->commit();
            
            if ($deleted) {
                $this->logInfo('Game deleted successfully', [
                    'game_id' => $id,
                    'operation' => 'delete'
                ]);
            }
            
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB Delete Error', $e, [
                'game_id' => $id,
                'operation' => 'delete_game'
            ]);
            throw new RuntimeException("Could not delete game. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function exists(int $id): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM games WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logError('DB Exists Error', $e, [
                'game_id' => $id,
                'operation' => 'exists'
            ]);
            throw new RuntimeException("Could not check if game exists. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function fetchAllowedStatuses(): array
    {
        try {
            $sql = "SELECT name FROM game_statuses ORDER BY id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (PDOException $e) {
            $this->logError('DB Fetch Allowed Statuses Error', $e, [
                'operation' => 'fetch_allowed_statuses'
            ]);
            throw new RuntimeException("Could not fetch allowed statuses. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function updateRating(int $id, float $rating): void
    {
        try {
            $sql = "UPDATE games SET rating = :rating WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':rating' => $rating
            ]);

            $this->logInfo('Game rating updated successfully', [
                'game_id' => $id,
                'rating' => $rating,
                'operation' => 'update_rating'
            ]);
        } catch (PDOException $e) {
            $this->logError('DB Update Rating Error', $e, [
                'game_id' => $id,
                'rating' => $rating,
                'operation' => 'update_rating'
            ]);
            throw new RuntimeException("Could not update game rating. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

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
}
