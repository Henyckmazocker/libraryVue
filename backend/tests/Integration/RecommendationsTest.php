<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Las cuatro acciones de la bandeja, entrando por `ActionRouter`.
 *
 * Por integración y no solo con unitarios porque el fallo típico de este repo
 * vive justo donde un mock de PDO no llega: la acción declarada en dos de sus
 * tres sitios, o un SQL que no casa con el esquema. `recommendations` es una
 * tabla que llega por migración, así que además comprueba que la migración se
 * aplicó — un `composer test` verde sobre un esquema sin ella es imposible.
 */
class RecommendationsTest extends IntegrationTestCase
{
    private int $yo;
    private int $amigo;
    private int $desconocido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yo          = $this->crearUsuario('Quien recibe');
        $this->amigo       = $this->crearUsuario('Quien recomienda');
        $this->desconocido = $this->crearUsuario('Quien no es amigo de nadie');

        $this->hacerAmigos($this->yo, $this->amigo);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    private function crearUsuario(string $nombre): int
    {
        $sufijo = bin2hex(random_bytes(4));
        $stmt = $this->pdo()->prepare('INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)');
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => $nombre]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function hacerAmigos(int $unUsuario, int $otro): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO friendships (requester_id, addressee_id, status) VALUES (:a, :b, 'accepted')"
        );
        $stmt->execute(['a' => $unUsuario, 'b' => $otro]);
    }

    /** Todo lo que sigue en la petición va como este usuario. */
    private function comoUsuario(int $userId): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->container()
            ->get(JWTService::class)
            ->generate(['user_id' => $userId]);
    }

    private function router(): ActionRouter
    {
        return $this->container()->get(ActionRouter::class);
    }

    /** El amigo me manda un ítem; devuelve la respuesta cruda. */
    private function meRecomienda(array $extra = []): array
    {
        $this->comoUsuario($this->amigo);

        return $this->router()->dispatch('send_recommendation', $extra + [
            'recipientId' => $this->yo,
            'entityType'  => 'movie',
            'entityId'    => 'tt0111161',
            'entityTitle' => 'Cadena perpetua',
            'comment'     => 'Esta te va a gustar',
        ]);
    }

    #[Test]
    public function a_friend_can_send_me_an_item(): void
    {
        $r = $this->meRecomienda();

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertIsInt($r['data']['recommendationId']);

        $stmt = $this->pdo()->prepare('SELECT * FROM recommendations WHERE id = :id');
        $stmt->execute(['id' => $r['data']['recommendationId']]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame($this->amigo, (int) $fila['sender_id']);
        $this->assertSame($this->yo, (int) $fila['recipient_id']);
        $this->assertSame('movie', $fila['entity_type']);
        $this->assertSame('pending', $fila['status']);
        $this->assertSame('Esta te va a gustar', $fila['comment']);
        $this->assertNull($fila['resolved_at']);
    }

    #[Test]
    public function recommending_to_a_stranger_is_a_readable_400(): void
    {
        $this->comoUsuario($this->desconocido);

        $r = $this->router()->dispatch('send_recommendation', [
            'recipientId' => $this->yo,
            'entityType'  => 'book',
            'entityId'    => '9788423353187',
        ]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(400, $r['http_code']);
        $this->assertStringContainsString('friends', $r['message']);
        // Y no llega a la tabla: la guarda es de dominio, no de presentación.
        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM recommendations')->fetchColumn());
    }

    #[Test]
    public function the_same_item_twice_is_a_409_not_a_duplicate_key_500(): void
    {
        $this->meRecomienda();
        $r = $this->meRecomienda();

        $this->assertSame('error', $r['status']);
        $this->assertSame(409, $r['http_code'], json_encode($r));
        $this->assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM recommendations')->fetchColumn());
    }

    #[Test]
    public function the_inbox_lists_what_they_sent_me_with_who_sent_it(): void
    {
        $this->meRecomienda();
        $this->comoUsuario($this->yo);

        $r = $this->router()->dispatch('get_inbox', []);

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertSame(1, $r['data']['total']);

        $recomendacion = $r['data']['recommendations'][0];
        $this->assertSame('Cadena perpetua', $recomendacion['entity_title']);
        $this->assertSame('movie', $recomendacion['entity_type']);
        // El remitente se resuelve al leer, no se copia en la fila: un nombre
        // cambia y el título de una película no.
        $this->assertSame('Quien recomienda', $recomendacion['sender']['name']);
    }

    #[Test]
    public function the_count_only_sees_my_own_pending_ones(): void
    {
        $this->meRecomienda();

        // Lo que le mandan a otro no cuenta para el mío.
        $this->hacerAmigos($this->amigo, $this->desconocido);
        $this->comoUsuario($this->amigo);
        $this->router()->dispatch('send_recommendation', [
            'recipientId' => $this->desconocido,
            'entityType'  => 'game',
            'entityId'    => '1020',
        ]);

        $this->comoUsuario($this->yo);
        $r = $this->router()->dispatch('get_inbox_count', []);

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertSame(1, $r['data']['pending']);
    }

    #[Test]
    public function dismissing_it_takes_it_off_the_count_and_stamps_the_row(): void
    {
        $id = $this->meRecomienda()['data']['recommendationId'];
        $this->comoUsuario($this->yo);

        $r = $this->router()->dispatch('resolve_recommendation', [
            'recommendationId' => $id,
            'resolution'       => 'dismissed',
        ]);

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertSame(0, $this->router()->dispatch('get_inbox_count', [])['data']['pending']);

        $stmt = $this->pdo()->prepare('SELECT status, resolved_at FROM recommendations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('dismissed', $fila['status']);
        // La fila se queda: es el historial que permitiría algún día decirle al
        // que recomienda si le hicieron caso.
        $this->assertNotNull($fila['resolved_at']);
    }

    #[Test]
    public function resolving_someone_elses_recommendation_is_a_403(): void
    {
        $id = $this->meRecomienda()['data']['recommendationId'];

        // El propio remitente no puede resolverla: es del destinatario.
        $this->comoUsuario($this->amigo);
        $r = $this->router()->dispatch('resolve_recommendation', [
            'recommendationId' => $id,
            'resolution'       => 'added',
        ]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code'], json_encode($r));

        $stmt = $this->pdo()->prepare('SELECT status FROM recommendations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $this->assertSame('pending', $stmt->fetchColumn());
    }
}
