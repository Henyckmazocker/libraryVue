<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

use App\Domain\Model\JournalEntry;
use DateTimeImmutable;

final readonly class GetJournalQuery
{
    public function __construct(
        public int     $userId,
        public int     $limit  = 30,
        public int     $offset = 0,
        public ?string $media  = null,
        public ?string $from   = null,
        public ?string $to     = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $media = $data['media'] ?? null;

        [$from, $to] = self::rango($data['from'] ?? null, $data['to'] ?? null);

        return new self(
            userId: $userId,
            // El tope se aplica en el servidor, como en `GetFeedQuery`: un
            // `limit` que llega del cliente no se cree.
            //
            // **El mes del calendario pide 100, no 200.** El plan del
            // calendario dibujaba la petición del mes con `"limit": 200`, pero
            // subir el tope —ni siquiera solo cuando viene rango— ampliaría lo
            // que un cliente puede sacar de un tirón en la acción que sirve
            // TODO el diario, y a cambio de nada: con `from`/`to` puestos,
            // `total` y `hasMore` cuentan ya solo el rango, así que un mes con
            // más de 100 entradas se pagina con `offset` como cualquier otra
            // página. Se queda en 100 y el 200 del cliente se topa aquí.
            limit:  min(100, max(1, (int) ($data['limit'] ?? 30))),
            offset: max(0, (int) ($data['offset'] ?? 0)),
            // Un medio que no existe se ignora en vez de reventar: el filtro es
            // una comodidad del listado, no una condición de la petición.
            media:  is_string($media) && in_array($media, JournalEntry::MEDIA, true) ? $media : null,
            from:   $from,
            to:     $to
        );
    }

    /**
     * El rango de fechas del listado, validado con el mismo criterio que el
     * `media`: lo que no se entiende se ignora y el listado sale entero, que es
     * la respuesta útil, en vez de un error sobre un filtro accesorio.
     *
     * Dos reglas: cada extremo tiene que ser un `YYYY-MM-DD` de verdad
     * —`entry_date` es una columna `DATE` y se compara por la cadena—, y si los
     * dos están puestos, `from` no puede ser posterior a `to`. Un rango
     * invertido se descarta **entero**, no a medias: quedarse con uno de los dos
     * devolvería medio año de entradas a quien pidió un mes.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function rango(mixed $from, mixed $to): array
    {
        $desde = self::fecha($from);
        $hasta = self::fecha($to);

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            return [null, null];
        }

        return [$desde, $hasta];
    }

    /** La cadena si es una fecha `YYYY-MM-DD` existente, y null si no. */
    private static function fecha(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', trim($valor));

        // `createFromFormat` acepta un 31 de febrero y lo desplaza al 3 de
        // marzo, así que no basta con que devuelva un objeto: tiene que volver
        // a formatearse igual que entró.
        return ($fecha !== false && $fecha->format('Y-m-d') === trim($valor))
            ? $fecha->format('Y-m-d')
            : null;
    }
}
