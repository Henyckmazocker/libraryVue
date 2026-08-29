<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * La votación del club: la ronda, las propuestas y el voto.
 *
 * Va aparte de `ClubPickTest` por el mismo motivo por el que aquel se separó de
 * `ClubsTest`: su `setUp` es otro. Aquí no hace falta biblioteca sembrada —lo
 * que se propone es un ítem de catálogo que **nadie tiene guardado**— y en
 * cambio hacen falta clubs de varios tamaños.
 *
 * Y es de INTEGRACIÓN por lo que dice el `CLAUDE.md`: la lógica de la ronda
 * está fijada por unitarios en `ClubRoundResolverTest`, pero lo que un mock no
 * puede ver es la acción declarada a medias en uno de los tres sitios, ni el
 * SQL que no casa con el esquema. Se entra por `ActionRouter`.
 */
class ClubVotingTest extends IntegrationTestCase
{
    private int $yo;
    private int $amiga;
    private int $tercero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yo      = $this->crearUsuario('Dueño del club');
        $this->amiga   = $this->crearUsuario('La otra miembro');
        $this->tercero = $this->crearUsuario('El tercero');
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Sembrado
    // ------------------------------------------------------------------

    private function crearUsuario(string $nombre): int
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

    /** Club del que `yo` es dueño, con los miembros que se le pasen dentro. */
    private function club(int ...$otros): int
    {
        $this->comoUsuario($this->yo);
        $clubId = (int) $this->router()->dispatch('create_club', ['name' => 'Los del jueves'])['data']['clubId'];

        foreach ($otros as $miembro) {
            $this->comoUsuario($this->yo);
            $this->hacerAmigos($this->yo, $miembro);
            $r = $this->router()->dispatch('invite_to_club', ['clubId' => $clubId, 'userId' => $miembro]);

            $this->comoUsuario($miembro);
            $this->router()->dispatch('accept_club_invitation', [
                'recommendationId' => $r['data']['recommendationId'],
            ]);
        }

        $this->comoUsuario($this->yo);

        return $clubId;
    }

    private function verClub(int $clubId): array
    {
        return $this->router()->dispatch('get_club', ['clubId' => $clubId])['data'];
    }

    /** Cuántas rondas tiene el club en la base, cerradas o no. */
    private function rondasEnLaBase(int $clubId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM club_round WHERE club_id = :c');
        $stmt->execute(['c' => $clubId]);

        return (int) $stmt->fetchColumn();
    }

