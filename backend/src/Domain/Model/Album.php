<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Model\ValueObjects\AlbumId;
use App\Domain\Model\ValueObjects\SpotifyId;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;

class Album
{
    private int $id;
    /**
     * La identidad del álbum: un MBID de MusicBrainz, o un base62 de Spotify
     * para lo guardado antes de que existiera el mirror.
     */
    private AlbumId $albumId;

    /**
     * El id de Spotify, cuando lo hay
     *
     * Ya no es la identidad —eso era atar la biblioteca a un proveedor
     * privado—, sino el puente que permite reconocer un álbum guardado antes
     * del mirror y no duplicarlo al volver a guardarlo por MBID.
     */
    private ?SpotifyId $spotifyId;

    /**
     * De qué catálogo salió la ficha
     *
     * Solo importa cuando vale 'spotify': esas filas caducan, porque los
     * términos de Spotify prohíben guardar su contenido indefinidamente. Las de
     * MusicBrainz son CC0 y no caducan nunca.
     */
    private string $catalogSource;
    private string $title;
    private string $artist;
    private ?string $artistId;
    private ?string $releaseDate;
    private ?string $releaseDatePrecision;
    private ?string $coverUrl;
    /** @var Genre[]|null */
    private ?array $genres;
    private ?string $label;
    private ?int $totalTracks;
    private ?string $albumType;
    private ?int $durationMs;
    private ?int $popularity;
    private ?string $externalUrl;
    private ?string $upc;
    private Timestamp $addedTimestamp;
    private ?Rating $userRating;
    private array $userStatuses;
    private array $allowedStatuses;
    private ?array $tags;
    private ?array $allowedTags;
    private ?string $personalNotes;
    private ?int $listenCount;
    private ?string $favoriteTrack;
    private ?string $dateStarted;
    private ?string $dateFinished;
    private ?string $completedAt;
    private ?array $ownershipFormat; // Formato de posesión (id, value, label)

    public function __construct(
        int $id,
        AlbumId $albumId,
        ?SpotifyId $spotifyId,
        string $catalogSource,
        string $title,
        string $artist,
        ?string $artistId,
        ?string $releaseDate,
        ?string $releaseDatePrecision,
        ?string $coverUrl,
        ?array $genres,
        ?string $label,
        ?int $totalTracks,
        ?string $albumType,
        ?int $durationMs,
        ?int $popularity,
        ?string $externalUrl,
        ?string $upc,
        Timestamp $addedTimestamp,
        ?Rating $userRating,
        array $userStatuses,
        array $allowedStatuses = [],
        ?array $tags = null,
        ?array $allowedTags = null,
        ?string $personalNotes = null,
        ?int $listenCount = null,
        ?string $favoriteTrack = null,
        ?string $dateStarted = null,
        ?string $dateFinished = null,
        ?string $completedAt = null,
        ?array $ownershipFormat = null
    ) {
        if (empty($title)) {
            throw new InvalidArgumentException('Title cannot be empty.');
        }
        if ($popularity !== null && ($popularity < 0 || $popularity > 100)) {
            throw new InvalidArgumentException('Popularity must be between 0 and 100.');
        }
        if ($listenCount !== null && $listenCount < 0) {
            throw new InvalidArgumentException('Listen count must be non-negative.');
        }

        $this->id = $id;
        $this->albumId   = $albumId;
        $this->catalogSource = $catalogSource;
        $this->spotifyId = $spotifyId;
        $this->title = $title;
        $this->artist = $artist;
        $this->artistId = $artistId;
        $this->releaseDate = $releaseDate;
        $this->releaseDatePrecision = $releaseDatePrecision;
        $this->coverUrl = $coverUrl;
        $this->genres = $genres;
        $this->label = $label;
        $this->totalTracks = $totalTracks;
        $this->albumType = $albumType;
        $this->durationMs = $durationMs;
        $this->popularity = $popularity;
        $this->externalUrl = $externalUrl;
        $this->upc = $upc;
        $this->addedTimestamp = $addedTimestamp;
        $this->userRating = $userRating;
        $this->userStatuses = $userStatuses;
        $this->allowedStatuses = $allowedStatuses;
        $this->tags = $tags;
        $this->allowedTags = $allowedTags;
        $this->personalNotes = $personalNotes;
        $this->listenCount = $listenCount ?? 0;
        $this->favoriteTrack = $favoriteTrack;
        $this->dateStarted = $dateStarted;
        $this->dateFinished = $dateFinished;
        $this->completedAt = $completedAt;
        $this->ownershipFormat = $ownershipFormat;
    }

