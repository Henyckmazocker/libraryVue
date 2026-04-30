<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

use App\Domain\Model\ValueObjects\SpotifyId;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;

/**
 * Command DTO for adding an album to user's library
 */
final readonly class AddAlbumCommand
{
    /**
     * @param int $id Internal DB ID (0 when the album does not exist yet)
     * @param SpotifyId $spotifyId Spotify identifier
     * @param string $title Album title
     * @param string $artist Artist name
     * @param int $userId User ID adding the album
     * @param array $statuses User statuses for this album
     * @param string|null $artistId Spotify artist ID (used to retrieve genres)
     * @param string|null $releaseDate Release date (variable format from Spotify)
     * @param string|null $releaseDatePrecision Precision: "day", "month", or "year"
     * @param string|null $coverUrl Cover image URL (largest size, 640px)
     * @param array|null $genres Array of Genre VOs (fetched from Artist endpoint)
     * @param string|null $label Record label
     * @param int|null $totalTracks Total number of tracks
     * @param string|null $albumType "album", "single", or "compilation"
     * @param int|null $durationMs Total duration in milliseconds (sum of track durations)
     * @param int|null $popularity Spotify popularity score (0–100)
     * @param string|null $externalUrl Spotify Web Player URL
     * @param string|null $upc Universal Product Code (barcode)
     * @param Rating|null $userRating User's personal rating
     * @param string|null $personalNotes User's notes
     * @param int|null $listenCount Number of times listened
     * @param string|null $favoriteTrack User's favourite track name
     */
    public function __construct(
        public int $id,
        public SpotifyId $spotifyId,
        public string $title,
        public string $artist,
        public int $userId,
        public array $statuses = [],
        public ?string $artistId = null,
        public ?string $releaseDate = null,
        public ?string $releaseDatePrecision = null,
        public ?string $coverUrl = null,
        public ?array $genres = null,
        public ?string $label = null,
        public ?int $totalTracks = null,
        public ?string $albumType = null,
        public ?int $durationMs = null,
        public ?int $popularity = null,
        public ?string $externalUrl = null,
        public ?string $upc = null,
        public ?Rating $userRating = null,
        public ?string $personalNotes = null,
        public ?int $listenCount = null,
        public ?string $favoriteTrack = null,
        public ?int $ownershipFormatId = null
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $spotifyIdStr = $data['spotify_id'] ?? $data['spotifyId'] ?? null;
        if (empty($spotifyIdStr)) {
            throw new \InvalidArgumentException('Spotify ID is required.');
        }

        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Album title is required.');
        }

        $spotifyId = SpotifyId::fromString($spotifyIdStr);

        $userRating = null;
        $ratingValue = $data['userRating'] ?? $data['user_rating'] ?? null;
        if ($ratingValue !== null && is_numeric($ratingValue)) {
            $userRating = Rating::fromNullableFloat((float)$ratingValue);
        }

        $genres = null;
        $genresRaw = $data['genres'] ?? null;
        if ($genresRaw !== null && is_array($genresRaw)) {
            $genres = array_map(fn($g) => Genre::fromString($g), $genresRaw);
        }

        $popularity = isset($data['popularity']) && is_numeric($data['popularity'])
            ? (int)$data['popularity']
            : null;

        $totalTracks = isset($data['total_tracks']) && is_numeric($data['total_tracks'])
            ? (int)$data['total_tracks']
            : (isset($data['totalTracks']) && is_numeric($data['totalTracks']) ? (int)$data['totalTracks'] : null);

        $durationMs = isset($data['duration_ms']) && is_numeric($data['duration_ms'])
            ? (int)$data['duration_ms']
            : (isset($data['durationMs']) && is_numeric($data['durationMs']) ? (int)$data['durationMs'] : null);

        $listenCount = isset($data['listen_count']) && is_numeric($data['listen_count'])
            ? (int)$data['listen_count']
            : (isset($data['listenCount']) && is_numeric($data['listenCount']) ? (int)$data['listenCount'] : null);

        return new self(
            id: (int)($data['id'] ?? 0),
            spotifyId: $spotifyId,
            title: $data['title'],
            artist: $data['artist'] ?? '',
            userId: $userId,
            statuses: $data['statuses'] ?? $data['userStatuses'] ?? [],
            artistId: $data['artist_id'] ?? $data['artistId'] ?? null,
            releaseDate: $data['release_date'] ?? $data['releaseDate'] ?? null,
            releaseDatePrecision: $data['release_date_precision'] ?? $data['releaseDatePrecision'] ?? null,
            coverUrl: $data['cover_url'] ?? $data['coverUrl'] ?? null,
            genres: $genres,
            label: $data['label'] ?? null,
            totalTracks: $totalTracks,
            albumType: $data['album_type'] ?? $data['albumType'] ?? null,
            durationMs: $durationMs,
            popularity: $popularity,
            externalUrl: $data['external_url'] ?? $data['externalUrl'] ?? null,
            upc: $data['upc'] ?? null,
            userRating: $userRating,
            personalNotes: $data['personal_notes'] ?? $data['personalNotes'] ?? null,
            listenCount: $listenCount,
            favoriteTrack: $data['favorite_track'] ?? $data['favoriteTrack'] ?? null,
            ownershipFormatId: isset($data['ownership_format_id']) ? (int)$data['ownership_format_id'] : (isset($data['ownershipFormatId']) ? (int)$data['ownershipFormatId'] : null)
        );
    }

    /**
     * Convert command to an array compatible with Album::fromArray()
     * Used by AddAlbumUseCase to construct the Album entity before persisting.
     */
    public function toAlbumArray(): array
    {
        return [
            'id'                     => $this->id,
            'spotify_id'             => $this->spotifyId->toString(),
            'title'                  => $this->title,
            'artist'                 => $this->artist,
            'artist_id'              => $this->artistId,
            'release_date'           => $this->releaseDate,
            'release_date_precision' => $this->releaseDatePrecision,
            'cover_url'              => $this->coverUrl,
            'genres'                 => $this->genres !== null
                ? array_map(fn($g) => $g->toString(), $this->genres)
                : null,
            'label'                  => $this->label,
            'total_tracks'           => $this->totalTracks,
            'album_type'             => $this->albumType,
            'duration_ms'            => $this->durationMs,
            'popularity'             => $this->popularity,
            'external_url'           => $this->externalUrl,
            'upc'                    => $this->upc,
            'user_rating'            => $this->userRating?->toFloat(),
            'personal_notes'         => $this->personalNotes,
            'listen_count'           => $this->listenCount,
            'favorite_track'         => $this->favoriteTrack,
            'userStatuses'           => $this->statuses,
        ];
    }
}