    /** Cuántas propuestas hay en las rondas de ese club. */
    private function propuestasEnLaBase(int $clubId): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM club_proposal p JOIN club_round r ON r.id = p.round_id
             WHERE r.club_id = :c'
        );
        $stmt->execute(['c' => $clubId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Propone, leyendo el club antes — que es lo que hace el cliente real: la
     * ronda la abre `get_club`, y el botón de proponer está en la pantalla que
     * esa lectura pinta. Sin la lectura, `propose_club_item` responde 409
     * porque no hay ronda abierta, y es correcto que lo haga.
     */
    private function proponer(int $clubId, string $entityId, ?string $titulo = null): array
    {
        $this->router()->dispatch('get_club', ['clubId' => $clubId]);

        return $this->router()->dispatch('propose_club_item', [
            'clubId'      => $clubId,
            'entityType'  => 'movie',
            'entityId'    => $entityId,
            'entityTitle' => $titulo,
        ]);
    }

    /**
     * Cierra la ronda abierta del club dejando a `$ganador` como autor de la
     * propuesta ganadora. Se hace por SQL y no por la API porque `close_club_vote`
     * es del M3: aquí solo hace falta el ESTADO del que parte la rotación.
     */
    private function cerrarRondaCon(int $clubId, int $ganador): void
    {
        $this->verClub($clubId);   // abre la ronda

        $stmt = $this->pdo()->prepare(
            "SELECT id FROM club_round WHERE club_id = :c AND phase <> 'closed' LIMIT 1"
        );
        $stmt->execute(['c' => $clubId]);
        $rondaId = (int) $stmt->fetchColumn();

        $stmt = $this->pdo()->prepare(
            "INSERT INTO club_proposal (round_id, user_id, entity_type, entity_id)
             VALUES (:r, :u, 'movie', 'tt0000001')"
        );
        $stmt->execute(['r' => $rondaId, 'u' => $ganador]);
        $propuestaId = (int) $this->pdo()->lastInsertId();

        $stmt = $this->pdo()->prepare(
            "UPDATE club_round SET phase = 'closed', winning_proposal_id = :p, closed_at = NOW()
             WHERE id = :r"
        );
        $stmt->execute(['p' => $propuestaId, 'r' => $rondaId]);
    }

    private function votar(int $clubId, int $proposalId): array
    {
        return $this->router()->dispatch('vote_club_proposal', [
            'clubId'     => $clubId,
            'proposalId' => $proposalId,
        ]);
    }

    /** El id de la propuesta de ese miembro en la ronda abierta del club. */
    private function propuestaDe(int $clubId, int $userId): int
    {
        $stmt = $this->pdo()->prepare(
            "SELECT p.id FROM club_proposal p JOIN club_round r ON r.id = p.round_id
             WHERE r.club_id = :c AND r.phase <> 'closed' AND p.user_id = :u LIMIT 1"
        );
        $stmt->execute(['c' => $clubId, 'u' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    private function votosEnLaBase(int $clubId): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM club_vote v JOIN club_round r ON r.id = v.round_id WHERE r.club_id = :c'
        );
        $stmt->execute(['c' => $clubId]);

        return (int) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------------
    // M1 — la ronda se abre sola
    // ------------------------------------------------------------------

    #[Test]
    public function a_club_without_an_active_item_opens_a_round(): void
    {
        $clubId = $this->club($this->amiga);

        $club = $this->verClub($clubId);

        // Un club recién creado no tiene ítem, así que ya está eligiendo el
        // primero: es el estado normal, no un caso raro.
        $this->assertNotNull($club['round'], json_encode($club));
        $this->assertSame('proposing', $club['round']['phase']);
        $this->assertSame(1, $club['round']['ballot']);
        $this->assertNull($club['round']['winning_proposal_id']);
        $this->assertSame([], $club['round']['proposals']);
    }

    #[Test]
    public function reading_the_club_twice_does_not_open_a_second_round(): void
    {
        $clubId = $this->club($this->amiga);

        // Cinco lecturas, que es lo que hacen dos pestañas abiertas.
        for ($i = 0; $i < 5; $i++) {
            $this->verClub($clubId);
        }

        $this->assertSame(1, $this->rondasEnLaBase($clubId));
    }

    #[Test]
    public function a_club_with_an_active_item_has_no_round(): void
    {
        $clubId = $this->club($this->amiga);

        $this->router()->dispatch('set_club_pick', [
            'clubId'     => $clubId,
            'entityType' => 'movie',
            'entityId'   => 'tt0111161',
        ]);

        $club = $this->verClub($clubId);

        // Ítem activo y ronda son estados excluyentes: mandar los dos invitaría
        // a la pantalla a pintar un voto sobre un club que ya está leyendo algo.
        $this->assertNotNull($club['pick']);
        $this->assertNull($club['round']);
    }

    #[Test]
    public function finishing_the_item_opens_the_next_round(): void
    {
        $clubId = $this->club($this->amiga);

        // La ronda que abre el club vacío se cierra al elegir a mano: es la vía
        // de escape del dueño, que este plan NO retira.
        $this->router()->dispatch('set_club_pick', [
            'clubId'     => $clubId,
            'entityType' => 'movie',
            'entityId'   => 'tt0111161',
        ]);
        $this->router()->dispatch('finish_club_pick', ['clubId' => $clubId]);

        $club = $this->verClub($clubId);

        $this->assertNull($club['pick']);
        $this->assertNotNull($club['round']);
        $this->assertSame('proposing', $club['round']['phase']);
        $this->assertSame([], $club['round']['proposals']);
    }

    // ------------------------------------------------------------------
    // M2 — proponer, con la rotación
    // ------------------------------------------------------------------

    #[Test]
    public function any_member_can_propose_an_item(): void
    {
        $clubId = $this->club($this->amiga);

        // La propone la NO dueña: es el sentido del plan entero.
        $this->comoUsuario($this->amiga);
        $r = $this->proponer($clubId, 'tt0111161', 'Cadena perpetua');

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertNotNull($r['data']['proposalId']);

        $club = $this->verClub($clubId);
        $this->assertCount(1, $club['round']['proposals']);
        $this->assertSame('Cadena perpetua', $club['round']['proposals'][0]['entity_title']);
        $this->assertSame($this->amiga, $club['round']['proposals'][0]['user_id']);

        // Y su propio `canPropose` se apaga: una por persona.
        $this->assertFalse($club['round']['canPropose']);
        $this->assertSame('already_proposed', $club['round']['reasonBlocked']);
    }

    #[Test]
    public function proposing_twice_is_a_400(): void
    {
        $clubId = $this->club($this->amiga);
        $this->proponer($clubId, 'tt0111161');

        $r = $this->proponer($clubId, 'tt0068646');

        $this->assertSame('error', $r['status']);
        $this->assertSame(400, $r['http_code']);
        $this->assertSame(1, $this->propuestasEnLaBase($clubId));
    }

    #[Test]
    public function a_non_member_cannot_propose(): void
    {
        $clubId = $this->club($this->amiga);

        $this->comoUsuario($this->tercero);
        $r = $this->proponer($clubId, 'tt0111161');

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);
    }

    #[Test]
    public function series_is_not_a_valid_proposal_type(): void
    {
        $clubId = $this->club($this->amiga);

        // El ENUM tiene cinco valores y `series` no es uno: una serie se guarda
        // con AddMovieUseCase y viaja como `movie`.
        $r = $this->router()->dispatch('propose_club_item', [
            'clubId' => $clubId, 'entityType' => 'series', 'entityId' => 'tt0903747',
        ]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(400, $r['http_code']);
    }

    #[Test]
    public function the_previous_winner_must_rotate_in_a_club_of_three(): void
    {
        $clubId = $this->club($this->amiga, $this->tercero);
        $this->cerrarRondaCon($clubId, $this->yo);

        // Yo gané la ronda anterior: me toca rotar y recibo 403.
        $this->comoUsuario($this->yo);
        $r = $this->proponer($clubId, 'tt0111161');
        $this->assertSame('error', $r['status'], json_encode($r));
        $this->assertSame(403, $r['http_code']);
        $this->assertSame('rotation', $this->verClub($clubId)['round']['reasonBlocked']);

        // Los otros dos sí pueden.
        foreach ([$this->amiga, $this->tercero] as $miembro) {
            $this->comoUsuario($miembro);
            $this->assertTrue($this->verClub($clubId)['round']['canPropose']);
            $this->assertSame('success', $this->proponer($clubId, 'tt' . $miembro . '000000')['status']);
        }
    }

    #[Test]
    public function a_club_of_two_never_rotates(): void
    {
        // La verificación nº 2 del plan: si la excepción de la rotación no
        // estuviera, este club se moriría en su segunda ronda.
        $clubId = $this->club($this->amiga);
        $this->cerrarRondaCon($clubId, $this->yo);

        foreach ([$this->yo, $this->amiga] as $miembro) {
            $this->comoUsuario($miembro);
            $club = $this->verClub($clubId);

            $this->assertTrue($club['round']['canPropose'], "El miembro {$miembro} no puede proponer");
            $this->assertNull($club['round']['reasonBlocked']);
            $this->assertSame('success', $this->proponer($clubId, 'tt' . $miembro . '111111')['status']);
        }
    }

    #[Test]
    public function leaving_the_club_takes_the_open_proposal_with_it(): void
    {
        $clubId = $this->club($this->amiga);

        $this->comoUsuario($this->amiga);
        $this->proponer($clubId, 'tt0111161');
        $this->assertSame(1, $this->propuestasEnLaBase($clubId));

        $this->router()->dispatch('leave_club', ['clubId' => $clubId]);

        // `club_member` no es clave ajena de `club_proposal`: sin borrarla a
        // mano, «han propuesto todos» compararía miembros actuales contra
        // propuestas de gente que ya no está y la fase no cerraría nunca.
        $this->assertSame(0, $this->propuestasEnLaBase($clubId));
    }

    // ------------------------------------------------------------------
    // M3 — votar, cerrar y el desempate
    // ------------------------------------------------------------------

    #[Test]
    public function when_everyone_has_proposed_the_vote_opens_by_itself(): void
    {
        $clubId = $this->club($this->amiga, $this->tercero);

        foreach ([$this->yo, $this->amiga, $this->tercero] as $miembro) {
            $this->comoUsuario($miembro);
            $this->proponer($clubId, 'tt' . $miembro . '000000');
        }

        $club = $this->verClub($clubId);
        $this->assertSame('voting', $club['round']['phase'], json_encode($club['round']));
        $this->assertCount(3, $club['round']['proposals']);
        $this->assertSame(3, $club['round']['pendingVoters']);
        $this->assertNull($club['round']['myVote']);
    }

    #[Test]
    public function when_everyone_has_voted_the_round_closes_and_the_winner_becomes_the_item(): void
    {
        $clubId = $this->abrirVotacionDeTres();
        $ganadora = $this->propuestaDe($clubId, $this->amiga);

        // Los tres votan lo mismo: no hay empate que resolver.
        foreach ([$this->yo, $this->amiga, $this->tercero] as $miembro) {
            $this->comoUsuario($miembro);
            $this->assertSame('success', $this->votar($clubId, $ganadora)['status']);
        }

        $club = $this->verClub($clubId);

        $this->assertNull($club['round'], 'La ronda tenía que haberse cerrado');
        $this->assertNotNull($club['pick'], json_encode($club));
        $this->assertSame('tt' . $this->amiga . '000000', $club['pick']['entity_id']);
    }

    #[Test]
    public function my_vote_comes_back_and_can_be_changed(): void
    {
        $clubId = $this->abrirVotacionDeTres();
        $unaOtra = $this->propuestaDe($clubId, $this->tercero);
        $laMia   = $this->propuestaDe($clubId, $this->yo);

        $this->comoUsuario($this->yo);
        $this->votar($clubId, $unaOtra);
        $this->assertSame($unaOtra, $this->verClub($clubId)['round']['myVote']);

        // Cambiar de idea es la misma acción otra vez, y NO añade un voto.
        $this->votar($clubId, $laMia);
        $this->assertSame($laMia, $this->verClub($clubId)['round']['myVote']);
        $this->assertSame(1, $this->votosEnLaBase($clubId));
    }

    #[Test]
    public function nobody_can_see_who_voted_for_what(): void
    {
        // La verificación nº 3 del plan: se mira el JSON, no la pantalla.
        $clubId = $this->abrirVotacionDeTres();

        $this->comoUsuario($this->amiga);
        $this->votar($clubId, $this->propuestaDe($clubId, $this->tercero));

        $this->comoUsuario($this->yo);
        $round = $this->verClub($clubId)['round'];
        $json  = json_encode($round);

        // Hay recuentos y voto propio...
        $this->assertSame(1, array_sum(array_column($round['proposals'], 'votes')));
        $this->assertNull($round['myVote']);

        // ...y ni un solo par usuario → propuesta ajeno. Lo que está en el DOM
        // está enseñado, por mucho que la pantalla no lo pinte.
        $this->assertStringNotContainsString('voter', $json);
        $this->assertStringNotContainsString('voted_by', $json);
        foreach ($round['proposals'] as $propuesta) {
            $this->assertArrayNotHasKey('voters', $propuesta);
        }
    }

    #[Test]
    public function a_tie_goes_to_a_second_ballot_with_the_losers_eliminated(): void
    {
        $clubId = $this->abrirVotacionDeTres();
        $deYo      = $this->propuestaDe($clubId, $this->yo);
        $deAmiga   = $this->propuestaDe($clubId, $this->amiga);
        $deTercero = $this->propuestaDe($clubId, $this->tercero);

        // 1-1-1: empate a tres. No hay «la menos votada» que eliminar.
        $this->comoUsuario($this->yo);      $this->votar($clubId, $deYo);
        $this->comoUsuario($this->amiga);   $this->votar($clubId, $deAmiga);
        $this->comoUsuario($this->tercero); $this->votar($clubId, $deTercero);

        $round = $this->verClub($clubId)['round'];
        $this->assertNotNull($round, 'La ronda no podía cerrarse con un empate a tres');
        $this->assertSame('voting', $round['phase']);
        $this->assertSame(2, $round['ballot']);

        // Empataban las tres, así que ninguna queda eliminada, y el recuento
        // vuelve a cero: los votos del `ballot` 1 se conservan pero no cuentan.
        $this->assertSame([false, false, false], array_column($round['proposals'], 'eliminated'));
        $this->assertSame(0, array_sum(array_column($round['proposals'], 'votes')));
        $this->assertNull($round['myVote']);
    }

    #[Test]
    public function a_two_one_vote_eliminates_the_loser_in_the_tie_break(): void
    {
        $clubId = $this->abrirVotacionDeTres();
        $deYo      = $this->propuestaDe($clubId, $this->yo);
        $deAmiga   = $this->propuestaDe($clubId, $this->amiga);
        $deTercero = $this->propuestaDe($clubId, $this->tercero);

        // 1-1-0: empatan dos y la tercera queda fuera.
        $this->comoUsuario($this->yo);      $this->votar($clubId, $deYo);
        $this->comoUsuario($this->amiga);   $this->votar($clubId, $deAmiga);
        $this->comoUsuario($this->tercero); $this->votar($clubId, $deAmiga);

        // 2-1: gana la de la amiga sin desempate.
        $club = $this->verClub($clubId);
        $this->assertNull($club['round']);
        $this->assertSame('tt' . $this->amiga . '000000', $club['pick']['entity_id']);
        $this->assertNotSame($deYo, $deTercero);
    }

    #[Test]
    public function an_eliminated_proposal_cannot_be_voted_in_the_tie_break(): void
    {
        $clubId = $this->empatarHastaElSegundoRecuento();

        // En el club de dos empatan las dos, así que para tener una eliminada
        // hace falta el de tres: se comprueba con una propuesta de OTRA ronda.
        $r = $this->votar($clubId, 999999);

        $this->assertSame('error', $r['status']);
        $this->assertSame(404, $r['http_code']);
    }

    #[Test]
    public function the_drawn_winner_does_not_change_when_the_club_is_read_again(): void
    {
        // La verificación nº 1 del plan, y el fallo que el esquema existe para
        // evitar: un sorteo recalculado en cada lectura convertiría `get_club`
        // en una máquina tragaperras.
        $clubId = $this->empatarHastaElSegundoRecuento();

        // Segundo recuento, y vuelven a empatar: entra el sorteo.
        $this->comoUsuario($this->yo);    $this->votar($clubId, $this->propuestaDe($clubId, $this->yo));
        $this->comoUsuario($this->amiga); $this->votar($clubId, $this->propuestaDe($clubId, $this->amiga));

        $this->comoUsuario($this->yo);
        $primero = $this->verClub($clubId);
        $this->assertNull($primero['round'], 'El sorteo tenía que haber cerrado la ronda');
        $this->assertNotNull($primero['pick']);

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(
                $primero['pick']['entity_id'],
                $this->verClub($clubId)['pick']['entity_id'],
                'El ganador sorteado cambió al releer el club'
            );
        }
    }

    #[Test]
    public function the_owner_can_open_the_vote_with_what_there_is(): void
    {
        $clubId = $this->club($this->amiga, $this->tercero);

        // Solo propone uno: la fase no cierra sola.
        $this->comoUsuario($this->amiga);
        $this->proponer($clubId, 'tt0111161');
        $this->assertSame('proposing', $this->verClub($clubId)['round']['phase']);

        $this->comoUsuario($this->yo);
        $r = $this->router()->dispatch('open_club_vote', ['clubId' => $clubId]);

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertSame('voting', $this->verClub($clubId)['round']['phase']);
    }

    #[Test]
    public function the_owner_cannot_open_a_vote_with_nothing_to_vote_on(): void
    {
        $clubId = $this->club($this->amiga);
        $this->verClub($clubId);

        $r = $this->router()->dispatch('open_club_vote', ['clubId' => $clubId]);

        // Abrir un voto vacío dejaría la ronda clavada un escalón más allá.
        $this->assertSame('error', $r['status']);
        $this->assertSame(409, $r['http_code']);
    }

    #[Test]
    public function only_the_owner_can_use_the_valves(): void
    {
        $clubId = $this->club($this->amiga);
        $this->comoUsuario($this->amiga);
        $this->proponer($clubId, 'tt0111161');

        foreach (['open_club_vote', 'close_club_vote'] as $accion) {
            $r = $this->router()->dispatch($accion, ['clubId' => $clubId]);
            $this->assertSame(403, $r['http_code'], $accion . ' no dio 403');
        }
    }

    #[Test]
    public function the_owner_can_close_the_vote_with_the_votes_there_are(): void
    {
        $clubId = $this->abrirVotacionDeTres();

        // Vota uno solo de tres: no cerraría nunca sola.
        $this->comoUsuario($this->tercero);
        $this->votar($clubId, $this->propuestaDe($clubId, $this->tercero));

        $this->comoUsuario($this->yo);
        $r = $this->router()->dispatch('close_club_vote', ['clubId' => $clubId]);

        $this->assertSame('success', $r['status'], json_encode($r));
        $this->assertSame('closed', $r['data']['phase']);
        $this->assertNotNull($r['data']['pickId']);
        $this->assertSame('tt' . $this->tercero . '000000', $this->verClub($clubId)['pick']['entity_id']);
    }

    #[Test]
    public function the_owner_cannot_close_a_vote_that_nobody_voted(): void
    {
        $clubId = $this->abrirVotacionDeTres();

        $r = $this->router()->dispatch('close_club_vote', ['clubId' => $clubId]);

        // Sin un solo voto no hay ganador que escribir.
        $this->assertSame('error', $r['status']);
        $this->assertSame(400, $r['http_code']);
    }

    #[Test]
    public function voting_while_the_round_is_still_taking_proposals_is_a_409(): void
    {
        $clubId = $this->club($this->amiga, $this->tercero);
        $this->comoUsuario($this->amiga);
        $this->proponer($clubId, 'tt0111161');

        $r = $this->votar($clubId, $this->propuestaDe($clubId, $this->amiga));

        $this->assertSame('error', $r['status']);
        $this->assertSame(409, $r['http_code']);
    }

    #[Test]
    public function leaving_the_club_takes_the_vote_with_it(): void
    {
        $clubId = $this->abrirVotacionDeTres();

        $this->comoUsuario($this->amiga);
        $this->votar($clubId, $this->propuestaDe($clubId, $this->amiga));
        $this->assertSame(1, $this->votosEnLaBase($clubId));

        $this->router()->dispatch('leave_club', ['clubId' => $clubId]);

        // Sin esto, «han votado todos» compara miembros actuales contra votos
        // de gente que ya no está.
        $this->assertSame(0, $this->votosEnLaBase($clubId));
    }

    #[Test]
    public function the_club_of_two_can_run_two_rounds_in_a_row(): void
    {
        // La verificación nº 2 del plan, entera: si la excepción de la rotación
        // no estuviera, la segunda ronda no tendría dos proponentes.
        $clubId = $this->club($this->amiga);

        for ($ronda = 1; $ronda <= 2; $ronda++) {
            foreach ([$this->yo, $this->amiga] as $miembro) {
                $this->comoUsuario($miembro);
                $this->assertTrue(
                    $this->verClub($clubId)['round']['canPropose'],
                    "Ronda {$ronda}: el miembro {$miembro} no puede proponer"
                );
                $this->proponer($clubId, 'tt' . $ronda . $miembro . '00000');
            }

            // La lectura que abre el voto — la pantalla se refresca al proponer.
            $this->comoUsuario($this->yo);
            $this->assertSame('voting', $this->verClub($clubId)['round']['phase'], "Ronda {$ronda}");

            // Los dos votan lo mismo: sin empate, la ronda cierra sola.
            $ganadora = $this->propuestaDe($clubId, $this->yo);
            foreach ([$this->yo, $this->amiga] as $miembro) {
                $this->comoUsuario($miembro);
                $this->votar($clubId, $ganadora);
            }

            $this->comoUsuario($this->yo);
            $club = $this->verClub($clubId);
            $this->assertNotNull($club['pick'], "Ronda {$ronda}: no se eligió ítem");

            // Se termina para que se abra la siguiente ronda.
            $this->router()->dispatch('finish_club_pick', ['clubId' => $clubId]);
        }
    }

    // ------------------------------------------------------------------
    // Escenarios compartidos por los tests del M3
    // ------------------------------------------------------------------

    /** Club de tres con las tres propuestas puestas y el voto ya abierto. */
    private function abrirVotacionDeTres(): int
    {
        $clubId = $this->club($this->amiga, $this->tercero);

        foreach ([$this->yo, $this->amiga, $this->tercero] as $miembro) {
            $this->comoUsuario($miembro);
            $this->proponer($clubId, 'tt' . $miembro . '000000');
        }

        $this->comoUsuario($this->yo);
        $this->verClub($clubId);   // abre el voto

        return $clubId;
    }

    /** Club de dos empatado a uno, ya en el segundo recuento. */
    private function empatarHastaElSegundoRecuento(): int
    {
        $clubId = $this->club($this->amiga);

        foreach ([$this->yo, $this->amiga] as $miembro) {
            $this->comoUsuario($miembro);
            $this->proponer($clubId, 'tt' . $miembro . '999999');
        }

        $this->comoUsuario($this->yo);
        $this->verClub($clubId);

        $this->comoUsuario($this->yo);    $this->votar($clubId, $this->propuestaDe($clubId, $this->yo));
        $this->comoUsuario($this->amiga); $this->votar($clubId, $this->propuestaDe($clubId, $this->amiga));

        $this->comoUsuario($this->yo);
        $this->assertSame(2, $this->verClub($clubId)['round']['ballot']);

        return $clubId;
    }

    #[Test]
    public function a_non_member_still_gets_a_403_and_opens_nothing(): void
    {
        $clubId = $this->club();

        $this->comoUsuario($this->amiga);
        $r = $this->router()->dispatch('get_club', ['clubId' => $clubId]);

        $this->assertSame('error', $r['status']);
        $this->assertSame(403, $r['http_code']);

        // La ronda se abre DESPUÉS de comprobar la pertenencia: un extraño
        // mirando un club ajeno no puede provocarle escrituras.
        $this->assertSame(0, $this->rondasEnLaBase($clubId));
    }
}
