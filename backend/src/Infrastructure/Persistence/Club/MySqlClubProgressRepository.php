<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club;

use App\Domain\Repository\Club\ClubProgressRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Cuántos miembros han completado el ítem, para los cinco medios.
 *
 * Aquí aterriza lo que midió el M0 (2026-08-28) sobre una base sembrada a
 * volumen —60.000 filas de biblioteca, 150.000 notas—, y con dos hallazgos que
 * gobiernan todo este fichero:
 *
 *  1. **`entity_id` es el id EXTERNO y no siempre es la clave de la tabla de
 *     estado.** Libros, álbumes y vídeos necesitan una resolución previa;
 *     películas, series y juegos no, porque su id externo ya es la PK. Las tres
 *     resoluciones salen por índice UNIQUE y el `EXPLAIN` las da como `const`.
 *  2. **El id de un estado NO es estable.** Es `AUTO_INCREMENT` y depende de
 *     cuántas veces se haya sembrado: en la base del M0, `read` salió con
 *     `id = 10`. Va siempre `(SELECT id FROM <medio>_statuses WHERE name = ...)`,
 *     que el UNIQUE de `name` resuelve como `const`.
 *
 * Son **dos** formas de consulta y no cinco: los cuatro medios que cuelgan de
 * `user_id` comparten SQL exacto y solo cambian tabla, columna y estado. Los
 * libros son la excepción porque su estado no cuelga del usuario sino de
 * `user_book_editions` (0,092 ms medidos, por `unique_user_edition`).
 */
final class MySqlClubProgressRepository implements ClubProgressRepositoryInterface
{
    use LoggableTrait;

    /**
     * Tabla de estado, columna del id interno y nombre del estado «completado»,
     * por medio. Los cinco nombres están verificados contra el seed de
     * `init.sql`: `read` · `viewed` · `completed` · `listened` · `watched`.
     *
     * `series` no aparece porque no es un `entity_type` de `club_pick`: una
     * serie viaja como `movie`.
     */
    private const ESTADOS = [
        'movie' => ['user_movie_statuses', 'movie_isbn', 'movie_statuses', 'viewed'],
        'game'  => ['user_game_statuses',  'game_id',    'game_statuses',  'completed'],
        'album' => ['user_album_statuses', 'album_id',   'album_statuses', 'listened'],
        'video' => ['user_video_statuses', 'video_id',   'video_statuses', 'watched'],
    ];

    public function __construct(
        private readonly PDO             $db,
        private readonly LoggerInterface $logger
    ) {}

