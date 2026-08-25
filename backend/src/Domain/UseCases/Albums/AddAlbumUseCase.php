<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\Model\Album;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Catalog\AlbumCatalogInterface;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\CoverService;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\AddAlbumCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class AddAlbumUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly AlbumRepositoryInterface $albumRepository,
        private readonly UserAlbumRepositoryInterface $userAlbumRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly FeedEventService $feedEventService,
        private readonly CoverService $coverService,
        private readonly AlbumCatalogInterface $albumCatalog,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Cambia un álbum de Spotify por su equivalente de MusicBrainz, si lo hay
     *
     * Los términos de Spotify prohíben *"store, aggregate or create
     * compilations or databases of Spotify Content"*. El mirror cubre la
     * inmensa mayoría de los casos, pero `FallbackAlbumCatalog` cae a Spotify
     * cuando la búsqueda por nombre no encuentra nada, y guardar ese resultado
     * metía catálogo de Spotify en `albums` sin caducidad.
     *
     * El puente es el **código de barras**: si el UPC que trae Spotify está en
     * `mb_release_group`, el álbum SÍ está en MusicBrainz —solo que no se
     * encontró por nombre— y se persiste la ficha abierta en lugar de la ajena.
     *
     * Lo que no se resuelve así se guarda igual, pero marcado con
     * `catalog_source = 'spotify'` para que `mirror-sync.sh --purge` le anule
     * el enriquecimiento a los 5 meses.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function preferOpenCatalog(array $data): array
    {
        $identity = $data['mb_release_group_gid'] ?? null;
        if ($identity !== null) {
            return $data + ['catalog_source' => 'musicbrainz'];
        }

        $upc = $data['upc'] ?? null;
        $match = !empty($upc) ? $this->albumCatalog->findByBarcode((string) $upc) : null;

        if ($match === null) {
            $this->logger->info('album saved from Spotify catalog', [
                'spotify_id' => $data['spotify_id'] ?? null,
                'upc'        => $upc,
            ]);

            return $data + ['catalog_source' => 'spotify'];
        }

        $this->logger->info('album resolved to MusicBrainz by barcode', [
            'upc'  => $upc,
            'mbid' => $match['id'],
        ]);

        // La identidad y el catálogo pasan a ser los abiertos; lo del usuario
        // (notas, estados, valoración) no se toca porque no vive en este array.
        return array_merge($data, [
            'mb_release_group_gid'   => $match['id'],
            'spotify_id'             => $data['spotify_id'] ?? null,
            'title'                  => $match['name'],
            'artist'                 => $match['artists'][0]['name'] ?? $data['artist'] ?? '',
            'artist_id'              => $match['artists'][0]['id'] ?? null,
            'release_date'           => $match['release_date'],
            'release_date_precision' => $match['release_date_precision'],
            'cover_url'              => $match['images'][0]['url'] ?? $data['cover_url'] ?? null,
            'label'                  => $match['label'],
            'total_tracks'           => $match['total_tracks'],
            'album_type'             => $match['album_type'],
            // MusicBrainz no tiene equivalente de ninguna de las dos, y dejar
            // las de Spotify sería justo lo que este método evita.
            'popularity'             => null,
            'duration_ms'            => null,
            'catalog_source'         => 'musicbrainz',
        ]);
    }

    protected function doExecute($command): Album
    {
        if (!$command instanceof AddAlbumCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddAlbumCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Busca por la identidad, sea MBID o base62: findBySpotifyId consulta
        // las dos columnas. El nombre del método se conserva porque lo declara
        // AlbumRepositoryInterface y lo usan otros sitios; renombrarlo es deuda
        // anotada en el plan «Mirror de Música».
        $existingAlbum = $this->albumRepository->findBySpotifyId($command->albumId->toString());

        if (!$existingAlbum) {
            // Album does not exist yet — persist it from the command data
            $album = Album::fromArray(array_merge(
                $this->preferOpenCatalog($command->toAlbumArray()),
                ['userStatuses' => $command->statuses ?: ['in-wishlist']]
            ));
            $album = $this->albumRepository->save($album);
        } else {
            $album = $existingAlbum;
        }

        // Check if user already has this album
        if ($this->userAlbumRepository->hasAlbum($command->userId, $album->getId())) {
            throw new InvalidArgumentException('You already have this album in your library.');
        }

        // Add the album to the user's library
        $this->userAlbumRepository->add(
            $command->userId,
            $album->getId(),
            $command->statuses,
            $command->userRating?->toFloat(),
            $command->personalNotes,
            null, // completedAt — not supplied at add time
            $command->listenCount,
            $command->favoriteTrack,
            $command->ownershipFormatId
        );

        $this->feedEventService->recordItemAdded(
            $command->userId,
            'album',
            (string) $album->getId(),
            $album->getTitle(),
            $album->getCoverUrl()
        );

        // Si este álbum ya se había visto en una búsqueda, su carátula está en
        // disco bajo la clave del CATÁLOGO —el MBID—, y la de biblioteca es el
        // id interno de MySQL. Reetiquetar la fila que hay evita una segunda
        // copia del mismo JPEG y una descarga que sobra.
        //
        // Si se reetiquetó, NO se registra: la fila promovida guarda la URL ya
        // resuelta y `register()` vería una `source_url` distinta de la que trae
        // el álbum, con lo que anularía su `storage_path` y la bajaría otra vez.
        $reaprovechada = $this->coverService->promoteCatalogCover(
            'album',
            $command->albumId->toString(),
            (string) $album->getId()
        );

        if (!$reaprovechada) {
            // Copia local de la portada: registra la fila ahora (sin red) y deja
            // la descarga para después de la respuesta. Un fallo aquí nunca
            // afecta al guardado; lo pendiente lo recoge `covers:backfill`.
            $this->coverService->recordCover(
                'album',
                (string) $album->getId(),
                $album->getCoverUrl()
            );
        }

        return $album;
    }

    protected function getLogContext(): string
    {
        return 'AddAlbumUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Album added successfully to user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to add album to user library';
    }
}