    public static function fromArray(array $data): self
    {
        $identity = $data['mb_release_group_gid'] ?? $data['mbReleaseGroupGid']
            ?? $data['album_id'] ?? $data['albumId']
            ?? $data['spotify_id'] ?? $data['spotifyId'] ?? null;

        if (empty($identity)) {
            throw new InvalidArgumentException(
                'An album needs an identity: a MusicBrainz MBID or a Spotify ID.'
            );
        }
        if (empty($data['title'])) {
            throw new InvalidArgumentException('Title is required for an album.');
        }
        if (empty($data['userStatuses']) || !is_array($data['userStatuses'])) {
            throw new InvalidArgumentException('User statuses are required and must be an array.');
        }

        $albumId = AlbumId::fromString((string) $identity);
        $catalogSource = $data['catalog_source'] ?? ($albumId->isMusicBrainz() ? 'musicbrainz' : 'spotify');

        // El base62 solo se conserva si de verdad lo es: cuando la identidad
        // llega como MBID, spotify_id se queda a NULL y el UNIQUE de la columna
        // admite tantos NULL como haga falta.
        $spotifyIdStr = $data['spotify_id'] ?? $data['spotifyId'] ?? null;
        $spotifyId = !empty($spotifyIdStr) && !$albumId->isMusicBrainz()
            ? SpotifyId::fromNullableString((string) $spotifyIdStr)
            : null;

        $userRating = null;
        $ratingValue = $data['user_rating'] ?? $data['userRating'] ?? null;
        if ($ratingValue !== null && is_numeric($ratingValue)) {
            $userRating = Rating::fromNullableFloat((float)$ratingValue);
        }

        $addedTimestamp = isset($data['addedTimestamp'])
            ? Timestamp::fromUnixTimestamp($data['addedTimestamp'])
            : Timestamp::now();

        $genres = null;
        $genresRaw = $data['genres'] ?? null;
        if ($genresRaw !== null) {
            if (is_string($genresRaw)) {
                $genresRaw = json_decode($genresRaw, true) ?? [];
            }
            if (is_array($genresRaw)) {
                $genres = array_map(fn($g) => Genre::fromString($g), $genresRaw);
            }
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
            albumId: $albumId,
            spotifyId: $spotifyId,
            catalogSource: $catalogSource,
            title: $data['title'],
            artist: $data['artist'] ?? '',
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
            addedTimestamp: $addedTimestamp,
            userRating: $userRating,
            userStatuses: $data['userStatuses'],
            allowedStatuses: $data['allowedStatuses'] ?? [],
            tags: $data['tags'] ?? null,
            allowedTags: $data['allowedTags'] ?? null,
            personalNotes: $data['personal_notes'] ?? $data['personalNotes'] ?? null,
            listenCount: $listenCount,
            favoriteTrack: $data['favorite_track'] ?? $data['favoriteTrack'] ?? null,
            dateStarted: $data['date_started'] ?? $data['dateStarted'] ?? null,
            dateFinished: $data['date_finished'] ?? $data['dateFinished'] ?? null,
            completedAt: $data['completed_at'] ?? $data['completedAt'] ?? null,
            ownershipFormat: $data['ownership_format'] ?? $data['ownershipFormat'] ?? null
        );
    }

    // --- Getters ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getAlbumId(): AlbumId
    {
        return $this->albumId;
    }

    public function getSpotifyId(): ?SpotifyId
    {
        return $this->spotifyId;
    }

    /** El MBID, o null si este álbum se guardó antes del mirror */
    /** 'musicbrainz' | 'spotify': de qué catálogo salió esta ficha */
    public function getCatalogSource(): string
    {
        return $this->catalogSource;
    }

