<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Las seis acciones de clubs del M1, entrando por `ActionRouter`.
 *
 * Por integración y no solo con unitarios por el motivo de siempre en este
 * repo, y aquí con un ejemplo reciente: el M1 llegó a esta sesión con las 1.055
 * líneas de dominio, persistencia y controller **escritas y sin cablear** —ni
 * `routes.php`, ni el `match` de `ActionRouter`, ni `container.php`—, así que
 * las seis acciones no existían y ni una sola línea se había ejecutado nunca.
 * Unos unitarios sobre los use cases habrían estado en verde igualmente.
 *
 * `club`, `club_member` y el `'club'` del ENUM de `recommendations` llegan por
 * migración, así que esto comprueba además que la migración se aplicó.
 *
 * El 403 del club ajeno es el test que la interfaz no puede hacer, porque la
 * interfaz nunca ofrecerá ese botón.
 */
class ClubsTest extends IntegrationTestCase
{
    private int $yo;
    private int $amiga;
    private int $extranio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yo       = $this->crearUsuario('Dueño del club', 'david');
        $this->amiga    = $this->crearUsuario('La amiga invitada', 'ana');
        $this->extranio = $this->crearUsuario('Ni amigo ni miembro', 'nadie');
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    private function crearUsuario(string $nombre, ?string $username = null): int
    {
        $sufijo = bin2hex(random_bytes(4));
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name, username) VALUES (:g, :e, :n, :u)'
        );
        $stmt->execute([
            'g' => 'g-' . $sufijo,
            'e' => $sufijo . '@ejemplo.test',
            'n' => $nombre,
            'u' => $username ?? 'u' . $sufijo,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function hacerAmigos(int $unUsuario, int $otro): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT IGNORE INTO friendships (requester_id, addressee_id, status) VALUES (:a, :b, 'accepted')"
        );
        $stmt->execute(['a' => $unUsuario, 'b' => $otro]);
    }

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

    private function crearClub(array $extra = []): int
    {
        $this->comoUsuario($this->yo);
        $r = $this->router()->dispatch('create_club', $extra + ['name' => 'Los del jueves']);

        return (int) $r['data']['clubId'];
    }

    /** El dueño invita a `amiga` y `amiga` acepta; devuelve el id del club. */
    private function conMiembro(): int
    {
        $clubId = $this->crearClub();

        $this->hacerAmigos($this->yo, $this->amiga);
        $r = $this->router()->dispatch('invite_to_club', [
            'clubId' => $clubId, 'userId' => $this->amiga,
        ]);

        $this->comoUsuario($this->amiga);
        $this->router()->dispatch('accept_club_invitation', [
            'recommendationId' => $r['data']['recommendationId'],
        ]);

        $this->comoUsuario($this->yo);

        return $clubId;
    }

    // ------------------------------------------------------------------
    // Crear y listar
    // ------------------------------------------------------------------

    #[Test]
    public function a_club_can_be_created_and_its_owner_is_already_a_member(): void
    {
        $clubId = $this->crearClub(['description' => 'Un libro al mes']);

        $this->assertGreaterThan(0, $clubId);

        $r = $this->router()->dispatch('get_club', ['clubId' => $clubId]);

        $this->assertSame('success', $r['status']);
        $this->assertSame('Los del jueves', $r['data']['club']['name']);
        $this->assertSame('Un libro al mes', $r['data']['club']['description']);
        $this->assertTrue($r['data']['club']['is_owner']);

        // El alta del dueño como miembro no es redundante con `owner_id`: es lo
        // que hace que `findForUser` sea una sola consulta y que el creador
        // salga en su propio club.
        $this->assertCount(1, $r['data']['members']);
        $this->assertSame($this->yo, $r['data']['members'][0]['user_id']);
    }

    #[Test]
    public function a_brand_new_club_has_no_pick_and_an_empty_history(): void
    {
        $clubId = $this->crearClub();

        $r = $this->router()->dispatch('get_club', ['clubId' => $clubId]);

        // Llegan en el M2, pero el contrato ya tiene su forma para que el
        // frontend no cambie a mitad del plan.
        $this->assertNull($r['data']['pick']);
        $this->assertSame([], $r['data']['history']);
    }

    #[Test]
    public function a_club_without_a_name_is_rejected(): void
    {
        $this->comoUsuario($this->yo);
        $r = $this->router()->dispatch('create_club', ['name' => '   ']);

        $this->assertSame('error', $r['status']);
    }

    #[Test]
    public function get_my_clubs_lists_the_ones_i_belong_to_with_their_member_count(): void
    {
        $clubId = $this->conMiembro();

        $r = $this->router()->dispatch('get_my_clubs', []);

        $this->assertSame('success', $r['status']);
        $this->assertCount(1, $r['data']['clubs']);
        $this->assertSame($clubId, $r['data']['clubs'][0]['id']);
        $this->assertTrue($r['data']['clubs'][0]['is_owner']);
        $this->assertSame(2, $r['data']['clubs'][0]['member_count']);

        // Para la invitada es el mismo club, pero no es suyo.
        $this->comoUsuario($this->amiga);
        $r = $this->router()->dispatch('get_my_clubs', []);
        $this->assertCount(1, $r['data']['clubs']);
        $this->assertFalse($r['data']['clubs'][0]['is_owner']);
    }

    #[Test]
    public function get_my_clubs_does_not_leak_clubs_i_am_not_in(): void
    {
        $this->crearClub();

        $this->comoUsuario($this->extranio);
        $r = $this->router()->dispatch('get_my_clubs', []);

        $this->assertSame([], $r['data']['clubs']);
    }

    // ------------------------------------------------------------------
    // El permiso: o eres miembro o no lo eres
    // ------------------------------------------------------------------

    #[Test]
    public function get_club_of_someone_elses_club_is_403(): void
    {
        $clubId = $this->crearClub();

        $this->comoUsuario($this->extranio);
        $r = $this->router()->dispatch('get_club', ['clubId' => $clubId]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function get_club_of_a_club_that_does_not_exist_is_404(): void
    {
        $this->comoUsuario($this->yo);
        $r = $this->router()->dispatch('get_club', ['clubId' => 999999]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(404, $r['http_code']);
    }

    // ------------------------------------------------------------------
    // Invitar y aceptar, por la bandeja
    // ------------------------------------------------------------------

    #[Test]
    public function an_invitation_lands_in_the_inbox_and_grants_no_access_until_accepted(): void
    {
        $clubId = $this->crearClub();
        $this->hacerAmigos($this->yo, $this->amiga);

        $r = $this->router()->dispatch('invite_to_club', [
            'clubId' => $clubId, 'userId' => $this->amiga,
        ]);
        $this->assertSame('success', $r['status']);

        // Invitar NO da acceso: sería enseñarle su progreso a los demás sin
        // haberle preguntado.
        $this->comoUsuario($this->amiga);
        $this->assertSame(403, $this->router()->dispatch('get_club', ['clubId' => $clubId])['http_code']);

        // Y viaja por el buzón de siempre, con el nombre del club de título.
        $bandeja = $this->router()->dispatch('get_inbox', []);
        $this->assertCount(1, $bandeja['data']['recommendations']);
        $this->assertSame('club', $bandeja['data']['recommendations'][0]['entity_type']);
        $this->assertSame((string) $clubId, $bandeja['data']['recommendations'][0]['entity_id']);
        $this->assertSame('Los del jueves', $bandeja['data']['recommendations'][0]['entity_title']);

        $this->router()->dispatch('accept_club_invitation', [
            'recommendationId' => $r['data']['recommendationId'],
        ]);

        $r = $this->router()->dispatch('get_club', ['clubId' => $clubId]);
        $this->assertSame('success', $r['status']);
        $this->assertCount(2, $r['data']['members']);
    }

    #[Test]
    public function accepting_an_invitation_empties_the_inbox(): void
    {
        $this->conMiembro();

        $this->comoUsuario($this->amiga);
        $this->assertSame(0, $this->router()->dispatch('get_inbox_count', [])['data']['pending']);
    }

    #[Test]
    public function only_friends_can_be_invited(): void
    {
        $clubId = $this->crearClub();

        $r = $this->router()->dispatch('invite_to_club', [
            'clubId' => $clubId, 'userId' => $this->extranio,
        ]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(400, $r['http_code']);
    }

    #[Test]
    public function only_the_owner_can_invite(): void
    {
        $clubId = $this->conMiembro();
        $this->hacerAmigos($this->amiga, $this->extranio);

        // Miembro, sí; dueño, no. Si invitara cualquiera, un miembro decidiría
        // por el dueño ante quién se expone el progreso de todos los demás.
        $this->comoUsuario($this->amiga);
        $r = $this->router()->dispatch('invite_to_club', [
            'clubId' => $clubId, 'userId' => $this->extranio,
        ]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function the_same_person_cannot_be_invited_twice(): void
    {
        $clubId = $this->crearClub();
        $this->hacerAmigos($this->yo, $this->amiga);

        $this->router()->dispatch('invite_to_club', ['clubId' => $clubId, 'userId' => $this->amiga]);
        $r = $this->router()->dispatch('invite_to_club', ['clubId' => $clubId, 'userId' => $this->amiga]);

        // El UNIQUE del buzón ya lo impide; esto comprueba que sale como
        // mensaje legible y no como un 500 por clave duplicada.
        $this->assertSame('error', $r['status']);
        $this->assertSame(400, $r['http_code']);
    }

    #[Test]
    public function a_member_cannot_be_invited_again(): void
    {
        $clubId = $this->conMiembro();

        $r = $this->router()->dispatch('invite_to_club', [
            'clubId' => $clubId, 'userId' => $this->amiga,
        ]);

        $this->assertSame('error', $r['status']);
    }

    #[Test]
    public function someone_elses_invitation_cannot_be_accepted(): void
    {
        $clubId = $this->crearClub();
        $this->hacerAmigos($this->yo, $this->amiga);
        $r = $this->router()->dispatch('invite_to_club', [
            'clubId' => $clubId, 'userId' => $this->amiga,
        ]);

        $this->comoUsuario($this->extranio);
        $r = $this->router()->dispatch('accept_club_invitation', [
            'recommendationId' => $r['data']['recommendationId'],
        ]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function a_list_invitation_cannot_be_accepted_as_a_club_one(): void
    {
        // Si se dejara, `entity_id` sería el id de una lista y se usaría como
        // id de club. Es el mismo motivo por el que `AcceptCollaborationUseCase`
        // rechaza lo que no es una invitación a lista.
        $this->comoUsuario($this->yo);
        $listId = (int) $this->router()->dispatch('create_list', ['name' => 'Una lista'])['data']['listId'];
        $this->router()->dispatch('update_list', ['listId' => $listId, 'visibility' => 'collaborative']);
        $this->hacerAmigos($this->yo, $this->amiga);
        $r = $this->router()->dispatch('invite_collaborator', [
            'listId' => $listId, 'userId' => $this->amiga,
        ]);

        $this->comoUsuario($this->amiga);
        $r = $this->router()->dispatch('accept_club_invitation', [
            'recommendationId' => $r['data']['recommendationId'],
        ]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(404, $r['http_code']);
    }

    // ------------------------------------------------------------------
    // Salir
    // ------------------------------------------------------------------

    #[Test]
    public function a_member_can_leave_and_loses_access(): void
    {
        $clubId = $this->conMiembro();

        $this->comoUsuario($this->amiga);
        $r = $this->router()->dispatch('leave_club', ['clubId' => $clubId]);
        $this->assertSame('success', $r['status']);

        $this->assertSame(403, $this->router()->dispatch('get_club', ['clubId' => $clubId])['http_code']);
        $this->assertSame([], $this->router()->dispatch('get_my_clubs', [])['data']['clubs']);

        // Y el club sigue en pie para el dueño, con un miembro menos.
        $this->comoUsuario($this->yo);
        $this->assertCount(1, $this->router()->dispatch('get_club', ['clubId' => $clubId])['data']['members']);
    }

    #[Test]
    public function the_owner_cannot_leave_their_own_club(): void
    {
        $clubId = $this->conMiembro();

        // Dejaría un club sin nadie que pueda invitar ni elegir ítem.
        $r = $this->router()->dispatch('leave_club', ['clubId' => $clubId]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function a_non_member_cannot_leave_a_club(): void
    {
        $clubId = $this->crearClub();

        $this->comoUsuario($this->extranio);
        $r = $this->router()->dispatch('leave_club', ['clubId' => $clubId]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }
}
