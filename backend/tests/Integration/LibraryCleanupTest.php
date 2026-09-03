<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Sacar algo de tu biblioteca se lleva lo tuyo sobre ello, en los cinco medios.
 *
 * La trampa es la misma en todos: las tablas `user_*_notes` —y `user_series_seasons`,
 * y `user_video_statuses`— tienen la FK apuntando a la tabla de **catálogo**
 * (`movie`, `games`, `albums`, `videos`), que es compartida y **no** se borra al
 * quitar algo de una biblioteca. Su `ON DELETE CASCADE` no salta nunca por esta vía,
 * así que hay que limpiarlas a mano en el `remove()` del repositorio. Las
 * `user_*_tag_assignments` sí cuelgan de la tabla del usuario y se van solas, que es
 * lo que hacía creer que todo estaba cubierto.
 *
 * Estaba mal en cuatro de los cinco medios —solo los libros lo hacían bien— y no lo
 * veía ninguna barrera: un unitario mockea PDO y comprueba que se llamó al
 * repositorio, no lo que el repositorio dejó en la base. Lo destapó el 2026-09-03
 * retirar una serie después de probar el seguimiento por temporadas.
 *
 * El síntoma no era un error: era que volver a guardar el mismo ítem resucitaba las
 * notas viejas y el progreso viejo.
 */
class LibraryCleanupTest extends IntegrationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Coleccionista']);
        $this->userId = (int) $this->pdo()->lastInsertId();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $this->userId]);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    private function contar(string $tabla, string $columna, string|int $id): int
    {
        $stmt = $this->pdo()->prepare("SELECT COUNT(*) FROM {$tabla} WHERE user_id = :u AND {$columna} = :i");
        $stmt->execute(['u' => $this->userId, 'i' => $id]);

        return (int) $stmt->fetchColumn();
    }

    #[Test]
    public function deleting_a_game_takes_its_notes_with_it(): void
    {
        $alta = $this->router()->dispatch('add_game', ['game' => [
            'id'       => 424242,
            'title'    => 'Un juego de prueba',
            'statuses' => ['owned'],
        ]]);
        $this->assertSame('success', $alta['status'], 'add_game tiene que guardar');

        $this->router()->dispatch('add_game_note', [
            'gameId'   => 424242,
            'noteText' => 'Una nota de prueba',
        ]);
        $this->assertSame(1, $this->contar('user_game_notes', 'game_id', 424242));

        $r = $this->router()->dispatch('delete_game', ['gameId' => 424242]);

        $this->assertSame('success', $r['status']);
        $this->assertSame(0, $this->contar('user_game_notes', 'game_id', 424242), 'La nota no puede quedar huérfana');
        $this->assertSame(0, $this->contar('user_game_statuses', 'game_id', 424242));
    }

    #[Test]
    public function deleting_an_album_takes_its_notes_with_it(): void
    {
        $alta = $this->router()->dispatch('add_album', ['album' => [
            // `AddAlbumCommand::fromArray` NO lee `id`: la identidad llega por
            // `mb_release_group_gid`, `album_id` o `spotify_id`. Y con forma de UUID,
            // porque `AlbumId` valida el formato.
            'mb_release_group_gid' => '11111111-2222-3333-4444-555555555555',
            'title'    => 'Un álbum de prueba',
            'artist'   => 'Alguien',
            'statuses' => ['owned'],
        ]]);
        $this->assertSame('success', $alta['status'], 'add_album tiene que guardar');

        $albumId = (int) $this->pdo()
            ->query("SELECT id FROM albums WHERE title = 'Un álbum de prueba' LIMIT 1")
            ->fetchColumn();

        $this->router()->dispatch('add_album_note', [
            'albumId'  => $albumId,
            'noteText' => 'Una nota de prueba',
        ]);
        $this->assertSame(1, $this->contar('user_album_notes', 'album_id', $albumId));

        $r = $this->router()->dispatch('delete_album', ['albumId' => $albumId]);

        $this->assertSame('success', $r['status']);
        $this->assertSame(0, $this->contar('user_album_notes', 'album_id', $albumId), 'La nota no puede quedar huérfana');
        $this->assertSame(0, $this->contar('user_album_statuses', 'album_id', $albumId));
    }

    /**
     * El vídeo era el peor de los cinco: su `remove()` borraba **solo** la fila de
     * `user_videos`, así que se dejaba los estados **y** las notas, y encima sin
     * transacción.
     */
    #[Test]
    public function deleting_a_video_takes_its_notes_and_statuses_with_it(): void
    {
        $alta = $this->router()->dispatch('add_video', [
            'youtubeId' => 'dQw4w9WgXcQ',
            'title'     => 'Un vídeo de prueba',
            'statuses'  => ['watched'],
        ]);
        $this->assertSame('success', $alta['status'], 'add_video tiene que guardar');

        $videoId = (int) $this->pdo()
            ->query("SELECT id FROM videos WHERE youtube_id = 'dQw4w9WgXcQ' LIMIT 1")
            ->fetchColumn();

        $this->router()->dispatch('add_video_note', [
            'youtubeId' => 'dQw4w9WgXcQ',
            'noteText'  => 'Una nota de prueba',
        ]);
        $this->assertSame(1, $this->contar('user_video_notes', 'video_id', $videoId));
        $this->assertGreaterThan(0, $this->contar('user_video_statuses', 'video_id', $videoId));

        $r = $this->router()->dispatch('delete_video', ['youtubeId' => 'dQw4w9WgXcQ']);

        $this->assertSame('success', $r['status']);
        $this->assertSame(0, $this->contar('user_video_notes', 'video_id', $videoId), 'La nota no puede quedar huérfana');
        $this->assertSame(0, $this->contar('user_video_statuses', 'video_id', $videoId), 'Ni el estado');
    }
}
