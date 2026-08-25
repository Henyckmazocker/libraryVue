<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\DTO\Commands\AddAlbumNoteCommand;
use App\Domain\UseCases\Albums\AddAlbumNoteUseCase;
use App\Domain\UseCases\Albums\AddAlbumUseCase;
use App\Domain\UseCases\Albums\DeleteAlbumUseCase;
use App\Domain\UseCases\Albums\UpdateAlbumRatingUseCase;
use App\Domain\UseCases\Albums\UpdateAlbumUserStatusesUseCase;
use App\Domain\UseCases\Albums\GetAlbumsUseCase;
use App\Domain\UseCases\Albums\GetAlbumAllowedStatusesUseCase;
use App\Domain\UseCases\Albums\EditUserAlbumUseCase;
use App\Domain\UseCases\Albums\GetTrendingAlbumsUseCase;
use App\Domain\DTO\Commands\AddAlbumCommand;
use App\Domain\DTO\Commands\DeleteAlbumCommand;
use App\Domain\DTO\Commands\UpdateAlbumRatingCommand;
use App\Domain\DTO\Commands\UpdateAlbumStatusesCommand;
use App\Domain\DTO\Commands\EditUserAlbumCommand;
use App\Domain\DTO\Queries\GetTrendingAlbumsQuery;
use App\Domain\Repository\Album\AlbumTagRepositoryInterface;
use App\Domain\Repository\Album\AlbumNoteRepositoryInterface;
use App\Domain\Model\ValueObjects\AlbumId;
use App\Domain\Repository\Catalog\AlbumCatalogInterface;
use App\Domain\Services\AlbumTrackService;
use App\Infrastructure\Http\PostResponse;
use App\Infrastructure\Persistence\Catalog\MySqlAlbumCatalog;
use App\Domain\Services\SpotifyService;
use App\Domain\Services\LastFmService;
use App\Domain\DTO\Queries\GetLastFmStatsQuery;
use App\Domain\UseCases\Albums\GetListeningStatsUseCase;

class AlbumController extends BaseController implements Contracts\AlbumControllerInterface
{
    public function __construct(
        private readonly AddAlbumUseCase $addAlbumUseCase,
        private readonly DeleteAlbumUseCase $deleteAlbumUseCase,
        private readonly UpdateAlbumRatingUseCase $updateAlbumRatingUseCase,
        private readonly UpdateAlbumUserStatusesUseCase $updateAlbumUserStatusesUseCase,
        private readonly GetAlbumsUseCase $getAlbumsUseCase,
        private readonly GetAlbumAllowedStatusesUseCase $getAlbumAllowedStatusesUseCase,
        private readonly EditUserAlbumUseCase $editUserAlbumUseCase,
        private readonly GetTrendingAlbumsUseCase $getTrendingAlbumsUseCase,
        private readonly AlbumTagRepositoryInterface $albumTagRepository,
        private readonly AlbumNoteRepositoryInterface $albumNoteRepository,
        private readonly AddAlbumNoteUseCase $addAlbumNoteUseCase,
        private readonly SpotifyService $spotifyService,
        private readonly AlbumCatalogInterface $albumCatalog,
        // Los dos concretos, y no la interfaz, porque las pistas no forman
        // parte del contrato del catálogo: solo las tiene el mirror.
        private readonly MySqlAlbumCatalog $mirrorCatalog,
        private readonly AlbumTrackService $albumTrackService,
        private readonly LastFmService $lastFmService,
        private readonly GetListeningStatsUseCase $getListeningStatsUseCase
    ) {}

    // =========================================================================
    // Library CRUD
    // =========================================================================

    public function addAlbum(AddAlbumCommand $command): array
    {
        $album = $this->addAlbumUseCase->execute($command);
        return $this->successResponse('Album added: ' . $album->getTitle(), $album->toArray(), 201);
    }

    public function deleteAlbum(DeleteAlbumCommand $command): array
    {
        $this->deleteAlbumUseCase->execute($command);
        return $this->successResponse('Album removed from your library: ' . $command->albumId);
    }

    public function updateAlbumRating(UpdateAlbumRatingCommand $command): array
    {
        $this->updateAlbumRatingUseCase->execute($command);
        return $this->successResponse('Album rating updated successfully.');
    }

    public function updateAlbumUserStatuses(UpdateAlbumStatusesCommand $command): array
    {
        $this->updateAlbumUserStatusesUseCase->execute($command);
        return $this->successResponse('User statuses updated for Album ID ' . $command->albumId);
    }

    public function editUserAlbum(EditUserAlbumCommand $command): array
    {
        $this->editUserAlbumUseCase->execute($command);
        return $this->successResponse('User album updated successfully.');
    }

    public function getAlbumAllowedStatuses(): array
    {
        $statuses = $this->getAlbumAllowedStatusesUseCase->execute([]);
        return $this->successResponse('Allowed album statuses retrieved.', $statuses);
    }

