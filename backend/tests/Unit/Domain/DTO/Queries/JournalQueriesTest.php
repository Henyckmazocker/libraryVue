<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Queries;

use App\Domain\DTO\Queries\GetJournalCalendarQuery;
use App\Domain\DTO\Queries\GetJournalQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Las dos consultas del calendario. El criterio que se fija aquí es el mismo
 * que el del `media` de `GetJournalQuery`: **lo que no se entiende se ignora**,
 * no revienta la petición, porque el rango es un filtro accesorio del listado.
 */
class JournalQueriesTest extends TestCase
{
    private const USUARIO = 4;

    // ═══════════════════════════════════════
    // GetJournalQuery — el rango from/to
    // ═══════════════════════════════════════

    #[Test]
    public function a_valid_range_survives(): void
    {
        $q = GetJournalQuery::fromArray(['from' => '2026-09-01', 'to' => '2026-09-30'], self::USUARIO);

        $this->assertSame('2026-09-01', $q->from);
        $this->assertSame('2026-09-30', $q->to);
    }

    #[Test]
    public function without_a_range_both_ends_are_null(): void
    {
        $q = GetJournalQuery::fromArray([], self::USUARIO);

        $this->assertNull($q->from);
        $this->assertNull($q->to);
    }

    #[Test]
    public function each_end_works_on_its_own(): void
    {
        $desde = GetJournalQuery::fromArray(['from' => '2026-09-01'], self::USUARIO);
        $hasta = GetJournalQuery::fromArray(['to' => '2026-09-30'], self::USUARIO);

        $this->assertSame('2026-09-01', $desde->from);
        $this->assertNull($desde->to);
        $this->assertNull($hasta->from);
        $this->assertSame('2026-09-30', $hasta->to);
    }

    #[Test]
    public function what_is_not_a_date_is_ignored(): void
    {
        foreach ([['from' => 'ayer'], ['from' => '01/09/2026'], ['from' => 20260901], ['from' => ['x']]] as $datos) {
            $this->assertNull(GetJournalQuery::fromArray($datos, self::USUARIO)->from);
        }
    }

    #[Test]
    public function a_date_that_does_not_exist_is_not_a_date(): void
    {
        // `createFromFormat` acepta el 31 de febrero y lo desplaza al 3 de
        // marzo: sin la comprobación de ida y vuelta, esto pasaría por válido.
        $this->assertNull(GetJournalQuery::fromArray(['from' => '2026-02-31'], self::USUARIO)->from);
        $this->assertNull(GetJournalQuery::fromArray(['from' => '2026-13-01'], self::USUARIO)->from);
    }

    #[Test]
    public function an_inverted_range_is_dropped_whole(): void
    {
        $q = GetJournalQuery::fromArray(['from' => '2026-09-30', 'to' => '2026-09-01'], self::USUARIO);

        $this->assertNull($q->from, 'Quedarse con un extremo devolvería medio diario a quien pidió un mes');
        $this->assertNull($q->to);
    }

    #[Test]
    public function the_same_day_at_both_ends_is_a_valid_range(): void
    {
        $q = GetJournalQuery::fromArray(['from' => '2026-09-04', 'to' => '2026-09-04'], self::USUARIO);

        $this->assertSame('2026-09-04', $q->from);
        $this->assertSame('2026-09-04', $q->to);
    }

    #[Test]
    public function the_month_asking_for_200_still_gets_100(): void
    {
        // El tope del servidor no sube por traer rango: el mes se pagina con
        // `offset` si algún día hiciera falta.
        $q = GetJournalQuery::fromArray(
            ['from' => '2026-09-01', 'to' => '2026-09-30', 'limit' => 200],
            self::USUARIO
        );

        $this->assertSame(100, $q->limit);
    }

    // ═══════════════════════════════════════
    // GetJournalCalendarQuery
    // ═══════════════════════════════════════

    #[Test]
    public function the_year_travels_and_becomes_a_range(): void
    {
        $q = GetJournalCalendarQuery::fromArray(['year' => 2025], self::USUARIO);

        $this->assertSame(2025, $q->year);
        $this->assertSame(self::USUARIO, $q->userId);
        $this->assertSame(['2025-01-01', '2025-12-31'], $q->yearRange());
    }

    #[Test]
    public function a_year_as_a_string_is_accepted(): void
    {
        $this->assertSame(2024, GetJournalCalendarQuery::fromArray(['year' => '2024'], self::USUARIO)->year);
    }

    #[Test]
    public function a_missing_or_impossible_year_falls_back_to_the_current_one(): void
    {
        $actual = (int) date('Y');

        $this->assertSame($actual, GetJournalCalendarQuery::fromArray([], self::USUARIO)->year);
        $this->assertSame($actual, GetJournalCalendarQuery::fromArray(['year' => 0], self::USUARIO)->year);
        $this->assertSame($actual, GetJournalCalendarQuery::fromArray(['year' => -5], self::USUARIO)->year);
        $this->assertSame($actual, GetJournalCalendarQuery::fromArray(['year' => 99999], self::USUARIO)->year);
        $this->assertSame($actual, GetJournalCalendarQuery::fromArray(['year' => 'este'], self::USUARIO)->year);
    }

    #[Test]
    public function the_media_filter_travels_to_the_calendar_too(): void
    {
        // Las píldoras de `/journal` mandan también sobre el calendario (M4).
        $q = GetJournalCalendarQuery::fromArray(['year' => 2026, 'media' => 'book'], self::USUARIO);

        $this->assertSame('book', $q->media);
    }

    #[Test]
    public function a_media_that_does_not_exist_is_ignored_in_the_calendar(): void
    {
        $this->assertNull(GetJournalCalendarQuery::fromArray(['year' => 2026], self::USUARIO)->media);
        $this->assertNull(GetJournalCalendarQuery::fromArray(['year' => 2026, 'media' => 'comic'], self::USUARIO)->media);
        $this->assertNull(GetJournalCalendarQuery::fromArray(['year' => 2026, 'media' => ''], self::USUARIO)->media);
        $this->assertNull(GetJournalCalendarQuery::fromArray(['year' => 2026, 'media' => 7], self::USUARIO)->media);
    }
}
