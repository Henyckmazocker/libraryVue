<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\UseCases\Videos\AddVideoUseCase;
use App\Domain\UseCases\Videos\DeleteVideoUseCase;
use App\Domain\UseCases\Videos\UpdateVideoRatingUseCase;
use App\Domain\UseCases\Videos\UpdateVideoUserStatusesUseCase;
use App\Domain\UseCases\Videos\GetVideosUseCase;
use App\Domain\UseCases\Videos\GetVideoAllowedStatusesUseCase;
use App\Domain\UseCases\Videos\EditUserVideoUseCase;
use App\Domain\UseCases\Videos\GetTrendingVideosUseCase;
use App\Domain\UseCases\Videos\AddVideoNoteUseCase;
use App\Domain\UseCases\Videos\GetVideoNotesUseCase;
use App\Domain\UseCases\Videos\UpdateVideoNoteUseCase;
use App\Domain\UseCases\Videos\DeleteVideoNoteUseCase;
use App\Domain\DTO\Commands\AddVideoCommand;
use App\Domain\DTO\Commands\DeleteVideoCommand;
use App\Domain\DTO\Commands\UpdateVideoRatingCommand;
use App\Domain\DTO\Commands\UpdateVideoStatusesCommand;
use App\Domain\DTO\Commands\EditUserVideoCommand;
use App\Domain\DTO\Commands\AddVideoNoteCommand;
use App\Domain\DTO\Commands\UpdateVideoNoteCommand;
use App\Domain\DTO\Commands\DeleteVideoNoteCommand;
use App\Domain\Repository\Video\VideoTagRepositoryInterface;
use App\Domain\Services\YouTubeService;

class VideoController extends BaseController implements Contracts\VideoControllerInterface
{
    public function __construct(
        private readonly AddVideoUseCase $addVideoUseCase,
        private readonly DeleteVideoUseCase $deleteVideoUseCase,
        private readonly UpdateVideoRatingUseCase $updateVideoRatingUseCase,
        private readonly UpdateVideoUserStatusesUseCase $updateVideoUserStatusesUseCase,
        private readonly GetVideosUseCase $getVideosUseCase,
        private readonly GetVideoAllowedStatusesUseCase $getVideoAllowedStatusesUseCase,
        private readonly EditUserVideoUseCase $editUserVideoUseCase,
        private readonly GetTrendingVideosUseCase $getTrendingVideosUseCase,
        private readonly AddVideoNoteUseCase $addVideoNoteUseCase,
        private readonly GetVideoNotesUseCase $getVideoNotesUseCase,
        private readonly UpdateVideoNoteUseCase $updateVideoNoteUseCase,
        private readonly DeleteVideoNoteUseCase $deleteVideoNoteUseCase,
        private readonly VideoTagRepositoryInterface $videoTagRepository,
        private readonly YouTubeService $youTubeService
    ) {}

    // =========================================================================
    // Library CRUD
    // =========================================================================

    public function addVideo(AddVideoCommand $command): array
    {
        $video = $this->addVideoUseCase->execute($command);
        return $this->successResponse('Video added: ' . $video->getTitle(), $video->toArray(), 201);
    }

    public function deleteVideo(DeleteVideoCommand $command): array
    {
        $this->deleteVideoUseCase->execute($command);
        return $this->successResponse('Video removed from your library.');
    }

    public function updateVideoRating(UpdateVideoRatingCommand $command): array
    {
        $this->updateVideoRatingUseCase->execute($command);
        return $this->successResponse('Video rating updated successfully.');
    }

    public function updateVideoUserStatuses(UpdateVideoStatusesCommand $command): array
    {
        $this->updateVideoUserStatusesUseCase->execute($command);
        return $this->successResponse('Video statuses updated successfully.');
    }

    public function editUserVideo(EditUserVideoCommand $command): array
    {
        $this->editUserVideoUseCase->execute($command);
        return $this->successResponse('Video updated successfully.');
    }

    public function getVideoAllowedStatuses(): array
    {
        $statuses = $this->getVideoAllowedStatusesUseCase->execute([]);
        return $this->successResponse('Allowed video statuses retrieved.', $statuses);
    }

    public function getVideos(array $params): array
    {
        $videos = $this->getVideosUseCase->execute($params);
        $data   = array_map(fn($v) => $v->toArray(), $videos);
        return $this->successResponse('Videos retrieved.', $data);
    }

    public function getTrendingVideos(array $params): array
    {
        $trending = $this->getTrendingVideosUseCase->execute($params);
        $data     = array_map(fn($v) => $v->toArray(), $trending);
        return $this->successResponse('Trending videos retrieved.', $data);
    }

    // =========================================================================
    // YouTube Search (external API proxy)
    // =========================================================================

