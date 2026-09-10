<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * JournalEntry — una cosa consumida, un día.
 *
 * Las seis columnas de fecha de consumo que el esquema arrastraba
 * —`consumed_at`, `last_session_completed_at`, `completed_at`, `watched_at`—
 * se **retiraron** el 2026-09-10 con la migración
 * `20260910_120000_drop_consumption_dates.sql`: estaban vacías en todas las
 * filas y en todos los entornos, y nunca se conectaron a nada.
 *
 * Este es el sitio donde eso se guarda de verdad. Aquí van medio, entidad,
 * título, portada, fecha, valoración, origen y `source_id`, y sobre todo hay
 * **una fila por vez**: releer un libro en marzo y en octubre son dos entradas,
 * y ver la misma película tres veces son tres. Una columna suelta solo podía
 * guardar una de ellas — que es justamente por lo que aquellas seis se quedaron
 * sin llenar.
 *
 * `entity_id` es el identificador **externo**, el que espera la ruta de detalle
 * del frontend: ISBN-13 en libros, imdbID en películas y series, id de IGDB en
 * juegos, id de álbum, `youtube_id` en vídeos. Guardar el autoincremento deja
 * una entrada cuyo enlace no resuelve, y el fallo solo se ve al pulsarla — es la
 * misma trampa que `feed_events.entity_id` documenta en la skill de base de datos.
 *
 * `entryDate` **admite fechas futuras** a propósito (decidido el 2026-09-04):
 * `SeriesSeasonTracker` deja marcar una temporada con fecha por venir y esos son
 * datos del usuario, no un error que el diario deba corregir.
 */
class JournalEntry
{
    public const MEDIA_BOOK   = 'book';
    public const MEDIA_MOVIE  = 'movie';
    public const MEDIA_SERIES = 'series';
    public const MEDIA_GAME   = 'game';
    public const MEDIA_ALBUM  = 'album';
    public const MEDIA_VIDEO  = 'video';

    public const SOURCE_MANUAL          = 'manual';
    public const SOURCE_STATUS          = 'status';
    public const SOURCE_READING_SESSION = 'reading_session';
    public const SOURCE_SERIES_SEASON   = 'series_season';

    /**
     * Los SEIS medios, series incluida. No copiar de
     * `FeedEvent::VALID_ENTITY_TYPES`, que solo tiene cinco: en el feed una serie
     * viaja como película porque en el backend son la misma entidad, pero el
     * diario sí las distingue —una entrada de serie es una TEMPORADA—.
     */
    public const MEDIA = [
        self::MEDIA_BOOK,
        self::MEDIA_MOVIE,
        self::MEDIA_SERIES,
        self::MEDIA_GAME,
        self::MEDIA_ALBUM,
        self::MEDIA_VIDEO,
    ];

    /**
     * Qué estado significa «lo he consumido» en cada medio. Es la tabla que
     * dispara la entrada automática, y vive **aquí y en ningún otro sitio**: los
     * cinco `Update<Medio>UserStatusesUseCase` no la copian, preguntan.
     *
     * Tres decisiones, todas del 2026-09-04:
     *  - **Los juegos disparan con los tres** (`played`, `completed` y
     *    `100-completed`), decisión de David: quiere que cuente el juego al que
     *    le dedicó horas aunque no piense terminarlo. Marcar dos a la vez sigue
     *    apuntando UNA entrada — la clave `medio:id:día` la deduplica.
     *  - **`re-reading`, `re-listening` y `re-watching` NO disparan.** Son
     *    estados de «estoy en ello», no de haberlo terminado; la repetición la
     *    apunta el `read`/`listened`/`watched` siguiente, que otro día crea su
     *    propia entrada.
     *  - **`owned`, `saved` y las listas de deseos tampoco**: tener algo no es
     *    haberlo consumido, y en este proyecto conviven en el mismo ítem.
     *
     * Series no está: sus entradas no salen de un estado sino del seguimiento
     * por temporadas (`TrackSeriesSeasonUseCase`).
     */
    public const CONSUMED_STATUSES = [
        self::MEDIA_BOOK  => ['read'],
        self::MEDIA_MOVIE => ['viewed'],
        self::MEDIA_GAME  => ['played', 'completed', '100-completed'],
        self::MEDIA_ALBUM => ['listened'],
        self::MEDIA_VIDEO => ['watched'],
    ];

