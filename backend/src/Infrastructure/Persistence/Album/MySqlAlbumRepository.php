<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Album;

use App\Domain\Model\Album;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Infrastructure\Persistence\Album\Mappers\AlbumDataMapper;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Concerns\StatusManagementTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation for Album repository
 * Handles Album CRUD operations only (catalogue-level, not user-specific)
 */
final class MySqlAlbumRepository implements AlbumRepositoryInterface
{
    use LoggableTrait;
    use StatusManagementTrait;

    private const STATUS_TABLE      = 'album_statuses';
    private const STATUS_LINK_TABLE = 'album_has_statuses';
    private const STATUS_COLUMN     = 'album_id';

    public function __construct(
        private readonly PDO $db,
        private readonly AlbumDataMapper $mapper,
        private readonly LoggerInterface $logger
    ) {}

    public function findById(int $id): ?Album
    {
        try {
            $sql = "SELECT * FROM albums WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);

            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                return null;
            }

            return $this->mapper->toDomain($data);
        } catch (PDOException $e) {
            $this->logError('DB findById Error', $e, ['id' => $id]);
            throw new RuntimeException('Could not find album. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findBySpotifyId(string $spotifyId): ?Album
    {
        try {
            $sql = "SELECT * FROM albums WHERE spotify_id = :spotifyId";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':spotifyId' => $spotifyId]);

            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                return null;
            }

            return $this->mapper->toDomain($data);
        } catch (PDOException $e) {
            $this->logError('DB findBySpotifyId Error', $e, ['spotify_id' => $spotifyId]);
            throw new RuntimeException('Could not find album by Spotify ID. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findAll(array $filters = []): array
    {
        try {
            $sql    = "SELECT a.* FROM albums a";
            $params = [];

            $conditions = [];
            if (!empty($filters['title'])) {
                $conditions[]        = "a.title LIKE :title";
                $params[':title']    = '%' . $filters['title'] . '%';
            }
            if (!empty($filters['artist'])) {
                $conditions[]        = "a.artist LIKE :artist";
                $params[':artist']   = '%' . $filters['artist'] . '%';
            }
            if (!empty($filters['genre'])) {
                $conditions[]        = "JSON_CONTAINS(a.genres, :genre, '\$')";
                $params[':genre']    = '"' . $filters['genre'] . '"';
            }
            if (!empty($filters['albumType'])) {
                $conditions[]           = "a.album_type = :albumType";
                $params[':albumType']   = $filters['albumType'];
            }

            if (!empty($conditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $conditions);
            }

            $sql .= ' ORDER BY a.title ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            $this->logError('DB findAll Error', $e, ['filters' => $filters]);
            throw new RuntimeException('Could not fetch albums. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function save(Album $album): Album
    {
        $this->db->beginTransaction();
        try {
            $p = $this->mapper->toPersistence($album);

            $sql = "INSERT INTO albums 
                        (spotify_id, title, artist, artist_id, release_date, release_date_precision,
                         cover_url, genres, label, total_tracks, album_type, duration_ms, popularity,
                         external_url, upc, addedTimestamp)
                    VALUES
                        (:spotify_id, :title, :artist, :artist_id, :release_date, :release_date_precision,
                         :cover_url, :genres, :label, :total_tracks, :album_type, :duration_ms, :popularity,
                         :external_url, :upc, UNIX_TIMESTAMP())
                    ON DUPLICATE KEY UPDATE
                        title                  = VALUES(title),
                        artist                 = VALUES(artist),
                        artist_id              = VALUES(artist_id),
                        release_date           = VALUES(release_date),
                        release_date_precision = VALUES(release_date_precision),
                        cover_url              = VALUES(cover_url),
                        genres                 = VALUES(genres),
                        label                  = VALUES(label),
                        total_tracks           = VALUES(total_tracks),
                        album_type             = VALUES(album_type),
                        duration_ms            = VALUES(duration_ms),
                        popularity             = VALUES(popularity),
                        external_url           = VALUES(external_url),
                        upc                    = VALUES(upc)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':spotify_id'             => $p['spotify_id'],
                ':title'                  => $p['title'],
                ':artist'                 => $p['artist'],
                ':artist_id'              => $p['artist_id'],
                ':release_date'           => $p['release_date'],
                ':release_date_precision' => $p['release_date_precision'],
                ':cover_url'              => $p['cover_url'],
                ':genres'                 => $p['genres'],
                ':label'                  => $p['label'],
                ':total_tracks'           => $p['total_tracks'],
                ':album_type'             => $p['album_type'],
                ':duration_ms'            => $p['duration_ms'],
                ':popularity'             => $p['popularity'],
                ':external_url'           => $p['external_url'],
                ':upc'                    => $p['upc'],
            ]);

            $insertedId = (int)$this->db->lastInsertId();
            $this->db->commit();

            $this->logInfo('Album saved', ['spotify_id' => $p['spotify_id'], 'db_id' => $insertedId]);

            // Return the album with its assigned DB ID
            return $this->findById($insertedId) ?? $album;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('DB save Error', $e, ['album' => $album->getTitle()]);
            throw new RuntimeException('Could not save album. DB Error: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException('Unexpected error saving album: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(Album $album): bool
    {
        try {
            $p = $this->mapper->toPersistence($album);

            $sql = "UPDATE albums SET
                        title                  = :title,
                        artist                 = :artist,
                        artist_id              = :artist_id,
                        release_date           = :release_date,
                        release_date_precision = :release_date_precision,
                        cover_url              = :cover_url,
                        genres                 = :genres,
                        label                  = :label,
                        total_tracks           = :total_tracks,
                        album_type             = :album_type,
                        duration_ms            = :duration_ms,
                        popularity             = :popularity,
                        external_url           = :external_url,
                        upc                    = :upc
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id'                     => $p['id'],
                ':title'                  => $p['title'],
                ':artist'                 => $p['artist'],
                ':artist_id'              => $p['artist_id'],
                ':release_date'           => $p['release_date'],
                ':release_date_precision' => $p['release_date_precision'],
                ':cover_url'              => $p['cover_url'],
                ':genres'                 => $p['genres'],
                ':label'                  => $p['label'],
                ':total_tracks'           => $p['total_tracks'],
                ':album_type'             => $p['album_type'],
                ':duration_ms'            => $p['duration_ms'],
                ':popularity'             => $p['popularity'],
                ':external_url'           => $p['external_url'],
                ':upc'                    => $p['upc'],
            ]);
        } catch (PDOException $e) {
            $this->logError('DB update Error', $e, ['id' => $album->getId()]);
            throw new RuntimeException('Could not update album. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM albums WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('DB delete Error', $e, ['id' => $id]);
            throw new RuntimeException('Could not delete album. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function fetchAllowedStatuses(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT name FROM album_statuses ORDER BY name");
            $stmt->execute();
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
        } catch (PDOException $e) {
            $this->logError('DB fetchAllowedStatuses Error', $e, []);
            throw new RuntimeException('Could not fetch allowed statuses. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateRating(int $id, float $rating): void
    {
        try {
            $stmt = $this->db->prepare("UPDATE albums SET popularity = :rating WHERE id = :id");
            $stmt->execute([':rating' => $rating, ':id' => $id]);
        } catch (PDOException $e) {
            $this->logError('DB updateRating Error', $e, ['id' => $id]);
            throw new RuntimeException('Could not update album rating. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function exists(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM albums WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logError('DB exists Error', $e, ['id' => $id]);
            throw new RuntimeException('Could not check album existence. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function existsBySpotifyId(string $spotifyId): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM albums WHERE spotify_id = :spotifyId");
            $stmt->execute([':spotifyId' => $spotifyId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->logError('DB existsBySpotifyId Error', $e, ['spotify_id' => $spotifyId]);
            throw new RuntimeException('Could not check album existence by Spotify ID. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    // --- StatusManagementTrait required methods ---

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

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
