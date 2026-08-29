<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Club;

use App\Domain\Repository\Club\ClubNoteRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Las notas públicas del club sobre su ítem activo.
 *
 * **Las cinco tablas de notas no comparten forma**, y eso lo destapó el M0
 * (hallazgo 5). Son tres consultas distintas y no una genérica:
 *
 *  - `user_edition_notes` cuelga de `user_edition_id`, es decir **indirecto**
 *    vía `user_book_editions`, y es la única con `page_number` de verdad
 *    (`INT UNSIGNED NOT NULL`).
 *  - `user_movie_notes` cuelga de `user_id` + `movie_isbn`. Tiene
 *    `page_number`, pero es `NULL` y **no significa nada** — ni un minuto ni
 *    una temporada—, así que aquí no se lee: hacerlo sería un bug silencioso.
 *  - `user_game_notes`, `user_album_notes` y `user_video_notes` cuelgan de un
 *    id interno y **no tienen la columna**. Un `SELECT … page_number` genérico
 *    sobre las cinco no compila: revienta con «Unknown column» en tres.
 */
final class MySqlClubNoteRepository implements ClubNoteRepositoryInterface
{
    use LoggableTrait;

    /** Tabla y columna del id interno, para los tres medios de forma simple. */
    private const SIMPLES = [
        'game'  => ['user_game_notes',  'game_id',  'games',  'id'],
        'album' => ['user_album_notes', 'album_id', 'albums', 'id'],
        'video' => ['user_video_notes', 'video_id', 'videos', 'id'],
    ];

    public function __construct(
        private readonly PDO             $db,
        private readonly LoggerInterface $logger
    ) {}

    public function findPublicForPick(int $clubId, string $entityType, string $entityId): array
    {
        try {
            return match ($entityType) {
                'book'  => $this->notasDeLibro($clubId, $entityId),
                'movie' => $this->notasDePelicula($clubId, $entityId),
                default => $this->notasSimples($clubId, $entityType, $entityId),
            };
        } catch (PDOException $e) {
            $this->logError('findPublicForPick failed', $e, [
                'club_id' => $clubId, 'entity_type' => $entityType, 'entity_id' => $entityId,
            ]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * El único medio con punto en el eje. Dos saltos: de la nota a
     * `user_book_editions` y de ahí a la edición del club.
     */
    private function notasDeLibro(int $clubId, string $isbn): array
    {
        $stmt = $this->db->prepare(
            "SELECT n.id AS note_id, n.user_id, u.username, u.name,
                    n.note_text AS text, n.page_number AS point, n.created_at
               FROM club_member cm
               JOIN users u ON u.id = cm.user_id
               JOIN user_book_editions ube ON ube.user_id = cm.user_id
               JOIN book_editions be ON be.edition_id = ube.edition_id
                    AND (be.isbn_13 = :isbn1 OR be.isbn_10 = :isbn2)
               JOIN user_edition_notes n ON n.user_edition_id = ube.id
              WHERE cm.club_id = :cid AND n.is_private = 0
              ORDER BY n.page_number ASC, n.id ASC"
        );
        $stmt->execute([':cid' => $clubId, ':isbn1' => $isbn, ':isbn2' => $isbn]);

        return $this->hidratar($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Películas y series comparten tabla porque comparten entidad: una serie se
     * guarda con `AddMovieUseCase`. `page_number` **no se lee** — ver el
     * docblock de la clase.
     */
    private function notasDePelicula(int $clubId, string $imdbId): array
    {
        $stmt = $this->db->prepare(
            "SELECT n.id AS note_id, n.user_id, u.username, u.name,
                    n.note_text AS text, NULL AS point, n.created_at
               FROM club_member cm
               JOIN users u ON u.id = cm.user_id
               JOIN user_movie_notes n ON n.user_id = cm.user_id AND n.movie_isbn = :mid
              WHERE cm.club_id = :cid AND n.is_private = 0
              ORDER BY n.created_at ASC, n.id ASC"
        );
        $stmt->execute([':cid' => $clubId, ':mid' => $imdbId]);

        return $this->hidratar($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Juegos, álbumes y vídeos: id interno y sin columna de punto. */
    private function notasSimples(int $clubId, string $entityType, string $entityId): array
    {
        if (!isset(self::SIMPLES[$entityType])) {
            return [];
        }

        [$tabla, $columna, $catalogo, $pk] = self::SIMPLES[$entityType];

        $interno = $this->resolverInterno($entityType, $entityId, $catalogo, $pk);
        if ($interno === null) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT n.id AS note_id, n.user_id, u.username, u.name,
                    n.note_text AS text, NULL AS point, n.created_at
               FROM club_member cm
               JOIN users u ON u.id = cm.user_id
               JOIN {$tabla} n ON n.user_id = cm.user_id AND n.{$columna} = :entity
              WHERE cm.club_id = :cid AND n.is_private = 0
              ORDER BY n.created_at ASC, n.id ASC"
        );
        $stmt->execute([':cid' => $clubId, ':entity' => $interno]);

        return $this->hidratar($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * El id externo → el interno, con el mismo criterio que
     * `MySqlClubProgressRepository`: en juegos el `entity_id` YA es la PK; en
     * álbumes se enruta por la forma del id y en vídeos por `youtube_id`.
     */
    private function resolverInterno(string $entityType, string $entityId, string $catalogo, string $pk): int|string|null
    {
        if ($entityType === 'game') {
            return $entityId;
        }

        if ($entityType === 'album') {
            $columna = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $entityId)
                ? 'mb_release_group_gid'
                : 'spotify_id';
        } else {
            $columna = 'youtube_id';
        }

        $stmt = $this->db->prepare("SELECT {$pk} FROM {$catalogo} WHERE {$columna} = :v LIMIT 1");
        $stmt->execute([':v' => $entityId]);
        $valor = $stmt->fetchColumn();

        return $valor === false ? null : $valor;
    }

    /** @return array<int, array{note_id:int, user_id:int, username:string, text:string, point:?int, created_at:string}> */
    private function hidratar(array $filas): array
    {
        return array_map(static fn (array $f) => [
            'note_id'    => (int) $f['note_id'],
            'user_id'    => (int) $f['user_id'],
            // `username` es NULLable en `users`; se cae al nombre, que no lo es.
            'username'   => (string) ($f['username'] ?? $f['name']),
            'text'       => (string) $f['text'],
            'point'      => $f['point'] === null ? null : (int) $f['point'],
            'created_at' => (string) $f['created_at'],
        ], $filas);
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
