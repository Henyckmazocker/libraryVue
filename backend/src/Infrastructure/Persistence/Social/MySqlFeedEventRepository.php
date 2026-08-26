<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Social;

use App\Domain\Model\FeedEvent;
use App\Domain\Repository\Social\FeedEventRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use App\Infrastructure\Persistence\Social\Mappers\FeedEventDataMapper;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlFeedEventRepository implements FeedEventRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'feed_events';

    public function __construct(
        private readonly PDO                $db,
        private readonly FeedEventDataMapper $mapper,
        private readonly LoggerInterface    $logger
    ) {}

    public function save(FeedEvent $event): FeedEvent
    {
        try {
            $data = $this->mapper->toPersistence($event);
            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE
                . " (user_id, event_type, entity_type, entity_id, entity_title, entity_cover, metadata)"
                . " VALUES (:user_id, :event_type, :entity_type, :entity_id, :entity_title, :entity_cover, :metadata)"
            );
            $stmt->execute($data);
            $id = (int) $this->db->lastInsertId();
            return new FeedEvent(
                $id,
                $event->getUserId(),
                $event->getEventType(),
                $event->getEntityType(),
                $event->getEntityId(),
                $event->getEntityTitle(),
                $event->getEntityCover(),
                $event->getMetadata()
            );
        } catch (PDOException $e) {
            $this->logError('save feed event failed', $e);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Build and execute a query filtering by user_id + event_type pairs
     *
     * @param array<int, string[]> $allowedByUser  userId => [event_types]
     */
    public function findFeedEvents(array $allowedByUser, int $limit, int $offset): array
    {
        if (empty($allowedByUser)) {
            return [];
        }

        [$whereClauses, $params] = $this->buildAllowedConditions($allowedByUser);

        // El `LEFT JOIN` a `movie` es lo que deja al cliente distinguir una serie de
        // una película: `feed_events.entity_type` no tiene —ni puede tener— un
        // `'series'`, porque en el backend las dos son la misma entidad y se
        // guardan con `AddMovieUseCase`. Lo que las separa es `movie.media_type`.
        //
        // `LEFT` y no `JOIN` a secas, y la condición en el `ON` y no en el `WHERE`:
        // con cualquiera de las dos cosas al revés, un evento de libro no casaría
        // con ninguna fila de `movie` y desaparecería del feed.
        $sql = "SELECT fe.*, u.username, u.name AS user_name, u.picture AS user_picture,"
             . " m.media_type AS entity_media_type"
             . " FROM " . self::TABLE . " fe"
             . " JOIN users u ON u.id = fe.user_id"
             . " LEFT JOIN movie m ON fe.entity_type = 'movie' AND m.isbn = fe.entity_id"
             . " WHERE (" . implode(' OR ', $whereClauses) . ")"
             . " ORDER BY fe.created_at DESC"
             . " LIMIT :lim OFFSET :off";

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':lim',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':off',  $offset, PDO::PARAM_INT);
            $stmt->execute();
            return array_map(
                fn(array $row) => $this->mapper->toEnrichedArray($row),
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );
        } catch (PDOException $e) {
            $this->logError('findFeedEvents failed', $e);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function countFeedEvents(array $allowedByUser): int
    {
        if (empty($allowedByUser)) {
            return 0;
        }

        [$whereClauses, $params] = $this->buildAllowedConditions($allowedByUser);

        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " fe"
             . " WHERE (" . implode(' OR ', $whereClauses) . ")";

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('countFeedEvents failed', $e);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Build WHERE sub-clauses and parameter map from allowedByUser map
     *
     * @param array<int, string[]> $allowedByUser
     * @return array{string[], array<string, mixed>}
     */
    private function buildAllowedConditions(array $allowedByUser): array
    {
        $whereClauses = [];
        $params       = [];
        $i            = 0;

        foreach ($allowedByUser as $userId => $eventTypes) {
            if (empty($eventTypes)) {
                continue;
            }
            $userKey   = ":uid_{$i}";
            $typeKeys  = [];
            foreach ($eventTypes as $j => $type) {
                $typeKey            = ":et_{$i}_{$j}";
                $typeKeys[]         = $typeKey;
                $params[$typeKey]   = $type;
            }
            $params[$userKey]    = (int) $userId;
            $inList              = implode(',', $typeKeys);
            $whereClauses[]      = "(fe.user_id = {$userKey} AND fe.event_type IN ({$inList}))";
            $i++;
        }

        return [$whereClauses, $params];
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
