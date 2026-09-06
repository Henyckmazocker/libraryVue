<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\Video\VideoRepositoryInterface;

/**
 * De `(medio, entityId)` al título y la portada que se copian en la entrada.
 *
 * El diario guarda título y portada en su propia fila —como `feed_events` y
 * `media_list_item`—, así que alguien tiene que resolverlos al dar de alta a
 * mano. Los emisores automáticos del M4 **no** pasan por aquí: ellos ya tienen
 * la entidad delante y se ahorran la consulta.
 *
 * El despacho por medio vive **solo** en este fichero. Repartirlo entre los
 * casos de uso de alta y de edición sería la segunda copia de un `match` de
 * seis ramas, y la que se olvidaría de actualizar cuando aparezca un medio más.
 */
class JournalItemResolver
{
    public function __construct(
        private readonly EditionRepositoryInterface $editions,
        private readonly MovieRepositoryInterface   $movies,
        private readonly GameRepositoryInterface    $games,
        private readonly AlbumRepositoryInterface   $albums,
        private readonly VideoRepositoryInterface   $videos
    ) {}

    /**
     * @return array{0: string, 1: string|null}|null  [título, portada], o null si
     *         el catálogo no conoce ese identificador
     */
    public function resolve(string $media, string $entityId): ?array
    {
        return match ($media) {
            // En libros el identificador externo es el ISBN, no el `edition_id`:
            // la ficha vive en `/books/:isbn`. `findByIsbn` prueba el de 13 y
            // luego el de 10, que es el orden en que están poblados.
            JournalEntry::MEDIA_BOOK => $this->pair(
                $this->editions->findByIsbn($entityId),
                static fn ($e) => [$e->getTitle(), $e->getCoverUrlMedium()]
            ),
            // Series y películas comparten tabla y repositorio: en el backend son
            // la misma entidad y lo que las separa es `movie.media_type`.
            JournalEntry::MEDIA_MOVIE,
            JournalEntry::MEDIA_SERIES => $this->pair(
                $this->movies->findById($entityId),
                static fn ($m) => [$m->getTitle(), $m->getCoverUrl()]
            ),
            JournalEntry::MEDIA_GAME => $this->pair(
                ctype_digit($entityId) ? $this->games->findById((int) $entityId) : null,
                static fn ($g) => [$g->getTitle(), $g->getCoverUrl()]
            ),
            JournalEntry::MEDIA_ALBUM => $this->pair(
                ctype_digit($entityId) ? $this->albums->findById((int) $entityId) : null,
                static fn ($a) => [$a->getTitle(), $a->getCoverUrl()]
            ),
            // El identificador externo de un vídeo es el `youtube_id`, no la PK.
            JournalEntry::MEDIA_VIDEO => $this->pair(
                $this->videos->findByYouTubeId($entityId),
                static fn ($v) => [$v->getTitle(), $v->getCoverUrl()]
            ),
            default => null,
        };
    }

    /**
     * @param callable(object): array{0: string, 1: string|null} $extract
     * @return array{0: string, 1: string|null}|null
     */
    private function pair(?object $entity, callable $extract): ?array
    {
        return $entity === null ? null : $extract($entity);
    }
}
