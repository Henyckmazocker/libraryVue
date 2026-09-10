<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\Movie\SeriesSeasonRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\Video\VideoRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * «La última entrada manda»: la valoración de una entrada del diario se escribe
 * también en la del ítem, la que ven `/library` y la ficha.
 *
 * Decidido con David el 2026-09-04. El motivo es que sin esto conviven dos
 * números para la misma película sin que nada explique cuál manda; así el
 * historial vive en el diario y la ficha sigue enseñando lo que piensas HOY.
 *
 * **No pasa por las acciones `update_*_rating`.** Cuando esto se escribió era
 * porque estaban rotas; desde el 2026-09-09 funcionan y las usan las seis
 * fichas, pero el motivo de ahora es otro: aquí ya se tiene la entidad delante
 * y una acción exigiría rehacer el payload y la pila de middleware para acabar
 * en el mismo repositorio. Se llama al de cada medio, que ya tiene el método
 * hecho y probado.
 *
 * Como `FeedEventService`, **se traga sus errores**: no poder propagar una
 * valoración no puede impedir que la entrada del diario se guarde.
 */
class JournalRatingWriter
{
    public function __construct(
        private readonly EditionRepositoryInterface         $editions,
        private readonly UserBookEditionRepositoryInterface $userBookEditions,
        private readonly UserMovieRepositoryInterface       $userMovies,
        private readonly UserGameRepositoryInterface        $userGames,
        private readonly UserAlbumRepositoryInterface       $userAlbums,
        private readonly AlbumRepositoryInterface           $albums,
        private readonly UserVideoRepositoryInterface       $userVideos,
        private readonly VideoRepositoryInterface           $videos,
        private readonly SeriesSeasonRepositoryInterface    $seasons,
        private readonly LoggerInterface                    $logger
    ) {}