    private const VALID_SOURCES = [
        self::SOURCE_MANUAL,
        self::SOURCE_STATUS,
        self::SOURCE_READING_SESSION,
        self::SOURCE_SERIES_SEASON,
    ];

    /**
     * @param string|null $sourceId NULL solo en `manual`: es lo que permite repetir,
     *                              porque MySQL admite NULLs repetidos en un índice único
     * @param bool|null   $isRepeat no es una columna: lo calcula el repositorio al leer
     */
    public function __construct(
        private ?int    $id,
        private int     $userId,
        private string  $media,
        private string  $entityId,
        private string  $entityTitle,
        private ?string $entityCover,
        private string  $entryDate,
        private ?float  $rating,
        private string  $source = self::SOURCE_MANUAL,
        private ?string $sourceId = null,
        private ?bool   $isRepeat = null
    ) {
        if (!in_array($media, self::MEDIA, true)) {
            throw new InvalidArgumentException("Invalid journal media: {$media}");
        }
        if (!in_array($source, self::VALID_SOURCES, true)) {
            throw new InvalidArgumentException("Invalid journal source: {$source}");
        }
        if ($source !== self::SOURCE_MANUAL && $sourceId === null) {
            throw new InvalidArgumentException("A '{$source}' entry needs a sourceId");
        }
        if ($rating !== null && ($rating < 0.0 || $rating > 5.0)) {
            throw new InvalidArgumentException("Rating out of range: {$rating}");
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate) !== 1) {
            throw new InvalidArgumentException("entryDate must be YYYY-MM-DD, got: {$entryDate}");
        }
    }

    public function getId(): ?int           { return $this->id; }
    public function getUserId(): int        { return $this->userId; }
    public function getMedia(): string      { return $this->media; }
    public function getEntityId(): string   { return $this->entityId; }
    public function getEntityTitle(): string { return $this->entityTitle; }
    public function getEntityCover(): ?string { return $this->entityCover; }
    public function getEntryDate(): string  { return $this->entryDate; }
    public function getRating(): ?float     { return $this->rating; }
    public function getSource(): string     { return $this->source; }
    public function getSourceId(): ?string  { return $this->sourceId; }
    public function isRepeat(): ?bool       { return $this->isRepeat; }

    /**
     * Construye desde una fila de `journal_entry`. `is_repeat` solo viene en las
     * consultas de listado, que lo resuelven con una subconsulta.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:          isset($row['id']) ? (int) $row['id'] : null,
            userId:      (int) $row['user_id'],
            media:       (string) $row['media'],
            entityId:    (string) $row['entity_id'],
            entityTitle: (string) $row['entity_title'],
            entityCover: $row['entity_cover'] ?? null,
            entryDate:   (string) $row['entry_date'],
            rating:      isset($row['rating']) && $row['rating'] !== null ? (float) $row['rating'] : null,
            source:      (string) ($row['source'] ?? self::SOURCE_MANUAL),
            sourceId:    $row['source_id'] ?? null,
            isRepeat:    isset($row['is_repeat']) ? (bool) $row['is_repeat'] : null
        );
    }

    /**
     * La forma que consume el frontend. `is_repeat` se omite si no se calculó:
     * mandarlo en `false` diría que no es repetición, que no es lo mismo que no
     * haberlo mirado.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id'           => $this->id,
            'media'        => $this->media,
            'entity_id'    => $this->entityId,
            'entity_title' => $this->entityTitle,
            'entity_cover' => $this->entityCover,
            'entry_date'   => $this->entryDate,
            'rating'       => $this->rating,
            'source'       => $this->source,
        ];

        if ($this->isRepeat !== null) {
            $data['is_repeat'] = $this->isRepeat;
        }

        return $data;
    }
}
