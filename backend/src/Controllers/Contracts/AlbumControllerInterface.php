<?php

declare(strict_types=1);

namespace App\Controllers\Contracts;

use App\Domain\DTO\Commands\AddAlbumCommand;
use App\Domain\DTO\Commands\DeleteAlbumCommand;
use App\Domain\DTO\Commands\UpdateAlbumRatingCommand;
use App\Domain\DTO\Commands\UpdateAlbumStatusesCommand;
use App\Domain\DTO\Commands\EditUserAlbumCommand;
use App\Domain\DTO\Queries\GetTrendingAlbumsQuery;

/**
 * Interface for AlbumController
 * Defines contract for album-related operations
 */
interface AlbumControllerInterface
{
    public function addAlbum(AddAlbumCommand $command): array;

    public function deleteAlbum(DeleteAlbumCommand $command): array;

    public function updateAlbumRating(UpdateAlbumRatingCommand $command): array;

    public function updateAlbumUserStatuses(UpdateAlbumStatusesCommand $command): array;

    public function editUserAlbum(EditUserAlbumCommand $command): array;

    public function getAlbumAllowedStatuses(): array;

    public function getAlbums(array $params): array;

    public function getTrendingAlbums(GetTrendingAlbumsQuery $query): array;

    public function getUserAlbumTags(int $userId): array;

    public function createUserAlbumTag(int $userId, string $name, string $color): array;

    public function deleteUserAlbumTag(int $userId, int $tagId): array;

    public function getAlbumTags(int $userId, int $albumId): array;

    public function updateAlbumTags(int $userId, int $albumId, array $tagIds): array;

    public function getAlbumNotes(int $userId, int $albumId): array;

    public function addAlbumNote(int $userId, int $albumId, string $noteText, string $noteType): array;

    public function updateAlbumNote(int $noteId, int $userId, string $noteText, string $noteType): array;

    public function deleteAlbumNote(int $noteId, int $userId): array;

    public function searchSpotifyAlbums(array $data): array;

    public function getSpotifyAlbum(array $data): array;

    public function getSpotifyArtist(array $data): array;

    public function getSpotifyAlbumTracks(array $data): array;

    public function getSpotifyNewReleases(array $data): array;
}