    public function countCompleted(int $clubId, string $entityType, string $entityId): int
    {
        try {
            if ($entityType === 'book') {
                return $this->countBooksCompleted($clubId, $entityId);
            }

            if (!isset(self::ESTADOS[$entityType])) {
                // Un tipo que no está en el ENUM no puede llegar aquí, pero
                // devolver 0 es lo correcto si llegara: «nadie lo ha
                // completado» no cierra nada, mientras que reventar dejaría la
                // pantalla del club sin pintar.
                return 0;
            }

            [$tabla, $columna, $tablaEstados, $estado] = self::ESTADOS[$entityType];

            $interno = $this->resolveEntityId($entityType, $entityId);
            if ($interno === null) {
                return 0;
            }

            // Forma C del M0: 0,040 ms con 8 miembros. `club_member` entra por
            // su PK con `Using index` y la tabla de estado por `eq_ref`.
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM club_member cm"
                . " INNER JOIN {$tabla} s ON s.user_id = cm.user_id"
                . "   AND s.{$columna} = :entity"
                . "   AND s.status_id = (SELECT id FROM {$tablaEstados} WHERE name = :estado)"
                . " WHERE cm.club_id = :cid"
            );
            $stmt->execute([':cid' => $clubId, ':entity' => $interno, ':estado' => $estado]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('countCompleted failed', $e, [
                'club_id' => $clubId, 'entity_type' => $entityType, 'entity_id' => $entityId,
            ]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function axisFor(string $entityType, string $entityId): ?string
    {
        if ($entityType === 'book') {
            return 'page';
        }

        // Una serie se guarda con `AddMovieUseCase`, así que su fila lleva
        // `entity_type = 'movie'`. Distinguirla exige mirar `movie.media_type`;
        // por el ENUM de `club_pick` es imposible.
        if ($entityType === 'movie') {
            try {
                $tipo = $this->scalar('SELECT media_type FROM movie WHERE isbn = :v', $entityId);

                return $tipo === 'series' ? 'season' : null;
            } catch (PDOException $e) {
                $this->logError('axisFor failed', $e, ['entity_id' => $entityId]);

                // Sin eje se pinta la marca binaria, que es correcta para
                // cualquier medio. Reventar dejaría la pantalla sin progreso.
                return null;
            }
        }

        return null;
    }

    public function findProgress(int $clubId, string $entityType, string $entityId): array
    {
        try {
            $eje = $this->axisFor($entityType, $entityId);

            return match ($eje) {
                'page'   => $this->progresoPorPagina($clubId, $entityId),
                'season' => $this->progresoPorTemporada($clubId, $entityId),
                default  => $this->progresoBinario($clubId, $entityType, $entityId),
            };
        } catch (PDOException $e) {
            $this->logError('findProgress failed', $e, [
                'club_id' => $clubId, 'entity_type' => $entityType, 'entity_id' => $entityId,
            ]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Forma A del M0: 0,092 ms con 8 miembros, 0,176 ms con 50. Escala con el
     * número de miembros, no con el tamaño de las tablas.
     *
     * Los `LEFT JOIN` son deliberados: quien no tiene el libro en su biblioteca
     * **sale igual**, con `point: null`. Con `INNER` desaparecería de la
     * pantalla justo el miembro que está bloqueando el cierre automático.
     */
    private function progresoPorPagina(int $clubId, string $isbn): array
    {
        $editionId = $this->resolveEditionId($isbn);

        $stmt = $this->db->prepare(
            "SELECT cm.user_id, u.username, u.name,
                    ube.current_page              AS point,
                    MAX(bs.name = 'read') IS TRUE AS completed
               FROM club_member cm
               JOIN users u ON u.id = cm.user_id
               LEFT JOIN user_book_editions ube
                      ON ube.user_id = cm.user_id AND ube.edition_id = :eid
               LEFT JOIN user_book_statuses ubs ON ubs.user_edition_id = ube.id
               LEFT JOIN book_statuses      bs  ON bs.id = ubs.status_id
              WHERE cm.club_id = :cid
              GROUP BY cm.user_id, u.username, u.name, ube.current_page
              ORDER BY cm.user_id"
        );
        $stmt->execute([':cid' => $clubId, ':eid' => $editionId]);

        return $this->hidratar($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Forma B del M0: 0,071 ms, por `uq_user_series_season`.
     *
     * El `point` es la temporada **más alta vista**, no el número de filas: ver
     * la 1 y la 3 y saltarse la 2 deja al miembro en la 3, que es donde está
     * de verdad para la regla de spoiler.
     *
     * `completed` NO sale de las temporadas —una serie sin `total_seasons`
     * fiable no se puede dar por acabada contándolas— sino de la misma fuente
     * que usa `countCompleted`: el estado `viewed` en `user_movie_statuses`. Si
     * saliera de otro sitio, la pantalla diría «no lo ha acabado» de alguien a
     * quien el cierre automático ya cuenta como acabado.
     */
    private function progresoPorTemporada(int $clubId, string $imdbId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cm.user_id, u.username, u.name,
                    MAX(uss.season_number)     AS point,
                    (ums.status_id IS NOT NULL) AS completed
               FROM club_member cm
               JOIN users u ON u.id = cm.user_id
               LEFT JOIN user_series_seasons uss
                      ON uss.user_id = cm.user_id AND uss.series_isbn = :sid
                     AND uss.status = 'viewed'
               LEFT JOIN user_movie_statuses ums
                      ON ums.user_id = cm.user_id AND ums.movie_isbn = :sid2
                     AND ums.status_id = (SELECT id FROM movie_statuses WHERE name = 'viewed')
              WHERE cm.club_id = :cid
              GROUP BY cm.user_id, u.username, u.name, ums.status_id
              ORDER BY cm.user_id"
        );
        $stmt->execute([':cid' => $clubId, ':sid' => $imdbId, ':sid2' => $imdbId]);

        return $this->hidratar($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Forma C del M0: 0,040 ms. Una sola consulta para los cuatro medios sin
     * eje y para las películas que no son serie.
     */
    private function progresoBinario(int $clubId, string $entityType, string $entityId): array
    {
        if (!isset(self::ESTADOS[$entityType])) {
            return [];
        }

        [$tabla, $columna, $tablaEstados, $estado] = self::ESTADOS[$entityType];
        $interno = $this->resolveEntityId($entityType, $entityId);

        $stmt = $this->db->prepare(
            "SELECT cm.user_id, u.username, u.name,
                    NULL                      AS point,
                    (s.status_id IS NOT NULL) AS completed
               FROM club_member cm
               JOIN users u ON u.id = cm.user_id
               LEFT JOIN {$tabla} s
                      ON s.user_id = cm.user_id AND s.{$columna} = :entity
                     AND s.status_id = (SELECT id FROM {$tablaEstados} WHERE name = :estado)
              WHERE cm.club_id = :cid
              ORDER BY cm.user_id"
        );
        $stmt->execute([':cid' => $clubId, ':entity' => $interno, ':estado' => $estado]);

        return $this->hidratar($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<int, array{user_id:int, username:string, point:?int, completed:bool}> */
    private function hidratar(array $filas): array
    {
        return array_map(static fn (array $f) => [
            'user_id'   => (int) $f['user_id'],
            // `username` es NULLable en `users`; se cae al nombre, que no lo es.
            'username'  => (string) ($f['username'] ?? $f['name']),
            'point'     => $f['point'] === null ? null : (int) $f['point'],
            'completed' => (bool) $f['completed'],
        ], $filas);
    }

    /**
     * Los libros no cuelgan del usuario sino de `user_book_editions`, así que
     * son un `JOIN` más: forma A del M0, 0,092 ms.
     */
    private function countBooksCompleted(int $clubId, string $isbn): int
    {
        $editionId = $this->resolveEditionId($isbn);
        if ($editionId === null) {
            return 0;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM club_member cm"
            . " INNER JOIN user_book_editions ube"
            . "   ON ube.user_id = cm.user_id AND ube.edition_id = :eid"
            . " INNER JOIN user_book_statuses ubs ON ubs.user_edition_id = ube.id"
            . "   AND ubs.status_id = (SELECT id FROM book_statuses WHERE name = 'read')"
            . " WHERE cm.club_id = :cid"
        );
        $stmt->execute([':cid' => $clubId, ':eid' => $editionId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * El id externo → el interno de la tabla de estado. Solo tres medios lo
     * necesitan; en películas y juegos el `entity_id` ya ES la clave.
     */
    private function resolveEntityId(string $entityType, string $entityId): int|string|null
    {
        return match ($entityType) {
            // `movies.isbn` guarda el id de IMDb: es la PK, no hay nada que
            // resolver. Igual `games.id` con el de RAWG.
            'movie', 'game' => $entityId,
            'album'         => $this->resolveAlbumId($entityId),
            'video'         => $this->scalar('SELECT id FROM videos WHERE youtube_id = :v', $entityId),
            default         => null,
        };
    }

    /**
     * Se enruta **por la forma del id**, no con un `OR` entre las dos columnas:
     * es lo que ya hace `findById` de `AlbumCatalogInterface`. Un MBID son 36
     * caracteres con guiones; un id de Spotify son 22 en base62.
     */
    private function resolveAlbumId(string $entityId): ?int
    {
        $columna = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $entityId)
            ? 'mb_release_group_gid'
            : 'spotify_id';

        $id = $this->scalar("SELECT id FROM albums WHERE {$columna} = :v", $entityId);

        return $id === null ? null : (int) $id;
    }

    /**
     * Mismo camino que `MySqlReadingProgressRepository::getEditionIdFromIsbn`
     * (`:500`), y a propósito: el club no puede inventarse otra forma de
     * resolver un ISBN. MySQL lo saca con `index_merge union(idx_isbn_13,
     * idx_isbn_10)` en 2 filas.
     */
    private function resolveEditionId(string $isbn): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT edition_id FROM book_editions WHERE isbn_13 = :isbn1 OR isbn_10 = :isbn2 LIMIT 1'
        );
        $stmt->execute([':isbn1' => $isbn, ':isbn2' => $isbn]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? (int) $fila['edition_id'] : null;
    }

    private function scalar(string $sql, string $valor): int|string|null
    {
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute([':v' => $valor]);
        $resultado = $stmt->fetchColumn();

        return $resultado === false ? null : $resultado;
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
