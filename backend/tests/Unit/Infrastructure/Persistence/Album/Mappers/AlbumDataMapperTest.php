<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Album\Mappers;

use App\Infrastructure\Persistence\Album\Mappers\AlbumDataMapper;
use App\Domain\Model\Album;
use App\Domain\Model\ValueObjects\SpotifyId;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AlbumDataMapperTest extends TestCase
{
    private AlbumDataMapper $mapper;
    private const SPOTIFY_ID = '4aawyAB9vmqN3uQ7FjRGTy';

    protected function setUp(): void
    {
        $this->mapper = new AlbumDataMapper();
    }

    private function fullDbRow(): array
    {
        return [
            'id' => 1,
            'spotify_id' => self::SPOTIFY_ID,
            'title' => 'OK Computer',
            'artist' => 'Radiohead',
            'artist_id' => 'artist123456789012',
            'release_date' => '1997-05-21',
            'release_date_precision' => 'day',
            'cover_url' => 'https://example.com/cover.jpg',
            'genres' => json_encode(['Alternative Rock', 'Art Rock']),
            'label' => 'Parlophone',
            'total_tracks' => 12,
            'album_type' => 'album',
            'duration_ms' => 3167751,
            'popularity' => 88,
            'external_url' => 'https://open.spotify.com/album/6dVIqQ8qmQ5GBnJ9shOYGE',
            'upc' => '724384529018',
            'user_rating' => 5.0,
            'personal_notes' => 'A masterpiece',
            'listen_count' => 42,
            'favorite_track' => 'No Surprises',
            'date_started' => '2024-01-01',
            'date_finished' => '2024-01-30',
            'completed_at' => '2024-01-30',
            'user_statuses' => 'listened, favorite',
            'user_added_at' => '2024-01-01 10:00:00',
        ];
    }

    // ── toDomain ──

    #[Test]
    public function to_domain_maps_all_fields(): void
    {
        $album = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(Album::class, $album);
        $this->assertSame(1, $album->getId());
        $this->assertEquals(self::SPOTIFY_ID, $album->getSpotifyId()->toString());
        $this->assertEquals('OK Computer', $album->getTitle());
        $this->assertEquals('Radiohead', $album->getArtist());
        $this->assertEquals('artist123456789012', $album->getArtistId());
        $this->assertEquals('1997-05-21', $album->getReleaseDate());
        $this->assertEquals('day', $album->getReleaseDatePrecision());
        $this->assertEquals('https://example.com/cover.jpg', $album->getCoverUrl());
        $this->assertEquals('Parlophone', $album->getLabel());
        $this->assertSame(12, $album->getTotalTracks());
        $this->assertEquals('album', $album->getAlbumType());
        $this->assertSame(3167751, $album->getDurationMs());
        $this->assertSame(88, $album->getPopularity());
        $this->assertSame(5.0, $album->getUserRating()->toFloat());
        $this->assertEquals('A masterpiece', $album->getPersonalNotes());
        $this->assertSame(42, $album->getListenCount());
        $this->assertEquals('No Surprises', $album->getFavoriteTrack());
        $this->assertEquals('2024-01-01', $album->getDateStarted());
        $this->assertEquals('2024-01-30', $album->getDateFinished());
        $this->assertEquals('2024-01-30', $album->getCompletedAt());
    }

    #[Test]
    public function to_domain_parses_genres_from_json(): void
    {
        $album = $this->mapper->toDomain($this->fullDbRow());

        $this->assertIsArray($album->getGenres());
        $this->assertCount(2, $album->getGenres());
        $this->assertEquals('Alternative Rock', $album->getGenres()[0]->toString());
        $this->assertEquals('Art Rock', $album->getGenres()[1]->toString());
    }

    #[Test]
    public function to_domain_parses_user_statuses_from_comma_string(): void
    {
        $album = $this->mapper->toDomain($this->fullDbRow());

        $this->assertIsArray($album->getUserStatuses());
        $this->assertContains('listened', $album->getUserStatuses());
        $this->assertContains('favorite', $album->getUserStatuses());
    }

    #[Test]
    public function to_domain_parses_user_statuses_from_array(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = ['in-wishlist', 'listened'];

        $album = $this->mapper->toDomain($row);
        $this->assertEquals(['in-wishlist', 'listened'], $album->getUserStatuses());
    }

    #[Test]
    public function to_domain_empty_user_statuses(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = '';

        $album = $this->mapper->toDomain($row);
        $this->assertEmpty($album->getUserStatuses());
    }

    #[Test]
    public function to_domain_null_user_statuses(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = null;

        $album = $this->mapper->toDomain($row);
        $this->assertEmpty($album->getUserStatuses());
    }

    #[Test]
    public function to_domain_handles_null_user_rating(): void
    {
        $row = $this->fullDbRow();
        $row['user_rating'] = null;

        $album = $this->mapper->toDomain($row);
        $this->assertNull($album->getUserRating());
    }

    #[Test]
    public function to_domain_handles_null_optional_fields(): void
    {
        $row = $this->fullDbRow();
        $row['artist_id'] = null;
        $row['cover_url'] = null;
        $row['genres'] = null;
        $row['label'] = null;
        $row['total_tracks'] = null;
        $row['album_type'] = null;
        $row['duration_ms'] = null;
        $row['popularity'] = null;
        $row['external_url'] = null;
        $row['upc'] = null;
        $row['personal_notes'] = null;
        $row['listen_count'] = null;
        $row['favorite_track'] = null;
        $row['date_started'] = null;
        $row['date_finished'] = null;
        $row['completed_at'] = null;

        $album = $this->mapper->toDomain($row);

        $this->assertNull($album->getArtistId());
        $this->assertNull($album->getCoverUrl());
        $this->assertNull($album->getGenres());
        $this->assertNull($album->getLabel());
        $this->assertNull($album->getTotalTracks());
        $this->assertNull($album->getAlbumType());
        $this->assertNull($album->getDurationMs());
        $this->assertNull($album->getPopularity());
        $this->assertNull($album->getExternalUrl());
        $this->assertNull($album->getUpc());
        $this->assertNull($album->getPersonalNotes());
        $this->assertNull($album->getFavoriteTrack());
        $this->assertNull($album->getDateStarted());
        $this->assertNull($album->getDateFinished());
        $this->assertNull($album->getCompletedAt());
    }

    #[Test]
    public function to_domain_falls_back_to_current_time_when_no_user_added_at(): void
    {
        $row = $this->fullDbRow();
        unset($row['user_added_at']);

        $album = $this->mapper->toDomain($row);
        $this->assertNotNull($album->getAddedTimestamp());
    }

    // ── toDomainCollection ──

    #[Test]
    public function to_domain_collection_maps_multiple_rows(): void
    {
        $rows = [
            $this->fullDbRow(),
            array_merge($this->fullDbRow(), [
                'id' => 2,
                'spotify_id' => '7tFiyTwD0nx5a1eklYtX2J',
                'title' => 'Pablo Honey',
            ]),
        ];

        $albums = $this->mapper->toDomainCollection($rows);

        $this->assertCount(2, $albums);
        $this->assertInstanceOf(Album::class, $albums[0]);
        $this->assertInstanceOf(Album::class, $albums[1]);
        $this->assertEquals('OK Computer', $albums[0]->getTitle());
        $this->assertEquals('Pablo Honey', $albums[1]->getTitle());
    }

    #[Test]
    public function to_domain_collection_returns_empty_array_for_empty_input(): void
    {
        $albums = $this->mapper->toDomainCollection([]);
        $this->assertEmpty($albums);
    }

    // ── toPersistence ──

    #[Test]
    public function to_persistence_maps_all_fields(): void
    {
        $album = $this->mapper->toDomain($this->fullDbRow());
        $persisted = $this->mapper->toPersistence($album);

        $this->assertSame(1, $persisted['id']);
        $this->assertEquals(self::SPOTIFY_ID, $persisted['spotify_id']);
        $this->assertEquals('OK Computer', $persisted['title']);
        $this->assertEquals('Radiohead', $persisted['artist']);
        $this->assertEquals('artist123456789012', $persisted['artist_id']);
        $this->assertEquals('1997-05-21', $persisted['release_date']);
        $this->assertEquals('day', $persisted['release_date_precision']);
        $this->assertEquals('https://example.com/cover.jpg', $persisted['cover_url']);
        $this->assertEquals('Parlophone', $persisted['label']);
        $this->assertSame(12, $persisted['total_tracks']);
        $this->assertEquals('album', $persisted['album_type']);
        $this->assertSame(3167751, $persisted['duration_ms']);
        $this->assertSame(88, $persisted['popularity']);
    }

    #[Test]
    public function to_persistence_encodes_genres_as_json(): void
    {
        $album = $this->mapper->toDomain($this->fullDbRow());
        $persisted = $this->mapper->toPersistence($album);

        $genres = json_decode($persisted['genres'], true);
        $this->assertIsArray($genres);
        $this->assertContains('Alternative Rock', $genres);
        $this->assertContains('Art Rock', $genres);
    }

    #[Test]
    public function to_persistence_null_genres_stays_null(): void
    {
        $row = $this->fullDbRow();
        $row['genres'] = null;

        $album = $this->mapper->toDomain($row);
        $persisted = $this->mapper->toPersistence($album);

        $this->assertNull($persisted['genres']);
    }

    // ── Round-trip ──

    #[Test]
    public function round_trip_toDomain_toPersistence_preserves_core_fields(): void
    {
        $album = $this->mapper->toDomain($this->fullDbRow());
        $persisted = $this->mapper->toPersistence($album);

        // Re-map from persisted data (simulate what DB would store/return)
        $roundTripRow = array_merge($this->fullDbRow(), [
            'genres' => $persisted['genres'], // already JSON encoded
        ]);
        $album2 = $this->mapper->toDomain($roundTripRow);

        $this->assertEquals($album->getTitle(), $album2->getTitle());
        $this->assertEquals($album->getSpotifyId()->toString(), $album2->getSpotifyId()->toString());
        $this->assertEquals($album->getArtist(), $album2->getArtist());
        $this->assertSame(count($album->getGenres()), count($album2->getGenres()));
    }
}
