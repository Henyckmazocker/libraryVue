<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Commands;

use App\Domain\DTO\Commands\AddAlbumCommand;
use App\Domain\DTO\Commands\DeleteAlbumCommand;
use App\Domain\DTO\Commands\EditUserAlbumCommand;
use App\Domain\DTO\Commands\UpdateAlbumRatingCommand;
use App\Domain\DTO\Commands\UpdateAlbumStatusesCommand;
use App\Domain\Model\ValueObjects\SpotifyId;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AlbumCommandsTest extends TestCase
{
    private const SPOTIFY_ID = '4aawyAB9vmqN3uQ7FjRGTy';

    // ═══════════════════════════════════════
    // AddAlbumCommand
    // ═══════════════════════════════════════

    #[Test]
    public function add_album_constructor_sets_all_properties(): void
    {
        $spotifyId = SpotifyId::fromString(self::SPOTIFY_ID);
        $rating = Rating::fromFloat(4.5);
        $genres = [Genre::fromString('Rock')];

        $cmd = new AddAlbumCommand(
            id: 0,
            spotifyId: $spotifyId,
            title: 'OK Computer',
            artist: 'Radiohead',
            userId: 1,
            statuses: ['listened'],
            artistId: 'artistId123456789',
            releaseDate: '1997-05-21',
            releaseDatePrecision: 'day',
            coverUrl: 'https://cover.jpg',
            genres: $genres,
            label: 'Parlophone',
            totalTracks: 12,
            albumType: 'album',
            durationMs: 3167751,
            popularity: 88,
            externalUrl: 'https://open.spotify.com',
            upc: '724384529018',
            userRating: $rating,
            personalNotes: 'Amazing album',
            listenCount: 5,
            favoriteTrack: 'No Surprises'
        );

        $this->assertSame($spotifyId, $cmd->spotifyId);
        $this->assertEquals('OK Computer', $cmd->title);
        $this->assertEquals('Radiohead', $cmd->artist);
        $this->assertSame(1, $cmd->userId);
        $this->assertEquals(['listened'], $cmd->statuses);
        $this->assertEquals('1997-05-21', $cmd->releaseDate);
        $this->assertEquals('Parlophone', $cmd->label);
        $this->assertSame(12, $cmd->totalTracks);
        $this->assertSame(88, $cmd->popularity);
        $this->assertSame(5, $cmd->listenCount);
        $this->assertEquals('No Surprises', $cmd->favoriteTrack);
        $this->assertSame($rating, $cmd->userRating);
        $this->assertCount(1, $cmd->genres);
    }

    #[Test]
    public function add_album_from_array_full(): void
    {
        $cmd = AddAlbumCommand::fromArray([
            'spotify_id' => self::SPOTIFY_ID,
            'title' => 'OK Computer',
            'artist' => 'Radiohead',
            'artist_id' => 'artistId123456789',
            'release_date' => '1997-05-21',
            'release_date_precision' => 'day',
            'cover_url' => 'https://cover.jpg',
            'genres' => ['Rock', 'Alternative'],
            'label' => 'Parlophone',
            'total_tracks' => 12,
            'album_type' => 'album',
            'duration_ms' => 3167751,
            'popularity' => 88,
            'external_url' => 'https://open.spotify.com',
            'upc' => '724384529018',
            'user_rating' => 4.5,
            'personal_notes' => 'Great!',
            'listen_count' => 3,
            'favorite_track' => 'No Surprises',
            'statuses' => ['listened'],
        ], 1);

        $this->assertEquals(self::SPOTIFY_ID, $cmd->spotifyId->toString());
        $this->assertEquals('OK Computer', $cmd->title);
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(4.5, $cmd->userRating->toFloat());
        $this->assertCount(2, $cmd->genres);
        $this->assertEquals('Rock', $cmd->genres[0]->toString());
        $this->assertSame(88, $cmd->popularity);
        $this->assertSame(12, $cmd->totalTracks);
        $this->assertSame(3167751, $cmd->durationMs);
        $this->assertSame(3, $cmd->listenCount);
        $this->assertEquals('No Surprises', $cmd->favoriteTrack);
    }

    #[Test]
    public function add_album_from_array_camel_case_keys(): void
    {
        $cmd = AddAlbumCommand::fromArray([
            'spotifyId' => self::SPOTIFY_ID,
            'title' => 'Test Album',
            'totalTracks' => 10,
            'durationMs' => 200000,
            'releaseDate' => '2024-01-01',
        ], 2);

        $this->assertEquals(self::SPOTIFY_ID, $cmd->spotifyId->toString());
        $this->assertSame(10, $cmd->totalTracks);
        $this->assertSame(200000, $cmd->durationMs);
        $this->assertEquals('2024-01-01', $cmd->releaseDate);
        $this->assertSame(2, $cmd->userId);
    }

    #[Test]
    public function add_album_from_array_throws_without_spotify_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Spotify ID is required');
        AddAlbumCommand::fromArray(['title' => 'Test'], 1);
    }

    #[Test]
    public function add_album_from_array_throws_without_title(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Album title is required');
        AddAlbumCommand::fromArray(['spotify_id' => self::SPOTIFY_ID], 1);
    }

    #[Test]
    public function add_album_to_album_array_serializes_genres_to_strings(): void
    {
        $cmd = AddAlbumCommand::fromArray([
            'spotify_id' => self::SPOTIFY_ID,
            'title' => 'Test',
            'genres' => ['Rock', 'Pop'],
            'statuses' => ['listened'],
        ], 1);

        $arr = $cmd->toAlbumArray();
        $this->assertIsArray($arr['genres']);
        $this->assertEquals(['Rock', 'Pop'], $arr['genres']);
        $this->assertEquals(self::SPOTIFY_ID, $arr['spotify_id']);
        $this->assertEquals(['listened'], $arr['userStatuses']);
    }

    #[Test]
    public function add_album_to_album_array_null_genres(): void
    {
        $cmd = AddAlbumCommand::fromArray([
            'spotify_id' => self::SPOTIFY_ID,
            'title' => 'Test',
        ], 1);

        $arr = $cmd->toAlbumArray();
        $this->assertNull($arr['genres']);
    }

    // ═══════════════════════════════════════
    // DeleteAlbumCommand
    // ═══════════════════════════════════════

    #[Test]
    public function delete_album_constructor_sets_properties(): void
    {
        $cmd = new DeleteAlbumCommand(userId: 1, albumId: 42);
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(42, $cmd->albumId);
    }

    #[Test]
    public function delete_album_from_array_full(): void
    {
        $cmd = DeleteAlbumCommand::fromArray(['albumId' => 99], 1);
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(99, $cmd->albumId);
    }

    #[Test]
    public function delete_album_from_array_casts_string_to_int(): void
    {
        $cmd = DeleteAlbumCommand::fromArray(['albumId' => '42'], 1);
        $this->assertSame(42, $cmd->albumId);
    }

    #[Test]
    public function delete_album_from_array_throws_without_album_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Album ID is required');
        DeleteAlbumCommand::fromArray([], 1);
    }

    // ═══════════════════════════════════════
    // UpdateAlbumRatingCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_album_rating_constructor_sets_properties(): void
    {
        $rating = Rating::fromFloat(4.0);
        $cmd = new UpdateAlbumRatingCommand(userId: 1, albumId: 42, rating: $rating);
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(42, $cmd->albumId);
        $this->assertSame($rating, $cmd->rating);
    }

    #[Test]
    public function update_album_rating_from_array_full(): void
    {
        $cmd = UpdateAlbumRatingCommand::fromArray(['albumId' => 10, 'rating' => 3.5], 1);
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(10, $cmd->albumId);
        $this->assertSame(3.5, $cmd->rating->toFloat());
    }

    #[Test]
    public function update_album_rating_from_array_throws_without_rating(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Valid rating is required');
        UpdateAlbumRatingCommand::fromArray(['albumId' => 1], 1);
    }

    #[Test]
    public function update_album_rating_from_array_throws_without_album_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Album ID is required');
        UpdateAlbumRatingCommand::fromArray(['rating' => 4.0], 1);
    }

    // ═══════════════════════════════════════
    // UpdateAlbumStatusesCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_album_statuses_constructor_sets_properties(): void
    {
        $cmd = new UpdateAlbumStatusesCommand(userId: 1, albumId: 5, statuses: ['listened', 'favorite']);
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(5, $cmd->albumId);
        $this->assertEquals(['listened', 'favorite'], $cmd->statuses);
    }

    #[Test]
    public function update_album_statuses_from_array_full(): void
    {
        $cmd = UpdateAlbumStatusesCommand::fromArray([
            'albumId' => 5,
            'statuses' => ['listened'],
        ], 1);

        $this->assertSame(5, $cmd->albumId);
        $this->assertEquals(['listened'], $cmd->statuses);
    }

    #[Test]
    public function update_album_statuses_allows_empty_array(): void
    {
        $cmd = UpdateAlbumStatusesCommand::fromArray(['albumId' => 1, 'statuses' => []], 1);
        $this->assertEmpty($cmd->statuses);
    }

    #[Test]
    public function update_album_statuses_throws_without_statuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Statuses array is required');
        UpdateAlbumStatusesCommand::fromArray(['albumId' => 1], 1);
    }

    #[Test]
    public function update_album_statuses_throws_without_album_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UpdateAlbumStatusesCommand::fromArray(['statuses' => []], 1);
    }

    // ═══════════════════════════════════════
    // EditUserAlbumCommand
    // ═══════════════════════════════════════

    #[Test]
    public function edit_user_album_constructor_defaults(): void
    {
        $cmd = new EditUserAlbumCommand(userId: 1, albumId: 10);
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(10, $cmd->albumId);
        $this->assertNull($cmd->userRating);
        $this->assertNull($cmd->personalNotes);
        $this->assertNull($cmd->statuses);
        $this->assertEmpty($cmd->tags);
    }

    #[Test]
    public function edit_user_album_from_array_flat(): void
    {
        $cmd = EditUserAlbumCommand::fromArray([
            'albumId' => 10,
            'personalRating' => 4.0,
            'notes' => 'Great',
            'statuses' => ['listened'],
            'tags' => [1, 2],
        ], 1);

        $this->assertSame(10, $cmd->albumId);
        $this->assertSame(4.0, $cmd->userRating->toFloat());
        $this->assertEquals('Great', $cmd->personalNotes);
        $this->assertEquals(['listened'], $cmd->statuses);
        $this->assertEquals([1, 2], $cmd->tags);
    }

    #[Test]
    public function edit_user_album_from_array_nested_data_subkey(): void
    {
        $cmd = EditUserAlbumCommand::fromArray([
            'albumId' => 10,
            'data' => [
                'personalRating' => 3.5,
                'personalNotes' => 'Good album',
                'statuses' => ['in-wishlist'],
                'listenCount' => 7,
                'favoriteTrack' => 'Track 1',
                'dateStarted' => '2024-01-01',
                'dateFinished' => '2024-01-10',
            ],
            'tags' => [5],
        ], 1);

        $this->assertSame(10, $cmd->albumId);
        $this->assertSame(3.5, $cmd->userRating->toFloat());
        $this->assertEquals('Good album', $cmd->personalNotes);
        $this->assertSame(7, $cmd->listenCount);
        $this->assertEquals('Track 1', $cmd->favoriteTrack);
        $this->assertEquals('2024-01-01', $cmd->dateStarted);
        $this->assertEquals('2024-01-10', $cmd->dateFinished);
        $this->assertEquals([5], $cmd->tags);
    }

    #[Test]
    public function edit_user_album_null_statuses_distinct_from_empty(): void
    {
        $cmdNull = EditUserAlbumCommand::fromArray(['albumId' => 1], 1);
        $cmdEmpty = EditUserAlbumCommand::fromArray(['albumId' => 1, 'statuses' => []], 1);

        $this->assertNull($cmdNull->statuses);
        $this->assertIsArray($cmdEmpty->statuses);
        $this->assertEmpty($cmdEmpty->statuses);
    }

    #[Test]
    public function edit_user_album_to_array_only_includes_non_null_fields(): void
    {
        $cmd = new EditUserAlbumCommand(
            userId: 1,
            albumId: 10,
            userRating: Rating::fromFloat(4.0),
            personalNotes: 'Good',
            listenCount: null
        );

        $arr = $cmd->toArray();
        $this->assertArrayHasKey('personal_rating', $arr);
        $this->assertArrayHasKey('personal_notes', $arr);
        $this->assertArrayNotHasKey('listen_count', $arr);
    }

    #[Test]
    public function edit_user_album_from_array_throws_without_album_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Album ID is required');
        EditUserAlbumCommand::fromArray([], 1);
    }

    // ═══════════════════════════════════════
    // Ownership Format
    // ═══════════════════════════════════════

    #[Test]
    public function add_album_ownership_format_id_from_array(): void
    {
        $cmd = AddAlbumCommand::fromArray([
            'spotifyId' => '1BZlo1MbFSztOf3l1X3vxQ',
            'title' => 'Album',
            'artist' => 'Artist',
            'ownership_format_id' => 6,
        ], 1);

        $this->assertSame(6, $cmd->ownershipFormatId);
    }

    #[Test]
    public function add_album_ownership_format_id_defaults_null(): void
    {
        $cmd = AddAlbumCommand::fromArray([
            'spotifyId' => '1BZlo1MbFSztOf3l1X3vxQ',
            'title' => 'Album',
            'artist' => 'Artist',
        ], 1);

        $this->assertNull($cmd->ownershipFormatId);
    }

    #[Test]
    public function edit_album_ownership_format_id_from_nested_data(): void
    {
        $cmd = EditUserAlbumCommand::fromArray([
            'albumId' => 10,
            'data' => ['ownershipFormatId' => 3],
        ], 1);

        $this->assertSame(3, $cmd->ownershipFormatId);
    }
}