    public function getMbReleaseGroupGid(): ?string
    {
        return $this->albumId->isMusicBrainz() ? $this->albumId->toString() : null;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getArtist(): string
    {
        return $this->artist;
    }

    public function getArtistId(): ?string
    {
        return $this->artistId;
    }

    public function getReleaseDate(): ?string
    {
        return $this->releaseDate;
    }

    public function getReleaseDatePrecision(): ?string
    {
        return $this->releaseDatePrecision;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function getGenres(): ?array
    {
        return $this->genres;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getTotalTracks(): ?int
    {
        return $this->totalTracks;
    }

    public function getAlbumType(): ?string
    {
        return $this->albumType;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function getPopularity(): ?int
    {
        return $this->popularity;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function getUpc(): ?string
    {
        return $this->upc;
    }

    public function getAddedTimestamp(): Timestamp
    {
        return $this->addedTimestamp;
    }

    public function getUserRating(): ?Rating
    {
        return $this->userRating;
    }

    public function getUserStatuses(): array
    {
        return $this->userStatuses;
    }

    public function getAllowedStatuses(): array
    {
        return $this->allowedStatuses;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function getAllowedTags(): ?array
    {
        return $this->allowedTags;
    }

    public function getPersonalNotes(): ?string
    {
        return $this->personalNotes;
    }

    public function getListenCount(): ?int
    {
        return $this->listenCount;
    }

    public function getFavoriteTrack(): ?string
    {
        return $this->favoriteTrack;
    }

    public function getDateStarted(): ?string
    {
        return $this->dateStarted;
    }

    public function getDateFinished(): ?string
    {
        return $this->dateFinished;
    }

    public function getCompletedAt(): ?string
    {
        return $this->completedAt;
    }

    // --- Utility methods ---

    public function hasStatus(string $status): bool
    {
        return in_array($status, $this->userStatuses, true);
    }

    public function isListened(): bool
    {
        return $this->hasStatus('listened') || $this->hasStatus('re-listening');
    }

    public function isListening(): bool
    {
        return $this->hasStatus('listening');
    }

    public function isInWishlist(): bool
    {
        return $this->hasStatus('in-wishlist');
    }

    public function isFavorite(): bool
    {
        return $this->hasStatus('favorite');
    }

    public function getArtistName(): string
    {
        return $this->artist;
    }

    public function getFormattedDuration(): string
    {
        if ($this->durationMs === null || $this->durationMs <= 0) {
            return '0:00';
        }

        $totalSeconds = (int)round($this->durationMs / 1000);
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function hasGenre(string $genreName): bool
    {
        if ($this->genres === null) {
            return false;
        }

        foreach ($this->genres as $genre) {
            if (strcasecmp($genre->toString(), $genreName) === 0) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'id'                     => $this->id,
            // El frontend usa spotify_id como clave e id de ruta, así que se
            // le sigue dando ese nombre — con la identidad real dentro, sea
            // MBID o base62. Renombrarlo es deuda anotada en el plan.
            'spotify_id'             => $this->albumId->toString(),
            'spotifyId'              => $this->albumId->toString(),
            'album_id'               => $this->albumId->toString(),
            'mb_release_group_gid'   => $this->getMbReleaseGroupGid(),
            'catalog_source'         => $this->catalogSource,
            'title'                  => $this->title,
            'name'                   => $this->title, // alias for compatibility
            'artist'                 => $this->artist,
            'artist_id'              => $this->artistId,
            'artistId'               => $this->artistId,
            'release_date'           => $this->releaseDate,
            'releaseDate'            => $this->releaseDate,
            'release_date_precision' => $this->releaseDatePrecision,
            'releaseDatePrecision'   => $this->releaseDatePrecision,
            'cover_url'              => $this->coverUrl,
            'coverUrl'               => $this->coverUrl,
            'genres'                 => $this->genres !== null
                ? array_map(fn($g) => $g->toString(), $this->genres)
                : null,
            'label'                  => $this->label,
            'total_tracks'           => $this->totalTracks,
            'totalTracks'            => $this->totalTracks,
            'album_type'             => $this->albumType,
            'albumType'              => $this->albumType,
            'duration_ms'            => $this->durationMs,
            'durationMs'             => $this->durationMs,
            'duration'               => $this->getFormattedDuration(),
            'popularity'             => $this->popularity,
            'external_url'           => $this->externalUrl,
            'externalUrl'            => $this->externalUrl,
            'upc'                    => $this->upc,
            'addedTimestamp'         => $this->addedTimestamp->toUnixTimestamp(),
            'user_rating'            => $this->userRating?->toFloat(),
            'userRating'             => $this->userRating?->toFloat(),
            'userStatuses'           => $this->userStatuses,
            'allowedStatuses'        => $this->allowedStatuses,
            'tags'                   => $this->tags,
            'allowedTags'            => $this->allowedTags,
            'personal_notes'         => $this->personalNotes,
            'personalNotes'          => $this->personalNotes,
            'notes'                  => $this->personalNotes, // alias
            'listen_count'           => $this->listenCount,
            'listenCount'            => $this->listenCount,
            'favorite_track'         => $this->favoriteTrack,
            'favoriteTrack'          => $this->favoriteTrack,
            'date_started'           => $this->dateStarted,
            'dateStarted'            => $this->dateStarted,
            'date_finished'          => $this->dateFinished,
            'dateFinished'           => $this->dateFinished,
            'completed_at'           => $this->completedAt,
            'completedAt'            => $this->completedAt,
            'ownership_format'       => $this->ownershipFormat,
            'ownershipFormat'        => $this->ownershipFormat,
            'ownership_format_value' => $this->ownershipFormat['value'] ?? null,
            'ownership_format_label' => $this->ownershipFormat['label'] ?? null,
        ];
    }

    public function getOwnershipFormat(): ?array { return $this->ownershipFormat; }
    public function setOwnershipFormat(?array $ownershipFormat): void { $this->ownershipFormat = $ownershipFormat; }
}
