<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Model\Album;
use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\Video\VideoRepositoryInterface;

/**
 * De `(medio, entityId)` al identificador canónico, el título y la portada que
 * se copian en la entrada.
 *
 * El diario guarda título y portada en su propia fila —como `feed_events` y
 * `media_list_item`—, así que alguien tiene que resolverlos al dar de alta a
 * mano. Los emisores automáticos del M4 **no** pasan por aquí: ellos ya tienen
 * la entidad delante y se ahorran la consulta.
 *
 * El despacho por medio vive **solo** en este fichero. Repartirlo entre los
 * casos de uso de alta y de edición sería la segunda copia de un `match` de
 * seis ramas, y la que se olvidaría de actualizar cuando aparezca un medio más.
 *
 * Y por eso el identificador canónico sale **también** de aquí: quien resuelve
 * el ítem es el único que sabe con qué identidad lo conoce el resto de la app.
 * `journal_entry.entity_id` guarda, para cada medio, el identificador con el
 * que el backend puede volver a encontrar el ítem y con el que
 * `libraryItem.idOf` del frontend registra su portada. El cliente puede mandar
 * cualquier forma que el catálogo entienda; aquí se normaliza antes de escribir.
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
     * @return array{0: string, 1: string, 2: string|null}|null
     *         [entityId canónico, título, portada], o null si el catálogo no lo conoce
     */
    public function resolve(string $media, string $entityId): ?array
    {
        return match ($media) {
            // En libros el identificador externo es el ISBN, no el `edition_id`:
            // la ficha vive en `/books/:isbn`. `findByIsbn` prueba el de 13 y
            // luego el de 10, que es el orden en que están poblados.
            JournalEntry::MEDIA_BOOK => $this->pair(
                $entityId,
                $this->editions->findByIsbn($entityId),
                static fn ($e) => [$e->getTitle(), $e->getCoverUrlMedium()]
            ),
            // Series y películas comparten tabla y repositorio: en el backend son
            // la misma entidad y lo que las separa es `movie.media_type`.
            JournalEntry::MEDIA_MOVIE,
            JournalEntry::MEDIA_SERIES => $this->pair(
                $entityId,
                $this->movies->findById($entityId),
                static fn ($m) => [$m->getTitle(), $m->getCoverUrl()]
            ),
            JournalEntry::MEDIA_GAME => $this->pair(
                $entityId,
                ctype_digit($entityId) ? $this->games->findById((int) $entityId) : null,
                static fn ($g) => [$g->getTitle(), $g->getCoverUrl()]
            ),
            JournalEntry::MEDIA_ALBUM => $this->pairAlbum(
                // El álbum es el ÚNICO medio con dos formas de identificador en
                // circulación, y de ahí la rama de más: su ficha vive en
                // `/albums/:albumId`, donde el parámetro es el MBID (o el base62
                // de los álbumes viejos de Spotify), mientras que el diario, las
                // portadas locales y `user_albums` hablan del PK de `albums`.
                // `findBySpotifyId` casa las dos formas de una vez —su `WHERE`
                // mira `spotify_id` **y** `mb_release_group_gid`, porque en los
                // álbumes del mirror la primera columna va a NULL—, así que aquí
                // basta con mirar la forma del id para elegir la consulta.
                ctype_digit($entityId)
                    ? $this->albums->findById((int) $entityId)
                    : $this->albums->findBySpotifyId($entityId)
            ),
            // El identificador externo de un vídeo es el `youtube_id`, no la PK.
            JournalEntry::MEDIA_VIDEO => $this->pair(
                $entityId,
                $this->videos->findByYouTubeId($entityId),
                static fn ($v) => [$v->getTitle(), $v->getCoverUrl()]
            ),
            default => null,
        };
    }

    /**
     * Los otros cinco medios devuelven el `$entityId` que entró, y no por
     * pereza: en ellos el parámetro de la ruta ya **es** la identidad con la que
     * los conoce el resto de la app —ISBN en libros, tconst en película y serie,
     * id de IGDB en juegos (que es la propia PK de `games`) y `youtube_id` en
     * vídeos—, así que normalizar sería devolver lo mismo dando un rodeo.
     *
     * @param callable(object): array{0: string, 1: string|null} $extract
     * @return array{0: string, 1: string, 2: string|null}|null
     */
    private function pair(string $entityId, ?object $entity, callable $extract): ?array
    {
        if ($entity === null) {
            return null;
        }

        [$titulo, $portada] = $extract($entity);

        return [$entityId, $titulo, $portada];
    }

    /**
     * El álbum es el único que descarta el identificador recibido: la identidad
     * canónica es el PK, venga como venga el id de la petición.
     *
     * No es una preferencia de estilo. Los emisores automáticos ya escriben el
     * PK (`UpdateAlbumUserStatusesUseCase.php:82`), así que aceptar el MBID sin
     * normalizar dejaría dos `entity_id` para el mismo álbum: dos filas donde
     * debería haber una —la clave de duplicado del alta automática es
     * `medio:id:día` (`JournalService.php:121-142`)—, y una portada rota, porque
     * la clave de `cover_file` la pone `libraryItem.idOf`, que en álbum es el PK.
     *
     * @return array{0: string, 1: string, 2: string|null}|null
     */
    private function pairAlbum(?Album $album): ?array
    {
        return $album === null
            ? null
            : [(string) $album->getId(), $album->getTitle(), $album->getCoverUrl()];
    }
}
