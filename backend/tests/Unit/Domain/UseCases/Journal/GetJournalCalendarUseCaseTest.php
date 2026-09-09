<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Journal;

use App\Domain\DTO\Queries\GetJournalCalendarQuery;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Domain\UseCases\Journal\GetJournalCalendarUseCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * El agregado del calendario. Lo que se prueba aquí es la forma de la
 * respuesta, que es el contrato con el heatmap: que un día sin entradas **no
 * aparezca** (un año vacío son cero claves, no 365 ceros), que `total` sea el
 * del año pedido y que `years` salga ordenado de más reciente a más antiguo.
 * El `GROUP BY` de verdad se prueba por integración, contra el esquema real.
 */
class GetJournalCalendarUseCaseTest extends TestCase
{
    private const USUARIO = 7;

    private GetJournalCalendarUseCase $useCase;

    private JournalRepositoryInterface $journalRepo;

    protected function setUp(): void
    {
        $this->journalRepo = $this->createMock(JournalRepositoryInterface::class);
        $this->useCase     = new GetJournalCalendarUseCase($this->journalRepo, new NullLogger());
    }

    #[Test]
    public function only_the_days_with_entries_come_back(): void
    {
        $this->journalRepo->method('countByDay')->willReturn([
            '2026-08-27' => ['count' => 1, 'media' => ['book']],
            '2026-09-04' => ['count' => 3, 'media' => ['book', 'movie']],
        ]);
        $this->journalRepo->method('yearsWithEntries')->willReturn([2026]);

        $resultado = $this->useCase->execute(new GetJournalCalendarQuery(self::USUARIO, 2026));

        $this->assertSame(['2026-08-27', '2026-09-04'], array_keys($resultado['days']));
        $this->assertArrayNotHasKey('2026-08-28', $resultado['days'], 'Un día sin entradas no ocupa una clave');
        $this->assertSame(['count' => 3, 'media' => ['book', 'movie']], $resultado['days']['2026-09-04']);
    }

    #[Test]
    public function an_empty_year_has_no_days_at_all(): void
    {
        $this->journalRepo->method('countByDay')->willReturn([]);
        $this->journalRepo->method('yearsWithEntries')->willReturn([2026]);

        $resultado = $this->useCase->execute(new GetJournalCalendarQuery(self::USUARIO, 2019));

        $this->assertSame([], $resultado['days']);
        $this->assertSame(0, $resultado['total']);
        // El navegador de años sigue estando, aunque el año mirado esté vacío.
        $this->assertSame([2026], $resultado['years']);
    }

    #[Test]
    public function the_total_is_the_sum_of_the_year_not_of_the_whole_journal(): void
    {
        $this->journalRepo->method('countByDay')->willReturn([
            '2026-01-02' => ['count' => 2, 'media' => ['game']],
            '2026-03-15' => ['count' => 5, 'media' => ['book', 'series']],
        ]);
        $this->journalRepo->method('yearsWithEntries')->willReturn([2026, 2025]);

        $resultado = $this->useCase->execute(new GetJournalCalendarQuery(self::USUARIO, 2026));

        $this->assertSame(7, $resultado['total']);
    }

    #[Test]
    public function the_years_come_from_newest_to_oldest(): void
    {
        $this->journalRepo->method('countByDay')->willReturn([]);
        $this->journalRepo->method('yearsWithEntries')->willReturn([2026, 2025, 2021]);

        $resultado = $this->useCase->execute(new GetJournalCalendarQuery(self::USUARIO, 2026));

        $this->assertSame([2026, 2025, 2021], $resultado['years']);
        $this->assertSame($resultado['years'], array_values(array_unique($resultado['years'])));
    }

    #[Test]
    public function the_year_is_asked_to_the_repository_as_a_date_range(): void
    {
        // El rango es lo que hace que la consulta caiga dentro de
        // `idx_journal_user_date`; un `YEAR(entry_date) = ?` no lo haría.
        $this->journalRepo->expects($this->once())
            ->method('countByDay')
            ->with(self::USUARIO, '2026-01-01', '2026-12-31', null)
            ->willReturn([]);
        $this->journalRepo->method('yearsWithEntries')->willReturn([]);

        $this->useCase->execute(new GetJournalCalendarQuery(self::USUARIO, 2026));
    }

    #[Test]
    public function the_media_filter_reaches_the_aggregate(): void
    {
        // El filtro por medio manda también sobre el calendario (M4 del plan).
        $this->journalRepo->expects($this->once())
            ->method('countByDay')
            ->with(self::USUARIO, '2026-01-01', '2026-12-31', 'book')
            ->willReturn(['2026-09-04' => ['count' => 2, 'media' => ['book']]]);
        $this->journalRepo->method('yearsWithEntries')->willReturn([2026]);

        $resultado = $this->useCase->execute(new GetJournalCalendarQuery(self::USUARIO, 2026, 'book'));

        // El `total` sale del agregado ya filtrado: no hay dos cuentas.
        $this->assertSame(2, $resultado['total']);
    }

    #[Test]
    public function the_years_navigator_is_never_filtered_by_media(): void
    {
        // La excepción deliberada del hito: filtrar `years` haría desaparecer
        // años enteros del selector y dejaría al usuario sin forma de volver a
        // ellos. `yearsWithEntries` no recibe el medio ni puede recibirlo.
        $this->journalRepo->method('countByDay')->willReturn([]);
        $this->journalRepo->expects($this->once())
            ->method('yearsWithEntries')
            ->with(self::USUARIO)
            ->willReturn([2026, 2025]);

        $resultado = $this->useCase->execute(new GetJournalCalendarQuery(self::USUARIO, 2026, 'album'));

        $this->assertSame([2026, 2025], $resultado['years']);
    }

    #[Test]
    public function another_query_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->useCase->execute(new \stdClass());
    }
}
