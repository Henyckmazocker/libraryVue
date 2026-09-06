<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Model\JournalEntry;
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
 * **No pasa por las acciones `update_*_rating`**, que están rotas y sin
 * consumidores (`ActionRouter.php:291` construye su comando con los argumentos
 * cambiados de orden; está en el Roadmap). Se llama al repositorio de cada
 * medio, que ya tiene el método hecho y probado.
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
        private readonly UserVideoRepositoryInterface       $userVideos,
        private readonly VideoRepositoryInterface           $videos,
        private readonly SeriesSeasonRepositoryInterface    $seasons,
        private readonly LoggerInterface                    $logger
    ) {}

    /**
     * Propaga la valoración de una entrada al ítem correspondiente.
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
                JournalEntry::MEDIA_GAME   => $this->userGames->updateRating($userId, (int) $entityId, $rating),
                JournalEntry::MEDIA_ALBUM  => $this->userAlbums->updateRating($userId, (int) $entityId, $rating),
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
     * `UpdateBookRatingUseCase.php:41-59`. Se escribe el `work_rating`, que es
     * el que la ficha enseña.
     */
    private function writeBook(int $userId, string $isbn, float $rating): void
    {
        $edition = $this->editions->findByIsbn($isbn);

        if ($edition === null) {
            return;
        }

        $this->userBookEditions->updateRating($userId, $edition->getEditionId(), $rating);
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
