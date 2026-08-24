<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\Album;
use App\Domain\Model\ValueObjects\AlbumId;
use App\Domain\Model\ValueObjects\SpotifyId;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AlbumTest extends TestCase
{
    private const SPOTIFY_ID = '4aawyAB9vmqN3uQ7FjRGTy';

    private function makeAlbum(array $overrides = []): Album
    {
        $defaults = [
            'id' => 1,
            'spotify_id' => self::SPOTIFY_ID,
            'title' => 'OK Computer',
            'artist' => 'Radiohead',
            'userStatuses' => ['listened'],
        ];

        return Album::fromArray(array_merge($defaults, $overrides));
    }

    // ── Construction ──

    #[Test]
    public function creates_album_with_minimum_required_fields(): void
    {
        $album = $this->makeAlbum();

        $this->assertEquals(1, $album->getId());
        $this->assertEquals(self::SPOTIFY_ID, $album->getSpotifyId()->toString());
        $this->assertEquals('OK Computer', $album->getTitle());
        $this->assertEquals('Radiohead', $album->getArtist());
        $this->assertEquals(['listened'], $album->getUserStatuses());
    }

    #[Test]
    public function creates_album_with_all_optional_fields(): void
    {
        $album = $this->makeAlbum([
            'artist_id' => 'spotify:artist:4Z8W4fKeB5YxbusRsdQVPb',
            'release_date' => '1997-05-21',
            'release_date_precision' => 'day',
            'cover_url' => 'https://example.com/cover.jpg',
            'genres' => ['Alternative Rock', 'Art Rock'],
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
            'date_finished' => '2024-01-05',
        ]);

        $this->assertEquals('1997-05-21', $album->getReleaseDate());
        $this->assertEquals('day', $album->getReleaseDatePrecision());
        $this->assertEquals('Parlophone', $album->getLabel());
        $this->assertSame(12, $album->getTotalTracks());
        $this->assertEquals('album', $album->getAlbumType());
        $this->assertSame(3167751, $album->getDurationMs());
        $this->assertSame(88, $album->getPopularity());
        $this->assertNotNull($album->getUserRating());
        $this->assertSame(5.0, $album->getUserRating()->toFloat());
        $this->assertEquals('A masterpiece', $album->getPersonalNotes());
        $this->assertSame(42, $album->getListenCount());
        $this->assertEquals('No Surprises', $album->getFavoriteTrack());
        $this->assertEquals('2024-01-01', $album->getDateStarted());
        $this->assertEquals('2024-01-05', $album->getDateFinished());
        $this->assertCount(2, $album->getGenres());
        $this->assertEquals('Alternative Rock', $album->getGenres()[0]->toString());
    }

    // ── Validation ──

    #[Test]
    public function throws_when_title_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Title');
        $this->makeAlbum(['title' => '']);
    }

    #[Test]
    public function throws_when_spotify_id_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Album::fromArray([
            'title' => 'Test',
            'userStatuses' => ['listened'],
        ]);
    }

    #[Test]
    public function throws_when_user_statuses_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Album::fromArray([
            'spotify_id' => self::SPOTIFY_ID,
            'title' => 'Test',
        ]);
    }

    #[Test]
    public function throws_when_popularity_above_100(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Popularity must be between 0 and 100');
        new Album(
            id: 1,
            albumId: AlbumId::fromString(self::SPOTIFY_ID),
            catalogSource: 'spotify',
            spotifyId: SpotifyId::fromString(self::SPOTIFY_ID),
            title: 'Test',
            artist: 'Test Artist',
            artistId: null,
            releaseDate: null,
            releaseDatePrecision: null,
            coverUrl: null,
            genres: null,
            label: null,
            totalTracks: null,
            albumType: null,
            durationMs: null,
            popularity: 101,
            externalUrl: null,
            upc: null,
            addedTimestamp: Timestamp::now(),
            userRating: null,
            userStatuses: ['listened']
        );
    }

    #[Test]
    public function throws_when_listen_count_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Listen count must be non-negative');
        new Album(
            id: 1,
            albumId: AlbumId::fromString(self::SPOTIFY_ID),
            catalogSource: 'spotify',
            spotifyId: SpotifyId::fromString(self::SPOTIFY_ID),
            title: 'Test',
            artist: 'Test Artist',
            artistId: null,
            releaseDate: null,
            releaseDatePrecision: null,
            coverUrl: null,
            genres: null,
            label: null,
            totalTracks: null,
            albumType: null,
            durationMs: null,
            popularity: null,
            externalUrl: null,
            upc: null,
            addedTimestamp: Timestamp::now(),
            userRating: null,
            userStatuses: ['listened'],
            allowedStatuses: [],
            tags: null,
            allowedTags: null,
            personalNotes: null,
            listenCount: -1
        );
    }

    // ── Genres parsing ──

    #[Test]
    public function from_array_parses_genres_from_json_string(): void
    {
        $album = $this->makeAlbum(['genres' => '["Pop", "Rock"]']);
        $this->assertCount(2, $album->getGenres());
        $this->assertEquals('Pop', $album->getGenres()[0]->toString());
        $this->assertEquals('Rock', $album->getGenres()[1]->toString());
    }

    #[Test]
    public function from_array_parses_genres_from_array(): void
    {
        $album = $this->makeAlbum(['genres' => ['Jazz', 'Soul']]);
        $this->assertCount(2, $album->getGenres());
        $this->assertEquals('Jazz', $album->getGenres()[0]->toString());
    }

    #[Test]
    public function from_array_with_no_genres_returns_null(): void
    {
        $album = $this->makeAlbum(['genres' => null]);
        $this->assertNull($album->getGenres());
    }

    // ── Status helpers ──

    #[Test]
    public function has_status_returns_true_for_existing_status(): void
    {
        $album = $this->makeAlbum(['userStatuses' => ['listened', 'favorite']]);
        $this->assertTrue($album->hasStatus('listened'));
        $this->assertTrue($album->hasStatus('favorite'));
        $this->assertFalse($album->hasStatus('in-wishlist'));
    }

    #[Test]
    public function is_listened_returns_true_for_listened_status(): void
    {
        $album = $this->makeAlbum(['userStatuses' => ['listened']]);
        $this->assertTrue($album->isListened());
    }

    #[Test]
    public function is_listened_returns_true_for_re_listening_status(): void
    {
        $album = $this->makeAlbum(['userStatuses' => ['re-listening']]);
        $this->assertTrue($album->isListened());
    }

    #[Test]
    public function is_in_wishlist_returns_true_for_in_wishlist_status(): void
    {
        $album = $this->makeAlbum(['userStatuses' => ['in-wishlist']]);
        $this->assertTrue($album->isInWishlist());
    }

    #[Test]
    public function is_favorite_returns_true(): void
    {
        $album = $this->makeAlbum(['userStatuses' => ['favorite']]);
        $this->assertTrue($album->isFavorite());
    }

    // ── Formatted duration ──

    #[Test]
    public function get_formatted_duration_returns_zero_for_null(): void
    {
        $album = $this->makeAlbum(['duration_ms' => null]);
        $this->assertEquals('0:00', $album->getFormattedDuration());
    }

    #[Test]
    public function get_formatted_duration_formats_minutes_and_seconds(): void
    {
        $album = $this->makeAlbum(['duration_ms' => 245000]); // 4 min 5 sec
        $this->assertEquals('4:05', $album->getFormattedDuration());
    }

    #[Test]
    public function get_formatted_duration_formats_hours(): void
    {
        $album = $this->makeAlbum(['duration_ms' => 3723000]); // 1h 2m 3s
        $this->assertEquals('1:02:03', $album->getFormattedDuration());
    }

    // ── hasGenre ──

    #[Test]
    public function has_genre_is_case_insensitive(): void
    {
        $album = $this->makeAlbum(['genres' => ['Alternative Rock']]);
        $this->assertTrue($album->hasGenre('Alternative Rock'));
        $this->assertTrue($album->hasGenre('alternative rock'));
    }

    #[Test]
    public function has_genre_returns_false_when_genres_null(): void
    {
        $album = $this->makeAlbum(['genres' => null]);
        $this->assertFalse($album->hasGenre('Pop'));
    }

    // ── fromArray camelCase aliases ──

    #[Test]
    public function from_array_accepts_camel_case_keys(): void
    {
        $album = Album::fromArray([
            'spotifyId' => self::SPOTIFY_ID,
            'title' => 'Test Album',
            'artist' => 'Test Artist',
            'artistId' => 'artist123456789012',
            'releaseDate' => '2024-01-01',
            'releaseDatePrecision' => 'day',
            'coverUrl' => 'https://cover.jpg',
            'albumType' => 'album',
            'totalTracks' => 10,
            'durationMs' => 200000,
            'externalUrl' => 'https://spotify.com',
            'userStatuses' => ['in-wishlist'],
        ]);

        $this->assertEquals(self::SPOTIFY_ID, $album->getSpotifyId()->toString());
        $this->assertEquals('2024-01-01', $album->getReleaseDate());
        $this->assertEquals('day', $album->getReleaseDatePrecision());
        $this->assertEquals('https://cover.jpg', $album->getCoverUrl());
        $this->assertEquals('album', $album->getAlbumType());
        $this->assertSame(10, $album->getTotalTracks());
    }
}