    /**
     * Propaga la valoración de una entrada al ítem correspondiente.
     *
     * **Por qué cada rama convierte como convierte (auditado el 2026-09-10).**
     * `journal_entry.entity_id` es una columna de texto que guarda **seis
     * identidades distintas**, y ninguna regla obliga a que sea la PK del ítem;
     * el repositorio de usuario de cada medio, en cambio, quiere la suya. Ahí
     * vivió meses el fallo del álbum —`(int)` sobre un MBID no avisa de nada—,
     * así que la auditoría se escribe aquí para que la próxima rama nazca
     * teniéndola delante:
     *
     * - **book** → `writeBook()`: el ISBN se cambia por el `edition_id` con
     *   `editions->findByIsbn()`. Ningún casteo.
     * - **movie** → `userMovies->updateRating(int, **string**, ?float)`: el
     *   identificador viaja como cadena porque la PK de `movie` se llama `isbn`
     *   y contiene el tconst de IMDb. No hay conversión que pueda salir mal.
     * - **game** → `(int) $entityId`, y **es correcto**: ver su rama.
     * - **album** → `writeAlbum()`, el único que resuelve: ver su docblock.
     * - **video** → `writeVideo()`: el `youtube_id` se resuelve a PK con
     *   `videos->findByYouTubeId()`, el mismo patrón que el álbum.
     * - **series** → `writeSeason()`: no trata `entityId` como número; parte el
     *   `source_id` (`"tconst:temporada"`) y solo propaga si la entrada nació
     *   del seguimiento por temporadas.
     *
     * @param string|null $sourceId el `source_id` de la entrada; en series es lo
     *                              único que dice de QUÉ temporada se habla
     */
    public function write(
        int     $userId,
        string  $media,
        string  $entityId,
        ?float  $rating,
        string  $source = JournalEntry::SOURCE_MANUAL,
        ?string $sourceId = null
    ): void {
        // Sin valoración no hay nada que propagar. Y en concreto **no** se borra
        // la del ítem: apuntar en el diario sin puntuar no es despuntuar.
        if ($rating === null) {
            return;
        }

        try {
            match ($media) {
                JournalEntry::MEDIA_BOOK   => $this->writeBook($userId, $entityId, $rating),
                JournalEntry::MEDIA_MOVIE  => $this->userMovies->updateRating($userId, $entityId, $rating),
                // El único `(int) $entityId` que queda, y es la identidad, no
                // una conversión: `games` **no tiene** columna `igdb_id` —su PK
                // `games.id` ES el id de IGDB (`DESCRIBE games`, 2026-09-10)—,
                // así que lo que el diario guarda ya es lo que quiere
                // `user_games`, y siempre es numérico. Se parece al fallo que
                // tenía el álbum aquí abajo y no lo es: no lo conviertas en una
                // resolución por parecido.
                JournalEntry::MEDIA_GAME   => $this->userGames->updateRating($userId, (int) $entityId, $rating),
                JournalEntry::MEDIA_ALBUM  => $this->writeAlbum($userId, $entityId, $rating),
                JournalEntry::MEDIA_VIDEO  => $this->writeVideo($userId, $entityId, $rating),
                JournalEntry::MEDIA_SERIES => $this->writeSeason($userId, $entityId, $rating, $source, $sourceId),
                default => null,
            };
        } catch (Throwable $e) {
            $this->logger->warning('JournalRatingWriter: could not propagate rating to the item', [
                'media'     => $media,
                'entity_id' => $entityId,
                'user_id'   => $userId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * El diario guarda el ISBN y el repositorio de usuario quiere el
     * `edition_id`: la conversión es la misma que hace
     * `UpdateBookRatingUseCase.php:41-59`.
     *
     * Se escribe `edition_rating`, NO `work_rating`: es la columna que la ficha
     * enseña —`UserBookEdition::toArray()` la publica como `user_rating`,
     * `personal_rating` y `rating` (`UserBookEdition.php:239-243`), y
     * `LibraryMediaItem.vue:188` lee `item.user_rating`—. Hasta el 2026-09-09
     * este método pasaba el rating en el TERCER argumento posicional, que es
     * `work_rating`, y dejaba el cuarto en su default `null`: como el UPDATE del
     * repositorio escribe las dos columnas de una vez
     * (`MySqlUserBookEditionRepository.php:318-332`), valorar desde el diario
     * guardaba en la columna que nadie lee y **borraba la que sí**.
     *
     * `work_rating` —la valoración de la OBRA, otro concepto del modelo
     * Work/Edition— se relee y se reescribe igual, por ese mismo UPDATE de dos
     * columnas.
     */
    private function writeBook(int $userId, string $isbn, float $rating): void
    {
        $edition = $this->editions->findByIsbn($isbn);

        if ($edition === null) {
            return;
        }

        $actual = $this->userBookEditions->findByUserAndEdition($userId, $edition->getEditionId());

        $this->userBookEditions->updateRating(
            $userId,
            $edition->getEditionId(),
            $actual?->getWorkRating()?->toFloat(), // work_rating: se conserva
            $rating                                // edition_rating: lo que la ficha lee
        );
    }

    /**
     * `user_albums` se indexa por el PK de `albums`, y el diario **puede** traer
     * otra cosa: la ficha vive en `/albums/:albumId`, donde el parámetro es el
     * MBID del mirror —o el base62 de los álbumes viejos de Spotify—, y ese es
     * el id que llega cuando la entrada se apunta desde ahí.
     *
     * Hasta el 2026-09-10 esto era `(int) $entityId`, y en eso consistía el
     * fallo: PHP convierte `"171db008-7f7b-…"` en **`171`** sin error ni aviso,
     * así que la valoración acababa en el álbum 171 —uno cualquiera, de otro
     * disco— en vez de no acabar en ninguno. Un fallo así no se ve: la entrada
     * del diario se guarda, la petición devuelve 200 y quien lo nota es el dueño
     * de un álbum que él no ha puntuado.
     *
     * `JournalItemResolver` ya normaliza a PK lo que entra por el alta manual,
     * pero por aquí pasan dos vías más que no lo hacen: la edición de una
     * entrada ya guardada (`UpdateJournalEntryUseCase.php:48-57`, que reenvía el
     * `entity_id` tal como está en la BD) y los emisores automáticos, que llaman
     * a `write()` sin pasar por el resolver. Por eso la guarda va también aquí.
     *
     * La resolución es la misma que la del resolver —`ctype_digit` elige la
     * consulta, y `findBySpotifyId` casa MBID y base62 de una vez porque su
     * `WHERE` mira además `mb_release_group_gid`, que es donde vive la identidad
     * de los álbumes del mirror—. Aquí también se castea, pero dentro de la rama
     * que ya ha comprobado que el id es numérico, y esa es toda la diferencia.
     * La repetición es deliberada: llamar al resolver desde aquí arrastraría una
     * consulta de título y portada que a este servicio no le hace falta, y
     * unificar las dos copias es un plan aparte.
     *
     * Si el álbum no aparece, no se propaga nada, como en `writeBook()` y
     * `writeVideo()`. Es la respuesta coherente con una clase que se traga sus
     * errores: quedarse sin valorar se arregla en un clic; valorar el álbum de
     * otro no se descubre nunca.
     */
    private function writeAlbum(int $userId, string $albumId, float $rating): void
    {
        $album = ctype_digit($albumId)
            ? $this->albums->findById((int) $albumId)
            : $this->albums->findBySpotifyId($albumId);

        if ($album === null) {
            return;
        }

        $this->userAlbums->updateRating($userId, $album->getId(), $rating);
    }

    /**
     * `user_videos` se indexa por la PK del vídeo, no por el `youtube_id` que
     * guarda el diario.
     */
    private function writeVideo(int $userId, string $youtubeId, float $rating): void
    {
        $video = $this->videos->findByYouTubeId($youtubeId);

        if ($video === null || $video->getId() === null) {
            return;
        }

        $this->userVideos->updateRating($userId, $video->getId(), $rating);
    }

    /**
     * **Una serie no tiene valoración propia: la tiene cada temporada**
     * (`user_series_seasons.personal_rating`), y de qué temporada hablamos solo
     * lo sabe el `source_id` de una entrada nacida del seguimiento por
     * temporadas (`"tt14452776:2"`).
     *
     * Una entrada de serie creada **a mano** no dice ninguna temporada, así que
     * no propaga nada. Es deliberado: escribirla en la temporada 1, o en todas,
     * sería inventarse un dato del usuario.
     */
    private function writeSeason(
        int     $userId,
        string  $entityId,
        float   $rating,
        string  $source,
        ?string $sourceId
    ): void {
        if ($source !== JournalEntry::SOURCE_SERIES_SEASON || $sourceId === null) {
            return;
        }

        [$isbn, $season] = array_pad(explode(':', $sourceId, 2), 2, null);

        if ($isbn !== $entityId || $season === null || !ctype_digit($season)) {
            return;
        }

        // `trackSeason` es un upsert que reescribe TODA la fila: su
        // `ON DUPLICATE KEY UPDATE` hace `date_viewed = VALUES(date_viewed)` y
        // `notes = VALUES(notes)` (`MySqlSeriesSeasonRepository.php:38-41`).
        // Pasarle `null` en esos dos por no tenerlos a mano **borraría la fecha
        // de visionado y las notas de la temporada** al puntuarla desde el
        // diario. Se releen y se devuelven tal cual.
        $actual = $this->seasons->getProgress($userId, $entityId)[(int) $season] ?? [];

        $this->seasons->trackSeason(
            $userId,
            $entityId,
            (int) $season,
            $actual['status'] ?? 'viewed',
            $actual['date_viewed'] ?? null,
            $rating,
            $actual['notes'] ?? null
        );
    }
}
