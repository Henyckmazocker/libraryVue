<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Journal;

use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlJournalRepository implements JournalRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'journal_entry';

    /**
     * `is_repeat` como subconsulta correlacionada y no como una segunda petición
     * por fila: el listado del diario es largo y paginado, y un N+1 aquí se nota.
     * Cae entera dentro de `idx_journal_item (user_id, media, entity_id, entry_date)`.
     *
     * El desempate por `id` importa: dos entradas del mismo ítem el MISMO día no
     * se distinguen por fecha, y sin él las dos saldrían como repetición o
     * ninguna, según el orden que devolviera el motor.
     *
     * **En series la identidad no es la serie, es la TEMPORADA**, y por eso se
     * compara también el `source_id` (`"<imdbID>:<temporada>"`). Sin esa
     * condición, ver la temporada 2 de *The Bear* salía marcado como
     * revisionado de la 1, que es exactamente lo contrario de lo que pasó. Lo
     * destapó una petición real con `curl` el 2026-09-04; el test de
     * integración no lo veía porque solo probaba con películas.
     */
    private const IS_REPEAT_SUBQUERY = '
        EXISTS (
            SELECT 1 FROM ' . self::TABLE . ' prev
            WHERE prev.user_id   = je.user_id
              AND prev.media     = je.media
              AND prev.entity_id = je.entity_id
              AND (je.media <> \'series\'
                   OR COALESCE(prev.source_id, \'\') = COALESCE(je.source_id, \'\'))
              AND (prev.entry_date < je.entry_date
                   OR (prev.entry_date = je.entry_date AND prev.id < je.id))
        ) AS is_repeat';

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function findByUser(
        int $userId,
        int $limit,
        int $offset,
        ?string $media = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        try {
            $sql = 'SELECT je.*, ' . self::IS_REPEAT_SUBQUERY . '
                    FROM ' . self::TABLE . ' je
                    WHERE je.user_id = :userId';

            if ($media !== null) {
                $sql .= ' AND je.media = :media';
            }
            // `entry_date` es DATE, no un instante: el rango se compara contra
            // la cadena `YYYY-MM-DD` y los dos extremos son inclusivos.
            if ($from !== null) {
                $sql .= ' AND je.entry_date >= :from';
            }
            if ($to !== null) {
                $sql .= ' AND je.entry_date <= :to';
            }

            $sql .= ' ORDER BY je.entry_date DESC, je.id DESC LIMIT :limit OFFSET :offset';

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            if ($media !== null) {
                $stmt->bindValue(':media', $media, PDO::PARAM_STR);
            }
            if ($from !== null) {
                $stmt->bindValue(':from', $from, PDO::PARAM_STR);
            }
            if ($to !== null) {
                $stmt->bindValue(':to', $to, PDO::PARAM_STR);
            }
            // `LIMIT :x` con `ATTR_EMULATE_PREPARES => false` (DatabaseConnector.php:117)
            // exige PARAM_INT: sin él el driver manda una cadena entrecomillada y
            // MySQL responde con un error de sintaxis.
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return array_map(
                static fn (array $row): JournalEntry => JournalEntry::fromRow($row),
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );
        } catch (PDOException $e) {
            $this->logError('DB Error listing journal entries', $e, [
                'userId' => $userId,
                'media'  => $media,
                'from'   => $from,
                'to'     => $to,
            ]);
            throw new RuntimeException('Could not list journal entries. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function countByUser(
        int $userId,
        ?string $media = null,
        ?string $from = null,
        ?string $to = null
    ): int {
        try {
            $sql = 'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE user_id = :userId';
            $params = [':userId' => $userId];

            if ($media !== null) {
                $sql .= ' AND media = :media';
                $params[':media'] = $media;
            }
            // El mismo rango que `findByUser`, o el `hasMore` del listado
            // compararía la página de un mes contra el total del diario entero.
            if ($from !== null) {
                $sql .= ' AND entry_date >= :from';
                $params[':from'] = $from;
            }
            if ($to !== null) {
                $sql .= ' AND entry_date <= :to';
                $params[':to'] = $to;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('DB Error counting journal entries', $e, [
                'userId' => $userId,
                'media'  => $media,
                'from'   => $from,
                'to'     => $to,
            ]);
            throw new RuntimeException('Could not count journal entries. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * El agregado del calendario: una fila por día CON entradas.
     *
     * `GROUP BY entry_date` sobre `WHERE user_id = ? AND entry_date BETWEEN ?`
     * cae entero dentro de `idx_journal_user_date (user_id, entry_date)`, así
     * que el año no se trae ni una entrada: se cuenta en el motor. `media` sale
     * como `GROUP_CONCAT(DISTINCT …)`, que es una cadena separada por comas —
     * los seis medios juntos ocupan 30 caracteres, muy por debajo del
     * `group_concat_max_len` por defecto (1024), así que no se trunca.
     *
     * Con `media` puesto el agregado cuenta solo ese medio —el filtro del
     * listado manda también sobre el calendario—, y el `GROUP_CONCAT` sale
     * entonces con un único valor, que es correcto: el acento de la celda es el
     * de lo que se está enseñando.
     */
    public function countByDay(int $userId, string $from, string $to, ?string $media = null): array
    {
        try {
            $sql = 'SELECT entry_date,
                           COUNT(*) AS total,
                           GROUP_CONCAT(DISTINCT media ORDER BY media) AS medios
                    FROM ' . self::TABLE . '
                    WHERE user_id = :userId
                      AND entry_date >= :from
                      AND entry_date <= :to';
            $params = [':userId' => $userId, ':from' => $from, ':to' => $to];

            if ($media !== null) {
                $sql .= ' AND media = :media';
                $params[':media'] = $media;
            }

            $sql .= ' GROUP BY entry_date ORDER BY entry_date';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $dias = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $dias[(string) $fila['entry_date']] = [
                    'count' => (int) $fila['total'],
                    'media' => $fila['medios'] === null ? [] : explode(',', (string) $fila['medios']),
                ];
            }

            return $dias;
        } catch (PDOException $e) {
            $this->logError('DB Error aggregating journal entries by day', $e, [
                'userId' => $userId,
                'from'   => $from,
                'to'     => $to,
                'media'  => $media,
            ]);
            throw new RuntimeException('Could not aggregate journal entries. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Los años con entradas, de más reciente a más antiguo. Aquí sí se usa
     * `YEAR(entry_date)`: no hay rango que acotar, y el índice sirve igual para
     * quedarse en las filas del usuario.
     */
    public function yearsWithEntries(int $userId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT DISTINCT YEAR(entry_date) AS anyo
                 FROM ' . self::TABLE . '
                 WHERE user_id = :userId
                 ORDER BY anyo DESC'
            );
            $stmt->execute([':userId' => $userId]);

            return array_map(
                static fn ($anyo): int => (int) $anyo,
                $stmt->fetchAll(PDO::FETCH_COLUMN)
            );
        } catch (PDOException $e) {
            $this->logError('DB Error listing journal years', $e, ['userId' => $userId]);
            throw new RuntimeException('Could not list journal years. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function add(JournalEntry $entry): int
    {
        try {
            // `id = LAST_INSERT_ID(id)` en el UPDATE no es adorno: sin él,
            // `lastInsertId()` devuelve 0 cuando la fila ya existía y el caso de
            // uso creería que no ha guardado nada. Con él devuelve el id real en
            // las dos ramas.
            $sql = '
                INSERT INTO ' . self::TABLE . '
                    (user_id, media, entity_id, entity_title, entity_cover, entry_date, rating, source, source_id)
                VALUES
                    (:userId, :media, :entityId, :entityTitle, :entityCover, :entryDate, :rating, :source, :sourceId)
                ON DUPLICATE KEY UPDATE
                    id           = LAST_INSERT_ID(id),
                    entity_title = VALUES(entity_title),
                    entity_cover = VALUES(entity_cover),
                    entry_date   = VALUES(entry_date),
                    rating       = VALUES(rating),
                    updated_at   = CURRENT_TIMESTAMP
            ';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId'      => $entry->getUserId(),
                ':media'       => $entry->getMedia(),
                ':entityId'    => $entry->getEntityId(),
                ':entityTitle' => $entry->getEntityTitle(),
                ':entityCover' => $entry->getEntityCover(),
                ':entryDate'   => $entry->getEntryDate(),
                ':rating'      => $entry->getRating(),
                ':source'      => $entry->getSource(),
                ':sourceId'    => $entry->getSourceId(),
            ]);

            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            $this->logError('DB Error saving journal entry', $e, [
                'userId'   => $entry->getUserId(),
                'media'    => $entry->getMedia(),
                'entityId' => $entry->getEntityId(),
                'source'   => $entry->getSource(),
            ]);
            throw new RuntimeException('Could not save journal entry. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(int $entryId, int $userId, ?string $entryDate, ?float $rating): bool
    {
        try {
            $sets   = [];
            $params = [':id' => $entryId, ':userId' => $userId];

            if ($entryDate !== null) {
                $sets[] = 'entry_date = :entryDate';
                $params[':entryDate'] = $entryDate;
            }
            // El rating se manda siempre, también cuando es NULL: quitar la
            // valoración de una entrada es una edición legítima, y con un
            // `!== null` aquí sería imposible.
            $sets[] = 'rating = :rating';
            $params[':rating'] = $rating;

            $stmt = $this->db->prepare(
                'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND user_id = :userId'
            );
            $stmt->execute($params);

            // `rowCount()` sirve como «existía y era suya» aunque el UPDATE deje
            // los mismos valores, porque `updated_at` lleva ON UPDATE
            // CURRENT_TIMESTAMP y la fila cambia igual.
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('DB Error updating journal entry', $e, [
                'entryId' => $entryId,
                'userId'  => $userId,
            ]);
            throw new RuntimeException('Could not update journal entry. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $entryId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM ' . self::TABLE . ' WHERE id = :id AND user_id = :userId'
            );
            $stmt->execute([':id' => $entryId, ':userId' => $userId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('DB Error deleting journal entry', $e, [
                'entryId' => $entryId,
                'userId'  => $userId,
            ]);
            throw new RuntimeException('Could not delete journal entry. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findById(int $entryId, int $userId): ?JournalEntry
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT je.*, ' . self::IS_REPEAT_SUBQUERY . '
                 FROM ' . self::TABLE . ' je
                 WHERE je.id = :id AND je.user_id = :userId
                 LIMIT 1'
            );
            $stmt->execute([':id' => $entryId, ':userId' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? JournalEntry::fromRow($row) : null;
        } catch (PDOException $e) {
            $this->logError('DB Error finding journal entry', $e, [
                'entryId' => $entryId,
                'userId'  => $userId,
            ]);
            throw new RuntimeException('Could not find journal entry. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function hasEarlierEntry(int $userId, string $media, string $entityId, string $entryDate): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM ' . self::TABLE . '
                 WHERE user_id = :userId AND media = :media AND entity_id = :entityId
                   AND entry_date < :entryDate
                 LIMIT 1'
            );
            $stmt->execute([
                ':userId'    => $userId,
                ':media'     => $media,
                ':entityId'  => $entityId,
                ':entryDate' => $entryDate,
            ]);

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            $this->logError('DB Error checking earlier journal entry', $e, [
                'userId'   => $userId,
                'media'    => $media,
                'entityId' => $entityId,
            ]);
            throw new RuntimeException('Could not check journal history. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
