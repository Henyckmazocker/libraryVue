<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * `get_user_journal`: el diario de otro, entrando por `ActionRouter`.
 *
 * Son **las tres peticiones que el plan pide con `curl`** —sin amistad, con
 * amistad y el interruptor apagado, con amistad y encendido— escritas como test
 * para que se reverifiquen solas. Van de integración porque el fallo típico de
 * este repo es la acción declarada a medias en uno de los tres sitios
 * (`routes.php`, el `match` de `ActionRouter`, el controller), y eso ningún mock
 * de PDO lo ve.
 *
 * Lo que se comprueba no es solo que el amigo vea el diario: es que **las dos
 * respuestas negativas son idénticas a la de un diario vacío**. Un 403, o un
 * «usuario no encontrado», confirmarían que hay algo detrás.
 */
class UserJournalTest extends IntegrationTestCase
{
    private int $visor;
    private int $dueno;
    private string $duenoUsername;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->visor, ]                     = $this->crearUsuario('Quien mira');
        [$this->dueno, $this->duenoUsername] = $this->crearUsuario('Quien escribe');

        $this->autenticarComo($this->visor);
        $this->apuntarEnElDiario('tt0111161', 'Cadena perpetua', '2026-09-01');
        $this->apuntarEnElDiario('tt0133093', 'The Matrix', '2026-09-03');
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    // ---- Utillaje ------------------------------------------------------------

    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    /** @return array{0: int, 1: string} id y username */
    private function crearUsuario(string $nombre): array
    {
        $sufijo = bin2hex(random_bytes(4));
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name, username) VALUES (:g, :e, :n, :u)'
        );
        $stmt->execute([
            'g' => 'g-' . $sufijo,
            'e' => $sufijo . '@ejemplo.test',
            'n' => $nombre,
            'u' => 'u' . $sufijo,
        ]);

        return [(int) $this->pdo()->lastInsertId(), 'u' . $sufijo];
    }

    private function autenticarComo(int $userId): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $userId]);
    }

    private function conAmistadAceptada(): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO friendships (requester_id, addressee_id, status)
             VALUES (:r, :a, 'accepted')"
        );
        $stmt->execute(['r' => $this->visor, 'a' => $this->dueno]);
    }

    private function conElInterruptor(bool $encendido): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO user_privacy_settings (user_id, show_journal) VALUES (:u, :v)
             ON DUPLICATE KEY UPDATE show_journal = VALUES(show_journal)'
        );
        $stmt->execute(['u' => $this->dueno, 'v' => (int) $encendido]);
    }

    private function apuntarEnElDiario(string $entityId, string $titulo, string $fecha): void
    {
        $this->container()->get(JournalRepositoryInterface::class)->add(new JournalEntry(
            id: null,
            userId: $this->dueno,
            media: JournalEntry::MEDIA_MOVIE,
            entityId: $entityId,
            entityTitle: $titulo,
            entityCover: null,
            entryDate: $fecha,
            rating: null
        ));
    }

    private function pedir(?string $username = null): array
    {
        return $this->router()->dispatch('get_user_journal', [
            'username' => $username ?? $this->duenoUsername,
        ]);
    }

    private function assertVacioYSinPistas(array $r): void
    {
        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertSame(200, $r['http_code'], 'Un 403 confirmaría que hay un diario detrás');
        $this->assertSame([], $r['data']['entries']);
        $this->assertSame(0, $r['data']['total']);
        $this->assertFalse($r['data']['hasMore']);
    }

    // ---- Las tres peticiones del plan ---------------------------------------

    #[Test]
    public function a_stranger_gets_an_empty_list(): void
    {
        $this->conElInterruptor(true);   // encendido: lo que decide es la amistad

        $this->assertVacioYSinPistas($this->pedir());
    }

    #[Test]
    public function a_friend_gets_nothing_while_the_switch_is_off(): void
    {
        $this->conAmistadAceptada();
        $this->conElInterruptor(false);

        $this->assertVacioYSinPistas($this->pedir());
    }

    #[Test]
    public function a_friend_sees_the_journal_once_it_is_on(): void
    {
        $this->conAmistadAceptada();
        $this->conElInterruptor(true);

        $r = $this->pedir();

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertSame(2, $r['data']['total']);
        // De lo más reciente a lo más antiguo, como el diario propio.
        $this->assertSame('The Matrix', $r['data']['entries'][0]['entity_title']);
        $this->assertSame('Cadena perpetua', $r['data']['entries'][1]['entity_title']);
        $this->assertFalse($r['data']['hasMore']);
    }

    // ---- Y lo que no debe confirmar nada -------------------------------------

    #[Test]
    public function a_username_that_does_not_exist_answers_like_an_empty_journal(): void
    {
        $this->assertVacioYSinPistas($this->pedir('no-existe-' . bin2hex(random_bytes(3))));
    }

    #[Test]
    public function a_friendship_still_pending_is_not_enough(): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO friendships (requester_id, addressee_id, status)
             VALUES (:r, :a, 'pending')"
        );
        $stmt->execute(['r' => $this->visor, 'a' => $this->dueno]);
        $this->conElInterruptor(true);

        $this->assertVacioYSinPistas($this->pedir());
    }

    #[Test]
    public function your_own_public_profile_does_not_show_your_journal(): void
    {
        // Nadie es amigo de sí mismo: el propio va por `get_journal`. Se fija
        // aquí para que quede como decisión y no como efecto colateral.
        $this->conElInterruptor(true);
        $this->autenticarComo($this->dueno);

        $this->assertVacioYSinPistas($this->pedir());
    }

    #[Test]
    public function the_limit_is_capped_by_the_server(): void
    {
        $this->conAmistadAceptada();
        $this->conElInterruptor(true);

        $r = $this->router()->dispatch('get_user_journal', [
            'username' => $this->duenoUsername,
            'limit'    => 5000,
        ]);

        // No se cree al cliente: 50 es el tope, y con dos entradas se ven dos.
        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertCount(2, $r['data']['entries']);
    }

    #[Test]
    public function the_action_requires_a_username(): void
    {
        $r = $this->router()->dispatch('get_user_journal', []);

        $this->assertSame('error', $r['status'], json_encode($r));
    }
}
