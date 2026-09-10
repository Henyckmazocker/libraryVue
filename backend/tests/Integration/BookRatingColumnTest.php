<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\JWTService;
use App\Router\ActionRouter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Los otros dos caminos que valoran un libro: **el diario y el alta**.
 *
 * El M4 de este plan arregló `update_book_rating`, que escribía `work_rating`
 * cuando la columna que la ficha enseña es `edition_rating`
 * —`UserBookEdition::toArray()` la publica como `user_rating`, `personal_rating`
 * y `rating` (`UserBookEdition.php:239-243`), y `LibraryMediaItem.vue:188` lee
 * `item.user_rating`—. Quedaron dos llamantes con la misma premisa falsa:
 *
 *   1. `JournalRatingWriter::writeBook()`, que propaga al ítem la valoración de
 *      una entrada del diario («la última entrada manda»);
 *   2. `AddBookUseCase`, cuando el alta trae `user_rating`.
 *
 * Los dos pasaban el rating en el **tercer** argumento posicional de
 * `updateRating(int, int, ?float $workRating, ?float $editionRating = null)`, o
 * sea `work_rating`, dejando el cuarto en su default `null`. Y como el UPDATE
 * del repositorio escribe **las dos columnas de una vez**
 * (`MySqlUserBookEditionRepository.php:318-332`), no solo guardaban en la
 * columna que nadie lee: **borraban la que sí**.
 *
 * Va por integración y no por unitario por lo mismo que el M2: el defecto está
 * en el significado de una posición de argumento contra el esquema real, y un
 * mock del repositorio acepta encantado los dos órdenes. `JournalRatingWriter`
 * además **se traga sus errores** a propósito, así que aquí la única prueba
 * posible es mirar la columna.
 */
class BookRatingColumnTest extends IntegrationTestCase
{
    /** ISBN-13 con checksum válido: el ValueObject lo valida. */
    private const ISBN = '9780000000019';

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (google_id, email, name) VALUES (:g, :e, :n)'
        );
        $sufijo = bin2hex(random_bytes(4));
        $stmt->execute(['g' => 'g-' . $sufijo, 'e' => $sufijo . '@ejemplo.test', 'n' => 'Quien lee']);
        $this->userId = (int) $this->pdo()->lastInsertId();

        // Por JWT y no por sesión, como `RatingActionsTest` y `JournalActionsTest`:
        // el pipeline omite el CSRF y el test se concentra en la costura
        // payload → use case → columna.
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

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function despachar(string $accion, array $payload): array
    {
        return $this->router()->dispatch($accion, $payload);
    }

    /** Da de alta el libro en la biblioteca; con `$rating`, valorándolo de paso. */
    private function sembrarLibro(?float $rating = null): void
    {
        $libro = [
            'isbn'         => self::ISBN,
            'title'        => 'Un libro valorable',
            'author'       => 'Autora de prueba',
            'userStatuses' => ['owned'],
        ];

        if ($rating !== null) {
            // `AddBookCommand::fromArray()` lee la valoración del usuario de
            // `user_rating`, en snake_case.
            $libro['user_rating'] = $rating;
        }

        $alta = $this->despachar('add_book', ['book' => $libro]);

        $this->assertSame('success', $alta['status'], 'add_book tiene que guardar');
    }

    /** @return array{work_rating: ?float, edition_rating: ?float} */
    private function columnas(): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT work_rating, edition_rating FROM user_book_editions
              WHERE user_id = :u ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':u' => $this->userId]);
        $fila = $stmt->fetch();

        $this->assertNotFalse($fila, 'Tiene que existir la fila de user_book_editions');

        return [
            'work_rating'    => $fila['work_rating'] === null ? null : (float) $fila['work_rating'],
            'edition_rating' => $fila['edition_rating'] === null ? null : (float) $fila['edition_rating'],
        ];
    }

    // =========================================================================
    // 1. El diario
    // =========================================================================

    #[Test]
    public function rating_a_book_from_the_journal_lands_on_the_column_the_detail_shows(): void
    {
        $this->sembrarLibro();

        $entrada = $this->despachar('add_journal_entry', [
            'media'     => 'book',
            'entityId'  => self::ISBN,
            'entryDate' => '2026-09-09',
            'rating'    => 4.5,
        ]);
        $this->assertSame('success', $entrada['status'], $entrada['message'] ?? '');

        // `edition_rating`, no `work_rating`: es la que la ficha lee.
        $this->assertSame(4.5, $this->columnas()['edition_rating']);
    }

    #[Test]
    public function rating_a_book_from_the_journal_does_not_wipe_the_work_rating(): void
    {
        $this->sembrarLibro();

        // La valoración de la OBRA es otro concepto del modelo Work/Edition y no
        // la toca nadie desde aquí; el UPDATE escribe las dos columnas, así que
        // hay que releerla y reescribirla igual.
        $this->pdo()->prepare(
            'UPDATE user_book_editions SET work_rating = 2.5 WHERE user_id = :u'
        )->execute([':u' => $this->userId]);

        $this->despachar('add_journal_entry', [
            'media'     => 'book',
            'entityId'  => self::ISBN,
            'entryDate' => '2026-09-09',
            'rating'    => 4.5,
        ]);

        $columnas = $this->columnas();
        $this->assertSame(2.5, $columnas['work_rating'], 'el work_rating tenía que conservarse');
        $this->assertSame(4.5, $columnas['edition_rating']);
    }

    // =========================================================================
    // 2. El alta
    // =========================================================================

    #[Test]
    public function adding_a_book_with_a_rating_lands_on_the_column_the_detail_shows(): void
    {
        $this->sembrarLibro(rating: 4.0);

        $columnas = $this->columnas();
        $this->assertSame(4.0, $columnas['edition_rating']);
        // Un alta no tiene `work_rating` previo que conservar, y tampoco se lo
        // inventa: la valoración del alta es de la edición que el usuario mete.
        $this->assertNull($columnas['work_rating']);
    }
}
