<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Las siete acciones de listas del M1, entrando por `ActionRouter`.
 *
 * Por integración y no solo con unitarios por el fallo típico de este repo: la
 * acción declarada en dos de sus tres sitios, o un SQL que no casa con el
 * esquema. `media_list` llega por migración, así que además comprueba que la
 * migración se aplicó.
 *
 * El 403 de la lista ajena es el test que la interfaz no puede hacer, porque la
 * interfaz nunca ofrecerá ese botón.
 */
class MediaListsTest extends IntegrationTestCase
{
    private int $yo;
    private int $otro;
    private int $tercero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yo   = $this->crearUsuario('Dueña de las listas', 'ana');
        $this->otro = $this->crearUsuario('Alguien que no es nadie aquí', 'luis');
        $this->tercero = $this->crearUsuario('Ni amigo ni colaborador', 'nadie');
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
        // `IGNORE` porque un test puede montar dos listas con el mismo par de
        // usuarios, y `unique_friendship` no distingue.
        $stmt = $this->pdo()->prepare(
            "INSERT IGNORE INTO friendships (requester_id, addressee_id, status) VALUES (:a, :b, 'accepted')"
        );
        $stmt->execute(['a' => $unUsuario, 'b' => $otro]);
    }

    /** El dueño invita a `otro` y `otro` acepta; devuelve el id de la lista. */
    private function conColaborador(): int
    {
        $listId = $this->crearLista(['name' => 'A cuatro manos']);
        $this->router()->dispatch('update_list', ['listId' => $listId, 'visibility' => 'collaborative']);

        $this->hacerAmigos($this->yo, $this->otro);
        $r = $this->router()->dispatch('invite_collaborator', [
            'listId' => $listId, 'userId' => $this->otro,
        ]);

        $this->comoUsuario($this->otro);
        $this->router()->dispatch('accept_collaboration', [
            'recommendationId' => $r['data']['recommendationId'],
        ]);

        return $listId;
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

    private function crearLista(array $extra = []): int
    {
        $this->comoUsuario($this->yo);
        $r = $this->router()->dispatch('create_list', $extra + ['name' => 'Para el verano']);

        return (int) $r['data']['listId'];
    }

    // ------------------------------------------------------------------
    // CRUD de la lista
    // ------------------------------------------------------------------

    #[Test]
    public function a_list_can_be_created_and_defaults_to_private(): void
    {
        $listId = $this->crearLista(['description' => 'Lo que quiero antes de septiembre']);

        $this->assertGreaterThan(0, $listId);

        $r = $this->router()->dispatch('get_list', ['listId' => $listId]);
        $this->assertSame('success', $r['status']);
        $this->assertSame('Para el verano', $r['data']['list']['name']);
        $this->assertSame('private', $r['data']['list']['visibility']);
        $this->assertTrue($r['data']['list']['can_edit']);
        $this->assertTrue($r['data']['list']['is_owner']);
        $this->assertSame([], $r['data']['items']);
    }

    #[Test]
    public function a_list_can_be_renamed_and_made_public(): void
    {
        $listId = $this->crearLista();

        $r = $this->router()->dispatch('update_list', [
            'listId'     => $listId,
            'name'       => 'Para el otoño',
            'visibility' => 'public',
        ]);
        $this->assertSame('success', $r['status']);

        $r = $this->router()->dispatch('get_list', ['listId' => $listId]);
        $this->assertSame('Para el otoño', $r['data']['list']['name']);
        $this->assertSame('public', $r['data']['list']['visibility']);
    }

    /**
     * `null` en el comando significa «no lo toques», que es distinto de
     * «ponlo a vacío»: sin la bandera de `UpdateListCommand`, renombrar una
     * lista le borraría la descripción.
     */
    #[Test]
    public function renaming_a_list_does_not_wipe_its_description(): void
    {
        $listId = $this->crearLista(['description' => 'No me borres']);

        $this->router()->dispatch('update_list', ['listId' => $listId, 'name' => 'Otro nombre']);

        $r = $this->router()->dispatch('get_list', ['listId' => $listId]);
        $this->assertSame('No me borres', $r['data']['list']['description']);
    }

    #[Test]
    public function a_list_can_be_deleted(): void
    {
        $listId = $this->crearLista();

        $r = $this->router()->dispatch('delete_list', ['listId' => $listId]);
        $this->assertSame('success', $r['status']);

        $r = $this->router()->dispatch('get_list', ['listId' => $listId]);
        $this->assertSame(404, $r['http_code']);
    }

    #[Test]
    public function my_lists_come_back_with_their_item_count(): void
    {
        $conItems = $this->crearLista(['name' => 'Con cosas']);
        $vacia    = $this->crearLista(['name' => 'Vacía']);

        $this->router()->dispatch('add_list_item', [
            'listId' => $conItems, 'entityType' => 'book', 'entityId' => '9780141036144',
        ]);

        $r = $this->router()->dispatch('get_my_lists', []);
        $this->assertSame('success', $r['status']);

        $porId = [];
        foreach ($r['data']['lists'] as $lista) {
            $porId[(int) $lista['id']] = $lista;
        }

        $this->assertCount(2, $porId);
        $this->assertSame(1, $porId[$conItems]['item_count']);
        // La lista vacía no sale del GROUP BY y su tarjeta necesita el 0.
        $this->assertSame(0, $porId[$vacia]['item_count']);
    }

    // ------------------------------------------------------------------
    // Los ítems, mezclando medios
    // ------------------------------------------------------------------

    #[Test]
    public function a_list_holds_a_book_and_a_movie_at_the_same_time(): void
    {
        $listId = $this->crearLista();

        $r = $this->router()->dispatch('add_list_item', [
            'listId'      => $listId,
            'entityType'  => 'book',
            'entityId'    => '9780141036144',
            'entityTitle' => '1984',
        ]);
        $this->assertSame('success', $r['status'], json_encode($r));

        $r = $this->router()->dispatch('add_list_item', [
            'listId'      => $listId,
            'entityType'  => 'movie',
            'entityId'    => 'tt0111161',
            'entityTitle' => 'Cadena perpetua',
        ]);
        $this->assertSame('success', $r['status']);

        $r = $this->router()->dispatch('get_list', ['listId' => $listId]);
        $tipos = array_column($r['data']['items'], 'entity_type');

        $this->assertCount(2, $r['data']['items']);
        $this->assertSame(['book', 'movie'], $tipos);
    }

    #[Test]
    public function the_same_item_cannot_be_added_twice(): void
    {
        $listId = $this->crearLista();
        $item = ['listId' => $listId, 'entityType' => 'album', 'entityId' => 'f0b7b7a8-mbid'];

        $this->router()->dispatch('add_list_item', $item);
        $r = $this->router()->dispatch('add_list_item', $item);

        // 409, no un 500 por clave duplicada.
        $this->assertSame(409, $r['http_code']);
    }

    #[Test]
    public function an_item_can_be_removed_from_the_list(): void
    {
        $listId = $this->crearLista();
        $r = $this->router()->dispatch('add_list_item', [
            'listId' => $listId, 'entityType' => 'game', 'entityId' => '1020',
        ]);
        $itemId = (int) $r['data']['id'];

        $r = $this->router()->dispatch('remove_list_item', ['listId' => $listId, 'itemId' => $itemId]);
        $this->assertSame('success', $r['status']);

        $r = $this->router()->dispatch('get_list', ['listId' => $listId]);
        $this->assertSame([], $r['data']['items']);
    }

    /**
     * El permiso se comprueba sobre `listId` y el borrado va por `itemId`: sin
     * comprobar que el ítem es de esa lista, quien pueda editar una cualquiera
     * podría vaciar la de otro.
     */
    #[Test]
    public function an_item_cannot_be_removed_through_a_different_list(): void
    {
        $conElItem = $this->crearLista(['name' => 'La que tiene el ítem']);
        $miOtra    = $this->crearLista(['name' => 'Otra mía']);

        $r = $this->router()->dispatch('add_list_item', [
            'listId' => $conElItem, 'entityType' => 'video', 'entityId' => 'dQw4w9WgXcQ',
        ]);
        $itemId = (int) $r['data']['id'];

        $r = $this->router()->dispatch('remove_list_item', ['listId' => $miOtra, 'itemId' => $itemId]);
        $this->assertSame('error', $r['status']);

        $r = $this->router()->dispatch('get_list', ['listId' => $conElItem]);
        $this->assertCount(1, $r['data']['items']);
    }

    // ------------------------------------------------------------------
    // La privacidad, atacada a mano
    // ------------------------------------------------------------------

    #[Test]
    public function a_private_list_of_someone_else_answers_403(): void
    {
        $listId = $this->crearLista();

        $this->comoUsuario($this->otro);
        $r = $this->router()->dispatch('get_list', ['listId' => $listId]);

        // 403 y no 404: existe, pero no es suya. Es la distinción que
        // `ResolveRecommendationUseCase` ya hacía.
        $this->assertSame(403, $r['http_code']);
        $this->assertArrayNotHasKey('list', $r['data'] ?? []);
    }

    #[Test]
    public function a_public_list_is_visible_to_anyone_but_not_editable(): void
    {
        $listId = $this->crearLista();
        $this->router()->dispatch('update_list', ['listId' => $listId, 'visibility' => 'public']);

        $this->comoUsuario($this->otro);

        $r = $this->router()->dispatch('get_list', ['listId' => $listId]);
        $this->assertSame('success', $r['status']);
        $this->assertFalse($r['data']['list']['can_edit']);
        $this->assertFalse($r['data']['list']['is_owner']);

        $r = $this->router()->dispatch('add_list_item', [
            'listId' => $listId, 'entityType' => 'book', 'entityId' => '9780141036144',
        ]);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function only_the_owner_can_rename_or_delete_a_list(): void
    {
        $listId = $this->crearLista();
        $this->router()->dispatch('update_list', ['listId' => $listId, 'visibility' => 'public']);

        $this->comoUsuario($this->otro);

        $r = $this->router()->dispatch('update_list', ['listId' => $listId, 'name' => 'Mía ahora']);
        $this->assertSame(403, $r['http_code']);

        $r = $this->router()->dispatch('delete_list', ['listId' => $listId]);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function someone_elses_list_never_shows_up_in_my_lists(): void
    {
        $this->crearLista();

        $this->comoUsuario($this->otro);
        $r = $this->router()->dispatch('get_my_lists', []);

        $this->assertSame([], $r['data']['lists']);
    }

    // ------------------------------------------------------------------
    // El perfil de otro: solo lo público
    // ------------------------------------------------------------------

    #[Test]
    public function the_public_lists_of_a_user_show_up_on_their_profile(): void
    {
        $listId = $this->crearLista(['name' => 'Lo que recomiendo']);
        $this->router()->dispatch('update_list', ['listId' => $listId, 'visibility' => 'public']);
        $this->router()->dispatch('add_list_item', [
            'listId' => $listId, 'entityType' => 'book', 'entityId' => '9780141036144',
        ]);

        $this->comoUsuario($this->otro);
        $r = $this->router()->dispatch('get_user_lists', ['username' => 'ana']);

        $this->assertSame('success', $r['status']);
        $this->assertCount(1, $r['data']['lists']);
        $this->assertSame('Lo que recomiendo', $r['data']['lists'][0]['name']);
        // El contador va en la misma respuesta: la tarjeta no pide nada más.
        $this->assertSame(1, $r['data']['lists'][0]['item_count']);
    }

    /**
     * La prueba que la interfaz no puede hacer, porque la interfaz nunca
     * ofrecerá ese botón: una privada no sale ni por la vista ni llamando a
     * `get_list` a mano con su id.
     */
    #[Test]
    public function a_private_list_appears_nowhere_not_even_called_by_id(): void
    {
        $privada = $this->crearLista(['name' => 'Solo mía']);
        $publica = $this->crearLista(['name' => 'Esta sí']);
        $this->router()->dispatch('update_list', ['listId' => $publica, 'visibility' => 'public']);

        $this->comoUsuario($this->otro);

        $nombres = array_column($this->router()->dispatch('get_user_lists', ['username' => 'ana'])['data']['lists'], 'name');
        $this->assertSame(['Esta sí'], $nombres);

        $r = $this->router()->dispatch('get_list', ['listId' => $privada]);
        $this->assertSame(403, $r['http_code']);
    }

    /**
     * `collaborative` NO es pública, y este es el test que lo fija: es la
     * lectura que se hace mal de la tabla de verdad.
     */
    #[Test]
    public function a_collaborative_list_is_not_public_and_stays_off_the_profile(): void
    {
        $listId = $this->crearLista(['name' => 'Entre unos pocos']);
        $this->router()->dispatch('update_list', ['listId' => $listId, 'visibility' => 'collaborative']);

        $this->comoUsuario($this->otro);

        $this->assertSame([], $this->router()->dispatch('get_user_lists', ['username' => 'ana'])['data']['lists']);
        $this->assertSame(403, $this->router()->dispatch('get_list', ['listId' => $listId])['http_code']);
    }

    #[Test]
    public function a_username_that_does_not_exist_is_a_404_not_an_empty_list(): void
    {
        // Con sesión: la acción lleva `Auth`, así que sin ella el 401 llega
        // antes y taparía lo que este test viene a comprobar.
        $this->comoUsuario($this->yo);

        $r = $this->router()->dispatch('get_user_lists', ['username' => 'nadie-con-este-nombre']);

        // Una lista vacía diría «este usuario no tiene listas públicas», que es
        // una respuesta distinta y equivocada.
        $this->assertSame(404, $r['http_code']);
    }

    // ------------------------------------------------------------------
    // M4 — colaboración
    // ------------------------------------------------------------------

    #[Test]
    public function inviting_a_collaborator_does_not_grant_access_by_itself(): void
    {
        $listId = $this->crearLista();
        $this->hacerAmigos($this->yo, $this->otro);

        $r = $this->router()->dispatch('invite_collaborator', ['listId' => $listId, 'userId' => $this->otro]);
        $this->assertSame('success', $r['status'], json_encode($r));

        // Todavía NO puede: invitar crea una fila pendiente, no da acceso.
        // Meterlo aquí sería añadir a alguien a tu lista sin preguntarle.
        $this->comoUsuario($this->otro);
        $this->assertSame(403, $this->router()->dispatch('get_list', ['listId' => $listId])['http_code']);
    }

    #[Test]
    public function the_invitation_lands_in_the_inbox_as_its_own_type(): void
    {
        $listId = $this->crearLista(['name' => 'A cuatro manos']);
        $this->hacerAmigos($this->yo, $this->otro);
        $this->router()->dispatch('invite_collaborator', ['listId' => $listId, 'userId' => $this->otro]);

        $this->comoUsuario($this->otro);
        $bandeja = $this->router()->dispatch('get_inbox', [])['data']['recommendations'];

        $this->assertCount(1, $bandeja);
        $this->assertSame('list', $bandeja[0]['entity_type']);
        $this->assertSame((string) $listId, $bandeja[0]['entity_id']);
        // El nombre se copia, como el título de una película.
        $this->assertSame('A cuatro manos', $bandeja[0]['entity_title']);
        // Y suma en la campanita, sin tocar `get_inbox_count`.
        $this->assertSame(1, $this->router()->dispatch('get_inbox_count', [])['data']['pending']);
    }

    #[Test]
    public function accepting_the_invitation_is_what_grants_access(): void
    {
        $listId = $this->conColaborador();

        // `conColaborador` deja la sesión en `otro`.
        $r = $this->router()->dispatch('get_list', ['listId' => $listId]);
        $this->assertSame('success', $r['status']);
        $this->assertTrue($r['data']['list']['can_edit']);
        $this->assertFalse($r['data']['list']['is_owner']);

        // Y puede editar el CONTENIDO de verdad.
        $r = $this->router()->dispatch('add_list_item', [
            'listId' => $listId, 'entityType' => 'book', 'entityId' => '9780141036144',
        ]);
        $this->assertSame('success', $r['status']);
    }

    #[Test]
    public function a_third_party_still_gets_403_on_a_collaborative_list(): void
    {
        $listId = $this->conColaborador();

        $this->comoUsuario($this->tercero);
        $this->assertSame(403, $this->router()->dispatch('get_list', ['listId' => $listId])['http_code']);
        $this->assertSame(403, $this->router()->dispatch('add_list_item', [
            'listId' => $listId, 'entityType' => 'book', 'entityId' => '9780141036144',
        ])['http_code']);
    }

    #[Test]
    public function a_collaborator_shows_up_in_the_list_but_cannot_rename_or_delete_it(): void
    {
        $listId = $this->conColaborador();

        $colaboradores = $this->router()->dispatch('get_list', ['listId' => $listId])['data']['collaborators'];
        $this->assertSame([$this->otro], array_column($colaboradores, 'user_id'));
        $this->assertSame('luis', $colaboradores[0]['username']);

        // `canEdit` abre el contenido, no la lista misma.
        $this->assertSame(403, $this->router()->dispatch('update_list', [
            'listId' => $listId, 'name' => 'Mía ahora',
        ])['http_code']);
        $this->assertSame(403, $this->router()->dispatch('delete_list', ['listId' => $listId])['http_code']);
        // Ni invitar a más gente.
        $this->assertSame(403, $this->router()->dispatch('invite_collaborator', [
            'listId' => $listId, 'userId' => $this->tercero,
        ])['http_code']);
    }

    #[Test]
    public function you_can_only_invite_friends(): void
    {
        $listId = $this->crearLista();

        // Sin amistad aceptada por medio.
        $r = $this->router()->dispatch('invite_collaborator', ['listId' => $listId, 'userId' => $this->tercero]);

        $this->assertSame(400, $r['http_code']);
    }

    #[Test]
    public function an_invitation_that_is_not_yours_cannot_be_accepted(): void
    {
        $listId = $this->crearLista();
        $this->hacerAmigos($this->yo, $this->otro);
        $r = $this->router()->dispatch('invite_collaborator', ['listId' => $listId, 'userId' => $this->otro]);

        $this->comoUsuario($this->tercero);
        $this->assertSame(403, $this->router()->dispatch('accept_collaboration', [
            'recommendationId' => $r['data']['recommendationId'],
        ])['http_code']);
    }

    /**
     * Una recomendación de un ítem no se acepta por aquí: si se dejara,
     * `entity_id` sería un ISBN y se usaría como id de lista.
     */
    #[Test]
    public function a_plain_recommendation_cannot_be_accepted_as_a_collaboration(): void
    {
        $this->comoUsuario($this->yo);
        $this->hacerAmigos($this->yo, $this->otro);
        $r = $this->router()->dispatch('send_recommendation', [
            'recipientId' => $this->otro, 'entityType' => 'book', 'entityId' => '9780141036144',
        ]);

        $this->comoUsuario($this->otro);
        $this->assertSame(404, $this->router()->dispatch('accept_collaboration', [
            'recommendationId' => $r['data']['recommendationId'],
        ])['http_code']);
    }

    #[Test]
    public function a_list_cannot_be_recommended_as_if_it_were_an_item(): void
    {
        $this->comoUsuario($this->yo);
        $this->hacerAmigos($this->yo, $this->otro);

        // La columna acepta `list`, pero `send_recommendation` valida contra los
        // cinco medios: sin esa separación, la bandeja intentaría dar de alta
        // una lista en la biblioteca.
        $r = $this->router()->dispatch('send_recommendation', [
            'recipientId' => $this->otro, 'entityType' => 'list', 'entityId' => '1',
        ]);

        $this->assertSame('error', $r['status']);
        $this->assertNotSame(201, $r['http_code']);
    }

    #[Test]
    public function the_owner_can_remove_a_collaborator_and_a_collaborator_can_leave(): void
    {
        $listId = $this->conColaborador();

        // El colaborador se va solo.
        $this->assertSame('success', $this->router()->dispatch('remove_collaborator', [
            'listId' => $listId, 'userId' => $this->otro,
        ])['status']);
        $this->assertSame(403, $this->router()->dispatch('get_list', ['listId' => $listId])['http_code']);

        // Y el dueño puede sacar a quien vuelva a entrar.
        $this->comoUsuario($this->yo);
        $listId2 = $this->conColaborador();
        $this->comoUsuario($this->yo);
        $this->assertSame('success', $this->router()->dispatch('remove_collaborator', [
            'listId' => $listId2, 'userId' => $this->otro,
        ])['status']);
    }

    /**
     * Bajar de `collaborative` deja colaboradores que seguirían pudiendo editar
     * —`canEdit` consulta la tabla en las TRES visibilidades— sin que la
     * interfaz lo enseñe. Se borran.
     */
    #[Test]
    public function downgrading_from_collaborative_removes_the_collaborators(): void
    {
        $listId = $this->conColaborador();

        $this->comoUsuario($this->yo);
        $this->router()->dispatch('update_list', ['listId' => $listId, 'visibility' => 'private']);

        $this->assertSame([], $this->router()->dispatch('get_list', ['listId' => $listId])['data']['collaborators']);

        $this->comoUsuario($this->otro);
        $this->assertSame(403, $this->router()->dispatch('get_list', ['listId' => $listId])['http_code']);
    }

    #[Test]
    public function my_own_public_lists_come_back_from_my_own_profile(): void
    {
        $listId = $this->crearLista();
        $this->router()->dispatch('update_list', ['listId' => $listId, 'visibility' => 'public']);

        // Sin cambiar de sesión: mirarse a uno mismo no es un caso especial.
        $r = $this->router()->dispatch('get_user_lists', ['username' => 'ana']);

        $this->assertCount(1, $r['data']['lists']);
    }
}