    public function getAlbums(array $params): array
    {
        $albums = $this->getAlbumsUseCase->execute($params);

        $albumsArray = array_map(function ($album) {
            return $album->toArray();
        }, $albums);

        return $this->successResponse('Albums data retrieved.', $albumsArray);
    }

    public function getTrendingAlbums(GetTrendingAlbumsQuery $query): array
    {
        $userId = $_SESSION['user_data']['id'] ?? null;

        $queryWithUser = GetTrendingAlbumsQuery::create(
            $query->limit,
            $query->daysWindow,
            $userId
        );

        $trending = $this->getTrendingAlbumsUseCase->execute($queryWithUser);
        return $this->successResponse('Trending albums retrieved.', $trending);
    }

    // =========================================================================
    // Tags
    // =========================================================================

    public function getUserAlbumTags(int $userId): array
    {
        try {
            $tags = $this->albumTagRepository->findByUser($userId);
            return $this->successResponse('Tags retrieved successfully', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving tags: ' . $e->getMessage());
        }
    }

    public function createUserAlbumTag(int $userId, string $name, string $color = '#1976d2'): array
    {
        try {
            $tagId = $this->albumTagRepository->create($userId, $name, $color);
            return $this->successResponse('Tag created successfully', [
                'id'    => $tagId,
                'name'  => $name,
                'color' => $color,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error creating tag: ' . $e->getMessage());
        }
    }

    public function deleteUserAlbumTag(int $userId, int $tagId): array
    {
        try {
            $deleted = $this->albumTagRepository->delete($userId, $tagId);
            if ($deleted) {
                return $this->successResponse('Tag deleted successfully');
            }
            return $this->errorResponse('Tag not found or could not be deleted', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error deleting tag: ' . $e->getMessage());
        }
    }

    public function getAlbumTags(int $userId, int $albumId): array
    {
        try {
            $tags = $this->albumTagRepository->getAlbumTags($userId, $albumId);
            return $this->successResponse('Album tags retrieved successfully', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving album tags: ' . $e->getMessage());
        }
    }

    public function updateAlbumTags(int $userId, int $albumId, array $tagIds): array
    {
        try {
            $this->albumTagRepository->syncAlbumTags($userId, $albumId, $tagIds);
            return $this->successResponse('Album tags updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error updating album tags: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Notes
    // =========================================================================

    public function getAlbumNotes(int $userId, int $albumId): array
    {
        try {
            $notes = $this->albumNoteRepository->getByAlbum($userId, $albumId);
            return $this->successResponse('Album notes retrieved successfully', $notes);
        } catch (\Exception $e) {
            return $this->errorResponse('Error retrieving album notes: ' . $e->getMessage());
        }
    }

    /**
     * Añadir una nota a un álbum.
     *
     * Pasa por su use case desde el 2026-08-25. Antes hablaba directamente con
     * el repositorio, que era la razón por la que álbumes no tenía dónde poner
     * la guarda de privacidad del feed.
     */
    public function addAlbumNote(AddAlbumNoteCommand $command): array
    {
        $note = $this->addAlbumNoteUseCase->execute($command);

        return $this->successResponse('Album note added successfully', ['note' => $note]);
    }

    public function updateAlbumNote(
        int $noteId,
        int $userId,
        string $noteText,
        string $noteType = 'note',
        bool $isPrivate = true
    ): array {
        try {
            $updated = $this->albumNoteRepository->update($noteId, $userId, $noteText, $noteType, $isPrivate);
            if ($updated) {
                return $this->successResponse('Album note updated successfully');
            }
            return $this->errorResponse('Note not found or not authorized', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error updating album note: ' . $e->getMessage());
        }
    }

    public function deleteAlbumNote(int $noteId, int $userId): array
    {
        try {
            $deleted = $this->albumNoteRepository->delete($noteId, $userId);
            if ($deleted) {
                return $this->successResponse('Album note deleted successfully');
            }
            return $this->errorResponse('Note not found or not authorized', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Error deleting album note: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Catálogo de álbumes
    // =========================================================================
    // Las acciones siguen llamándose search_spotify_albums y get_spotify_album,
    // pero por dentro consultan el mirror de MusicBrainz y solo caen a Spotify
    // si el mirror no tiene nada. Renombrarlas obligaría a tocar store/albums.js
    // y las vistas, que no es de este plan; la deuda de nomenclatura queda
    // anotada en el plan «Mirror de Música».
    //
    // getSpotifyArtist, getSpotifyAlbumTracks y getSpotifyNewReleases siguen
    // yendo a Spotify de verdad: están fuera de alcance, así que la ficha de
    // álbum NO queda 100 % local al terminar este plan.

    public function searchSpotifyAlbums(array $data): array
    {
        try {
            $query = $data['query'] ?? '';
            $limit = isset($data['limit']) ? (int)$data['limit'] : 20;

            if (empty($query)) {
                return $this->errorResponse('Query parameter is required', 400);
            }

            $albums = $this->albumCatalog->search($query, $limit);
            return $this->successResponse('Albums found', [
                'albums' => $albums,
                'count'  => count($albums),
            ]);
        } catch (\Exception $e) {
            return $this->externalServiceError('Spotify');
        }
    }

    public function getSpotifyAlbum(array $data): array
    {
        try {
            // El parámetro se sigue llamando spotifyId por compatibilidad con
            // el frontend, pero hoy lo normal es que traiga un MBID.
            $albumId = $data['spotifyId'] ?? $data['spotify_id'] ?? $data['albumId'] ?? '';

            if (empty($albumId)) {
                return $this->errorResponse('spotifyId parameter is required', 400);
            }

            $album = $this->albumCatalog->findById($albumId);

            if ($album === null) {
                return $this->errorResponse('Album not found', 404);
            }

            return $this->successResponse('Album found', ['album' => $album]);
        } catch (\Exception $e) {
            return $this->externalServiceError('Spotify');
        }
    }

    public function getSpotifyArtist(array $data): array
    {
        try {
            $artistId = $data['artistId'] ?? $data['artist_id'] ?? '';

            if (empty($artistId)) {
                return $this->errorResponse('artistId parameter is required', 400);
            }

            $artist = $this->spotifyService->getArtist($artistId);

            if ($artist === null) {
                return $this->errorResponse('Artist not found', 404);
            }

            return $this->successResponse('Artist found', ['artist' => $artist]);
        } catch (\Exception $e) {
            return $this->externalServiceError('Spotify');
        }
    }

    /**
     * La lista de pistas de un álbum
     *
     * Enruta por la FORMA del id, igual que `FallbackAlbumCatalog::findById()`:
     * un MBID se sirve del mirror y un base62 de Spotify. Preguntarle a Spotify
     * por un MBID es lo que dejó sin pistas a todos los álbumes guardados desde
     * el mirror — devolvía `success` con la lista vacía, sin error visible.
     */
    public function getSpotifyAlbumTracks(array $data): array
    {
        try {
            $albumId = $data['spotifyId'] ?? $data['spotify_id'] ?? $data['albumId'] ?? '';

            if (empty($albumId)) {
                return $this->errorResponse('spotifyId parameter is required', 400);
            }

            if (AlbumId::looksLikeMbid((string) $albumId)) {
                return $this->mirrorAlbumTracks((string) $albumId);
            }

            $tracks = $this->spotifyService->getAlbumTracks($albumId);
            return $this->successResponse('Tracks found', [
                'tracks' => $tracks,
                'count'  => count($tracks),
            ]);
        } catch (\Exception $e) {
            return $this->externalServiceError('Spotify');
        }
    }

    /**
     * Pistas de un álbum del mirror, encolando el fetch si aún no están
     *
     * **No espera a MusicBrainz.** Su API tarda entre 4 y 45 segundos (medido),
     * así que la primera visita a una ficha devuelve la lista vacía y encola el
     * trabajo con `PostResponse::defer()`, que corre con la conexión ya cerrada
     * — el mismo mecanismo con el que `CoverService` baja las portadas. A la
     * siguiente visita las pistas están.
     *
     * Es una decisión consciente, no una carencia: hacerlo síncrono serían esos
     * 4-45 s de espera en blanco. Si algún día molesta, la salida es mover el
     * disparo al guardado del álbum, no bloquear aquí.
     *
     * @return array<string,mixed>
     */
    private function mirrorAlbumTracks(string $releaseGroupGid): array
    {
        $tracks = $this->mirrorCatalog->tracksFor($releaseGroupGid);

        if ($tracks === [] && !$this->albumTrackService->isSettled($releaseGroupGid)) {
            PostResponse::defer(function () use ($releaseGroupGid): void {
                $this->albumTrackService->fetchFor($releaseGroupGid);
            });
        }

        return $this->successResponse('Tracks found', [
            'tracks' => $tracks,
            'count'  => count($tracks),
        ]);
    }

    public function getSpotifyNewReleases(array $data): array
    {
        try {
            $limit = isset($data['limit']) ? (int)$data['limit'] : 20;

            $releases = $this->spotifyService->getNewReleases($limit);
            return $this->successResponse('New releases retrieved', [
                'albums' => $releases,
                'count'  => count($releases),
            ]);
        } catch (\Exception $e) {
            return $this->externalServiceError('Spotify');
        }
    }

    // =========================================================================
    // Last.fm stats
    // =========================================================================

    public function getListeningStats(GetLastFmStatsQuery $query): array
    {
        try {
            $result = $this->getListeningStatsUseCase->execute($query);
            return $this->successResponse('Listening stats retrieved.', $result);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->externalServiceError('Last.fm');
        }
    }
}
