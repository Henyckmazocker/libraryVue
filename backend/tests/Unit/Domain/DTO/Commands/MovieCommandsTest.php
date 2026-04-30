<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Commands;

use App\Domain\DTO\Commands\AddMovieCommand;
use App\Domain\DTO\Commands\DeleteMovieCommand;
use App\Domain\DTO\Commands\EditUserMovieCommand;
use App\Domain\DTO\Commands\UpdateMovieRatingCommand;
use App\Domain\DTO\Commands\UpdateMovieStatusesCommand;
use App\Domain\DTO\Commands\TrackSeriesSeasonCommand;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MovieCommandsTest extends TestCase
{
    // ═══════════════════════════════════════
    // AddMovieCommand
    // ═══════════════════════════════════════

    #[Test]
    public function add_movie_constructor_sets_all_properties(): void
    {
        $id = MovieIdentifier::fromImdb('tt1234567');
        $rating = Rating::fromFloat(4.0);
        $userRating = Rating::fromFloat(3.5);

        $cmd = new AddMovieCommand(
            id: $id,
            title: 'Test Movie',
            userId: 1,
            statuses: ['watched'],
            originalTitle: 'Film Original',
            director: 'Director',
            coverUrl: 'https://poster.jpg',
            rating: $rating,
            userRating: $userRating,
            description: 'A movie',
            genres: [Genre::fromString('Drama')]
        );

        $this->assertSame($id, $cmd->id);
        $this->assertEquals('Test Movie', $cmd->title);
        $this->assertSame(1, $cmd->userId);
        $this->assertEquals(['watched'], $cmd->statuses);
        $this->assertEquals('Film Original', $cmd->originalTitle);
        $this->assertEquals('Director', $cmd->director);
        $this->assertEquals('https://poster.jpg', $cmd->coverUrl);
        $this->assertSame($rating, $cmd->rating);
        $this->assertSame($userRating, $cmd->userRating);
        $this->assertEquals('A movie', $cmd->description);
        $this->assertCount(1, $cmd->genres);
    }

    #[Test]
    public function add_movie_from_array_with_id(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'id' => 'tt1234567',
            'title' => 'Movie',
            'userStatuses' => ['watched'],
            'director' => 'Dir',
            'coverUrl' => 'https://poster.jpg',
            'rating' => 4.0,
            'user_rating' => 3.5,
            'description' => 'Plot',
            'genres' => ['Drama', 'Action'],
        ], 1);

        $this->assertEquals('tt1234567', $cmd->id->toString());
        $this->assertEquals('Movie', $cmd->title);
        $this->assertSame(4.0, $cmd->rating->toFloat());
        $this->assertSame(3.5, $cmd->userRating->toFloat());
        $this->assertCount(2, $cmd->genres);
    }

    #[Test]
    public function add_movie_from_array_isbn_fallback(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'isbn' => 'tt9876543',
            'title' => 'Movie',
        ], 1);

        $this->assertEquals('tt9876543', $cmd->id->toString());
    }

    #[Test]
    public function add_movie_from_array_imdb_id_fallback(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'imdbID' => 'tt1111111',
            'title' => 'Movie',
        ], 1);

        $this->assertEquals('tt1111111', $cmd->id->toString());
    }

    #[Test]
    public function add_movie_from_array_poster_key_fallback(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'id' => 'tt1234567',
            'title' => 'Movie',
            'Poster' => 'https://poster-omdb.jpg',
        ], 1);

        $this->assertEquals('https://poster-omdb.jpg', $cmd->coverUrl);
    }

    #[Test]
    public function add_movie_from_array_plot_key_fallback(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'id' => 'tt1234567',
            'title' => 'Movie',
            'Plot' => 'A great plot',
        ], 1);

        $this->assertEquals('A great plot', $cmd->description);
    }

    #[Test]
    public function add_movie_from_array_original_title_camel_case(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'id' => 'tt1234567',
            'title' => 'Movie',
            'originalTitle' => 'Original',
        ], 1);

        $this->assertEquals('Original', $cmd->originalTitle);
    }

    #[Test]
    public function add_movie_to_array_contains_aliases(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'id' => 'tt1234567',
            'title' => 'Movie',
            'coverUrl' => 'https://poster.jpg',
            'rating' => 4.0,
            'genres' => ['Drama'],
        ], 1);

        $arr = $cmd->toArray();
        $this->assertEquals('tt1234567', $arr['id']);
        $this->assertEquals('tt1234567', $arr['isbn']);
        $this->assertEquals('tt1234567', $arr['imdbID']);
        $this->assertEquals('https://poster.jpg', $arr['coverUrl']);
        $this->assertEquals('https://poster.jpg', $arr['cover_url']);
        $this->assertEquals(['Drama'], $arr['genres']);
    }

    #[Test]
    public function add_movie_from_array_zero_rating_is_null(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'id' => 'tt1234567',
            'title' => 'Movie',
            'rating' => 0,
            'user_rating' => 0,
        ], 1);

        $this->assertNull($cmd->rating);
        $this->assertNull($cmd->userRating);
    }

    // ═══════════════════════════════════════
    // DeleteMovieCommand
    // ═══════════════════════════════════════

    #[Test]
    public function delete_movie_from_array_id(): void
    {
        $cmd = DeleteMovieCommand::fromArray(['id' => 'tt1234567'], 5);

        $this->assertEquals('tt1234567', $cmd->id->toString());
        $this->assertSame(5, $cmd->userId);
    }

    #[Test]
    public function delete_movie_from_array_isbn_fallback(): void
    {
        $cmd = DeleteMovieCommand::fromArray(['isbn' => 'tt9876543'], 5);

        $this->assertEquals('tt9876543', $cmd->id->toString());
    }

    // ═══════════════════════════════════════
    // EditUserMovieCommand
    // ═══════════════════════════════════════

    #[Test]
    public function edit_user_movie_from_array_nested_data(): void
    {
        $cmd = EditUserMovieCommand::fromArray([
            'id' => 'tt1234567',
            'data' => [
                'personalRating' => 4.5,
                'statuses' => ['watched'],
            ],
            'tags' => [1, 3],
        ], 1);

        $this->assertEquals('tt1234567', $cmd->id->toString());
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(4.5, $cmd->userRating->toFloat());
        $this->assertEquals(['watched'], $cmd->statuses);
        $this->assertEquals([1, 3], $cmd->tags);
    }

    #[Test]
    public function edit_user_movie_from_array_flat(): void
    {
        $cmd = EditUserMovieCommand::fromArray([
            'isbn' => 'tt1234567',
            'user_rating' => 3.0,
            'statuses' => ['to-watch'],
        ], 2);

        $this->assertSame(3.0, $cmd->userRating->toFloat());
        $this->assertEquals(['to-watch'], $cmd->statuses);
    }

    #[Test]
    public function edit_user_movie_defaults(): void
    {
        $cmd = EditUserMovieCommand::fromArray([
            'id' => 'tt1234567',
        ], 1);

        $this->assertNull($cmd->userRating);
        $this->assertNull($cmd->statuses);
        $this->assertEquals([], $cmd->tags);
    }

    // ═══════════════════════════════════════
    // UpdateMovieRatingCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_movie_rating_from_array(): void
    {
        $cmd = UpdateMovieRatingCommand::fromArray([
            'id' => 'tt1234567',
            'rating' => 4.5,
        ], 1);

        $this->assertSame(1, $cmd->userId);
        $this->assertEquals('tt1234567', $cmd->id->toString());
        $this->assertSame(4.5, $cmd->rating->toFloat());
    }

    #[Test]
    public function update_movie_rating_isbn_fallback(): void
    {
        $cmd = UpdateMovieRatingCommand::fromArray([
            'isbn' => 'tt9876543',
            'rating' => 3.0,
        ], 2);

        $this->assertEquals('tt9876543', $cmd->id->toString());
    }

    // ═══════════════════════════════════════
    // UpdateMovieStatusesCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_movie_statuses_from_array(): void
    {
        $cmd = UpdateMovieStatusesCommand::fromArray([
            'id' => 'tt1234567',
            'statuses' => ['watched', 'owned'],
        ], 1);

        $this->assertEquals('tt1234567', $cmd->id->toString());
        $this->assertSame(1, $cmd->userId);
        $this->assertEquals(['watched', 'owned'], $cmd->statuses);
    }

    #[Test]
    public function update_movie_statuses_defaults_to_empty(): void
    {
        $cmd = UpdateMovieStatusesCommand::fromArray([
            'isbn' => 'tt1234567',
        ], 1);

        $this->assertEquals([], $cmd->statuses);
    }

    // ═══════════════════════════════════════
    // Ownership Format
    // ═══════════════════════════════════════

    #[Test]
    public function add_movie_ownership_format_id_snake_case(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'isbn' => 'tt1234567',
            'title' => 'Film',
            'ownership_format_id' => 4,
        ], 1);

        $this->assertSame(4, $cmd->ownershipFormatId);
    }

    #[Test]
    public function add_movie_ownership_format_id_defaults_null(): void
    {
        $cmd = AddMovieCommand::fromArray([
            'isbn' => 'tt1234567',
            'title' => 'Film',
        ], 1);

        $this->assertNull($cmd->ownershipFormatId);
    }

    #[Test]
    public function edit_movie_ownership_format_id_from_nested_data(): void
    {
        $cmd = EditUserMovieCommand::fromArray([
            'isbn' => 'tt1234567',
            'data' => ['ownership_format_id' => 7],
        ], 1);

        $this->assertSame(7, $cmd->ownershipFormatId);
    }

    // ═══════════════════════════════════════
    // TrackSeriesSeasonCommand
    // ═══════════════════════════════════════

    #[Test]
    public function track_series_season_constructor_sets_defaults(): void
    {
        $cmd = new TrackSeriesSeasonCommand(
            userId: 1,
            seriesIsbn: 'tt1234567',
            seasonNumber: 1,
        );

        $this->assertSame(1, $cmd->userId);
        $this->assertSame('tt1234567', $cmd->seriesIsbn);
        $this->assertSame(1, $cmd->seasonNumber);
        $this->assertSame('viewed', $cmd->status);
        $this->assertNull($cmd->dateViewed);
        $this->assertNull($cmd->personalRating);
        $this->assertNull($cmd->notes);
    }

    #[Test]
    public function track_series_season_throws_on_invalid_season_number(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TrackSeriesSeasonCommand(userId: 1, seriesIsbn: 'tt1234567', seasonNumber: 0);
    }

    #[Test]
    public function track_series_season_throws_on_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status must be one of');
        new TrackSeriesSeasonCommand(userId: 1, seriesIsbn: 'tt1234567', seasonNumber: 1, status: 'unknown');
    }

    #[Test]
    public function track_series_season_throws_on_invalid_rating(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('0.5 and 5.0 in 0.5 increments');
        new TrackSeriesSeasonCommand(userId: 1, seriesIsbn: 'tt1234567', seasonNumber: 1, personalRating: 3.3);
    }

    #[Test]
    public function track_series_season_from_array_camel_case(): void
    {
        $cmd = TrackSeriesSeasonCommand::fromArray([
            'seriesIsbn'    => 'tt9876543',
            'seasonNumber'  => 3,
            'status'        => 'partial',
            'personalRating' => 3.5,
            'notes'         => 'Good episode',
        ], 5);

        $this->assertSame(5, $cmd->userId);
        $this->assertSame('tt9876543', $cmd->seriesIsbn);
        $this->assertSame(3, $cmd->seasonNumber);
        $this->assertSame('partial', $cmd->status);
        $this->assertSame(3.5, $cmd->personalRating);
        $this->assertSame('Good episode', $cmd->notes);
    }

    #[Test]
    public function track_series_season_from_array_snake_case(): void
    {
        $cmd = TrackSeriesSeasonCommand::fromArray([
            'series_isbn'   => 'tt1111111',
            'season_number' => 2,
            'status'        => 'skipped',
            'personal_rating' => 2.0,
        ], 7);

        $this->assertSame('tt1111111', $cmd->seriesIsbn);
        $this->assertSame(2, $cmd->seasonNumber);
        $this->assertSame('skipped', $cmd->status);
        $this->assertSame(2.0, $cmd->personalRating);
    }
}
