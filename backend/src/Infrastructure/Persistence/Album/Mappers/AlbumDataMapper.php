<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Album\Mappers;

use App\Domain\Model\Album;
use App\Domain\Model\ValueObjects\SpotifyId;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

/**
 * Maps between database rows and Album domain entities
 */
class AlbumDataMapper
{
    use HydrationHelpersTrait;

    /**
     * Convert database row to Album entity
     *
     * @param array $row Database row with snake_case columns
     * @return Album
     */
    public function toDomain(array $row): Album
    {
        $spotifyId = SpotifyId::fromString($this->extractString($row, 'spotify_id'));

        $userRating = Rating::fromNullableFloat(
            $this->extractFloat($row, 'user_rating', null)
        );

        // Parse genres JSON array
        $genresData = $this->extractJson($row, 'genres', []);
        $genres = null;
        if (is_array($genresData) && !empty($genresData)) {
            $genres = array_values(array_filter(
                array_map(
                    fn($g) => is_string($g) ? Genre::fromString($g) : null,
                    $genresData
                )
            ));
        }

        $addedAt = isset($row['user_added_at'])
            ? Timestamp::fromString($this->extractString($row, 'user_added_at'))
            : Timestamp::now();

        // User statuses as array of strings
        $userStatuses = [];
        if (array_key_exists('user_statuses', $row) && $row['user_statuses'] !== null) {
            if (is_array($row['user_statuses'])) {
                $userStatuses = $row['user_statuses'];
            } elseif (is_string($row['user_statuses']) && $row['user_statuses'] !== '') {
                $userStatuses = explode(', ', $row['user_statuses']);
            }
        }

        return new Album(
            id: $this->extractRequiredInt($row, 'id'),
            spotifyId: $spotifyId,
            title: $this->extractString($row, 'title'),
            artist: $this->extractString($row, 'artist'),
            artistId: $this->extractString($row, 'artist_id', null),
            releaseDate: $this->extractString($row, 'release_date', null),
            releaseDatePrecision: $this->extractString($row, 'release_date_precision', null),
            coverUrl: $this->extractString($row, 'cover_url', null),
            genres: $genres,
            label: $this->extractString($row, 'label', null),
            totalTracks: $this->extractInt($row, 'total_tracks', null),
            albumType: $this->extractString($row, 'album_type', null),
            durationMs: $this->extractInt($row, 'duration_ms', null),
            popularity: $this->extractInt($row, 'popularity', null),
            externalUrl: $this->extractString($row, 'external_url', null),
            upc: $this->extractString($row, 'upc', null),
            addedTimestamp: $addedAt,
            userRating: $userRating,
            userStatuses: $userStatuses,
            allowedStatuses: [],
            tags: null,
            allowedTags: null,
            personalNotes: $this->extractString($row, 'personal_notes', null),
            listenCount: $this->extractInt($row, 'listen_count', null),
            favoriteTrack: $this->extractString($row, 'favorite_track', null),
            dateStarted: $this->extractString($row, 'date_started', null),
            dateFinished: $this->extractString($row, 'date_finished', null),
            completedAt: $this->extractString($row, 'completed_at', null),
            ownershipFormat: $this->buildOwnershipFormat($row)
        );
    }

    /**
     * Convert a collection of database rows to Album entities
     *
     * @param array $rows
     * @return Album[]
     */
    public function toDomainCollection(array $rows): array
    {
        return array_map([$this, 'toDomain'], $rows);
    }

    /**
     * Convert Album entity to database persistence array (for INSERT/UPDATE)
     *
     * @param Album $album
     * @return array
     */
    public function toPersistence(Album $album): array
    {
        $genres = null;
        if ($album->getGenres() !== null) {
            $genres = json_encode(
                array_map(fn(Genre $g) => $g->toString(), $album->getGenres()),
                JSON_THROW_ON_ERROR
            );
        }

        return [
            'id'                     => $album->getId(),
            'spotify_id'             => $album->getSpotifyId()->toString(),
            'title'                  => $album->getTitle(),
            'artist'                 => $album->getArtist(),
            'artist_id'              => $album->getArtistId(),
            'release_date'           => $album->getReleaseDate(),
            'release_date_precision' => $album->getReleaseDatePrecision(),
            'cover_url'              => $album->getCoverUrl(),
            'genres'                 => $genres,
            'label'                  => $album->getLabel(),
            'total_tracks'           => $album->getTotalTracks(),
            'album_type'             => $album->getAlbumType(),
            'duration_ms'            => $album->getDurationMs(),
            'popularity'             => $album->getPopularity(),
            'external_url'           => $album->getExternalUrl(),
            'upc'                    => $album->getUpc(),
        ];
    }
}