    public function searchVideos(array $params): array
    {
        try {
            $query      = $params['query'] ?? $params['q'] ?? '';
            $maxResults = (int)($params['maxResults'] ?? 10);

            if (empty($query)) {
                return $this->errorResponse('Search query is required', 400);
            }

            // data es un MAPA, no la lista pelada de antes: el aviso de degradacion
            // necesita viajar al lado de los resultados, igual que en libros y juegos.
            $result = $this->youTubeService->searchVideosResilient($query, $maxResults);
            $videos = $result['data'];

            return $this->successResponse('YouTube search results.', [
                'videos' => $videos,
                'count' => count($videos),
                'stale' => $result['stale'],
                'cached_at' => $result['cached_at'] ? date('c', $result['cached_at']) : null
            ]);
        } catch (\Exception $e) {
            return $this->externalServiceError('YouTube');
        }
    }

    /**
     * Ficha completa de un vídeo a partir de su id de YouTube.
     *
     * Es el equivalente de get_movie_details_omdb, get_igdb_game_details y
     * get_spotify_album en los otros medios: sin ella, la ficha de un vídeo que
     * no está en tu biblioteca no se puede pintar, porque la búsqueda solo
     * consulta por texto.
     */
    public function getVideoDetails(array $params): array
    {
        try {
            $youtubeId = trim($params['youtubeId'] ?? $params['youtube_id'] ?? $params['videoId'] ?? '');

            if (empty($youtubeId)) {
                return $this->errorResponse('youtubeId is required', 400);
            }

            $video = $this->youTubeService->getVideoDetails($youtubeId);

            if ($video === null) {
                return $this->errorResponse('Video not found', 404);
            }

            return $this->successResponse('Video details retrieved', ['video' => $video]);
        } catch (\Exception $e) {
            return $this->externalServiceError('YouTube');
        }
    }

    // =========================================================================
    // Tags
    // =========================================================================

    public function getUserVideoTags(int $userId): array
    {
        try {
            $tags = $this->videoTagRepository->findByUser($userId);
            return $this->successResponse('Tags retrieved successfully', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get video tags: ' . $e->getMessage());
        }
    }

    public function createUserVideoTag(int $userId, string $name, string $color): array
    {
        try {
            $tagId = $this->videoTagRepository->create($userId, $name, $color);
            return $this->successResponse('Tag created successfully', ['id' => $tagId, 'name' => $name, 'color' => $color]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create video tag: ' . $e->getMessage());
        }
    }

    public function deleteUserVideoTag(int $userId, int $tagId): array
    {
        try {
            $deleted = $this->videoTagRepository->delete($userId, $tagId);
            return $deleted
                ? $this->successResponse('Tag deleted successfully')
                : $this->errorResponse('Tag not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete video tag: ' . $e->getMessage());
        }
    }

    public function getVideoTags(int $userId, int $videoId): array
    {
        try {
            $tags = $this->videoTagRepository->getVideoTags($userId, $videoId);
            return $this->successResponse('Video tags retrieved', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get video tags: ' . $e->getMessage());
        }
    }

    public function updateVideoTags(int $userId, int $videoId, array $tagIds): array
    {
        try {
            $this->videoTagRepository->syncVideoTags($userId, $videoId, $tagIds);
            return $this->successResponse('Video tags updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update video tags: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Notes
    // =========================================================================

    public function getVideoNotes(int $userId, string $youtubeId): array
    {
        try {
            $notes = $this->getVideoNotesUseCase->execute([
                'userId'    => $userId,
                'youtubeId' => $youtubeId,
            ]);
            return $this->successResponse('Video notes retrieved', $notes);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get video notes: ' . $e->getMessage());
        }
    }

    /**
     * Añadir una nota a un vídeo.
     *
     * Recibe el comando entero desde el 2026-08-25. Antes le llegaban cuatro
     * argumentos sueltos y **`isPrivate` no era uno de ellos**, así que toda
     * nota de vídeo nacía privada —el default del comando— y **no había forma
     * de crear una pública desde la API**. Se descubrió al verificar que las
     * cinco entidades emiten evento de feed: vídeos guardaba la nota y no
     * emitía nunca.
     */
    public function addVideoNote(AddVideoNoteCommand $command): array
    {
        try {
            $noteId = $this->addVideoNoteUseCase->execute($command);
            return $this->successResponse('Note added successfully', ['noteId' => $noteId]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to add video note: ' . $e->getMessage());
        }
    }

    public function updateVideoNote(int $noteId, int $userId, string $noteText, string $noteType): array
    {
        try {
            $command = new UpdateVideoNoteCommand(
                noteId:   $noteId,
                userId:   $userId,
                noteText: $noteText,
                noteType: $noteType
            );
            $this->updateVideoNoteUseCase->execute($command);
            return $this->successResponse('Note updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update video note: ' . $e->getMessage());
        }
    }

    public function deleteVideoNote(int $noteId, int $userId): array
    {
        try {
            $command = new DeleteVideoNoteCommand(noteId: $noteId, userId: $userId);
            $deleted = $this->deleteVideoNoteUseCase->execute($command);
            return $deleted
                ? $this->successResponse('Note deleted successfully')
                : $this->errorResponse('Note not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete video note: ' . $e->getMessage());
        }
    }
}
