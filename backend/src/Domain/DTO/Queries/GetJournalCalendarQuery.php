<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

use App\Domain\Model\JournalEntry;

final readonly class GetJournalCalendarQuery
{
    public function __construct(
        public int $userId,
        public int $year,
        public ?string $media = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $year  = (int) ($data['year'] ?? 0);
        $media = $data['media'] ?? null;

        return new self(
            userId: $userId,
            // Un año que no lo es se ignora y se cae al actual, como
            // `GetJournalQuery` hace con un medio inexistente: el navegador de
            // años del calendario pide siempre uno de los que devuelve `years`,
            // así que un valor imposible es un cliente roto, no una petición
            // que haya que contestar con un error. El rango se acota a lo que
            // cabe en una columna DATE de MySQL (1000-9999) para que
            // `yearRange()` no componga una fecha que el motor rechace.
            year: ($year >= 1000 && $year <= 9999) ? $year : (int) date('Y'),
            // El mismo criterio que `GetJournalQuery`: un medio que no existe
            // se ignora y el calendario sale entero, en vez de contestar con un
            // error por un filtro accesorio. **El filtro manda también sobre el
            // calendario** (M4 del plan): las píldoras viven justo encima del
            // heatmap y prometen que lo filtran.
            media: is_string($media) && in_array($media, JournalEntry::MEDIA, true) ? $media : null
        );
    }

    /**
     * El año como par de fechas `YYYY-MM-DD`, que es como se compara una
     * columna `DATE`: con `>= :desde AND <= :hasta` la consulta cae dentro de
     * `idx_journal_user_date`, mientras que un `YEAR(entry_date) = :y` obliga a
     * recorrer todas las entradas del usuario.
     *
     * @return array{0: string, 1: string}
     */
    public function yearRange(): array
    {
        return [
            sprintf('%04d-01-01', $this->year),
            sprintf('%04d-12-31', $this->year),
        ];
    }
}
