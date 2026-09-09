<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Journal;

use App\Domain\DTO\Queries\GetJournalCalendarQuery;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * El agregado por día de un año, para el calendario.
 *
 * Es una acción aparte de `get_journal` porque son dos preguntas distintas: el
 * año necesita saber **cuántas** cosas hubo cada día, y el mes **cuáles**.
 * Traerse las entradas del año entero para contarlas en el navegador es justo
 * lo que esto evita.
 *
 * El `media` de la consulta filtra `days` y su `total` —las píldoras de
 * `/journal` mandan también sobre el calendario—, pero **no `years`**.
 */
class GetJournalCalendarUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetJournalCalendar'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetJournalCalendarQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetJournalCalendarQuery');
        }

        [$desde, $hasta] = $query->yearRange();

        // `days` viene ya indexado por `YYYY-MM-DD` y **solo con los días que
        // tienen algo**: un año vacío son cero claves, no 365 ceros. En JSON,
        // un año sin entradas sale como `[]` y no como `{}` —es un array PHP
        // vacío—, que es indistinguible para quien lo recorre por claves.
        $dias = $this->journal->countByDay($query->userId, $desde, $hasta, $query->media);

        return [
            'days'  => $dias,
            // El total del AÑO pedido, no el del diario entero: es el número
            // que acompaña al mapa del año que se está mirando.
            'total' => array_sum(array_column($dias, 'count')),
            // Los años con entradas, para el navegador. Sale de una consulta
            // aparte a propósito: `days` solo mira un año y no puede saber
            // cuáles otros existen.
            //
            // **Y NO se filtra por medio**, aunque `days` sí: filtrarlo haría
            // desaparecer años enteros del selector al pulsar «Álbum» y
            // dejaría al usuario sin forma de volver a ellos. El navegador de
            // años ofrece siempre todos los años con alguna entrada.
            'years' => $this->journal->yearsWithEntries($query->userId),
        ];
    }
}
