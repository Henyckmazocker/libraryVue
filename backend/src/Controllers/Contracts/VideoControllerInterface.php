<?php

declare(strict_types=1);

namespace App\Controllers\Contracts;

use App\Domain\DTO\Commands\AddVideoCommand;
use App\Domain\DTO\Commands\DeleteVideoCommand;
use App\Domain\DTO\Commands\UpdateVideoRatingCommand;
use App\Domain\DTO\Commands\UpdateVideoStatusesCommand;
use App\Domain\DTO\Commands\EditUserVideoCommand;

/**
 * Interface for VideoController
 * Defines contract for video-related operations
 */
interface VideoControllerInterface
{
    public function addVideo(AddVideoCommand $command): array;

    public function deleteVideo(DeleteVideoCommand $command): array;

    public function updateVideoRating(UpdateVideoRatingCommand $command): array;

    public function updateVideoUserStatuses(UpdateVideoStatusesCommand $command): array;

    public function editUserVideo(EditUserVideoCommand $command): array;

    public function getVideoAllowedStatuses(): array;

    public function getVideos(array $params): array;

    public function getTrendingVideos(array $params): array;

    public function getUserVideoTags(int $userId): array;

    public function createUserVideoTag(int $userId, string $name, string $color): array;

    public function deleteUserVideoTag(int $userId, int $tagId): array;

    public function getVideoTags(int $userId, int $videoId): array;

    public function updateVideoTags(int $userId, int $videoId, array $tagIds): array;

    public function getVideoNotes(int $userId, string $youtubeId): array;

    public function addVideoNote(int $userId, string $youtubeId, string $noteText, string $noteType): array;

    public function updateVideoNote(int $noteId, int $userId, string $noteText, string $noteType): array;

    public function deleteVideoNote(int $noteId, int $userId): array;

    public function searchVideos(array $params): array;
}
