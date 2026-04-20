<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Queries;

use App\Domain\DTO\Queries\GetMoviesByUserQuery;
use App\Domain\DTO\Queries\GetMovieNotesQuery;
use App\Domain\DTO\Queries\GetTrendingMoviesQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MovieQueriesTest extends TestCase
{
    // ═══════════════════════════════════════
    // GetMoviesByUserQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_movies_by_user_constructor(): void
    {
        $q = new GetMoviesByUserQuery(userId: 1, status: 'watched', sortBy: 'title', sortOrder: 'desc');

        $this->assertSame(1, $q->userId);
        $this->assertEquals('watched', $q->status);
        $this->assertEquals('title', $q->sortBy);
        $this->assertEquals('desc', $q->sortOrder);
    }

    #[Test]
    public function get_movies_by_user_defaults(): void
    {
        $q = new GetMoviesByUserQuery(userId: 1);

        $this->assertNull($q->status);
        $this->assertNull($q->sortBy);
        $this->assertEquals('asc', $q->sortOrder);
    }

    #[Test]
    public function get_movies_by_user_builds_filters(): void
    {
        $q = new GetMoviesByUserQuery(userId: 1, status: 'watched', sortBy: 'title');
        $filters = $q->toFilters();

        $this->assertEquals('watched', $filters['userStatus']);
        $this->assertEquals('title', $filters['sortBy']);
        $this->assertEquals('asc', $filters['sortOrder']);
    }

    #[Test]
    public function get_movies_by_user_empty_filters(): void
    {
        $q = new GetMoviesByUserQuery(userId: 1);
        $this->assertEmpty($q->toFilters());
    }

    #[Test]
    public function get_movies_by_user_from_array(): void
    {
        $q = GetMoviesByUserQuery::fromArray([
            'status' => 'to-watch',
            'sortBy' => 'director',
        ], 5);

        $this->assertSame(5, $q->userId);
        $this->assertEquals('to-watch', $q->status);
        $this->assertEquals('director', $q->sortBy);
    }

    // ═══════════════════════════════════════
    // GetMovieNotesQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_movie_notes_constructor(): void
    {
        $q = new GetMovieNotesQuery(userId: 1, movieIsbn: 'tt1234567', noteType: 'review');

        $this->assertSame(1, $q->userId);
        $this->assertEquals('tt1234567', $q->movieIsbn);
        $this->assertEquals('review', $q->noteType);
    }

    #[Test]
    public function get_movie_notes_defaults(): void
    {
        $q = new GetMovieNotesQuery(userId: 1, movieIsbn: 'tt1234567');
        $this->assertNull($q->noteType);
    }

    #[Test]
    public function get_movie_notes_from_array_camel_case(): void
    {
        $q = GetMovieNotesQuery::fromArray([
            'movieIsbn' => 'tt1234567',
            'noteType' => 'review',
        ], 1);

        $this->assertEquals('tt1234567', $q->movieIsbn);
        $this->assertEquals('review', $q->noteType);
    }

    #[Test]
    public function get_movie_notes_from_array_snake_case(): void
    {
        $q = GetMovieNotesQuery::fromArray([
            'movie_isbn' => 'tt9876543',
            'note_type' => 'note',
        ], 2);

        $this->assertEquals('tt9876543', $q->movieIsbn);
        $this->assertEquals('note', $q->noteType);
    }

    #[Test]
    public function get_movie_notes_from_array_isbn_fallback(): void
    {
        $q = GetMovieNotesQuery::fromArray([
            'isbn' => 'tt1111111',
        ], 1);

        $this->assertEquals('tt1111111', $q->movieIsbn);
    }

    // ═══════════════════════════════════════
    // GetTrendingMoviesQuery
    // ═══════════════════════════════════════

    #[Test]
    public function get_trending_movies_defaults(): void
    {
        $q = new GetTrendingMoviesQuery();

        $this->assertSame(20, $q->limit);
        $this->assertSame(90, $q->daysWindow);
        $this->assertNull($q->userId);
    }

    #[Test]
    public function get_trending_movies_create(): void
    {
        $q = GetTrendingMoviesQuery::create(10, 30, 5);

        $this->assertSame(10, $q->limit);
        $this->assertSame(30, $q->daysWindow);
        $this->assertSame(5, $q->userId);
    }

    #[Test]
    public function get_trending_movies_from_array(): void
    {
        $q = GetTrendingMoviesQuery::fromArray([
            'limit' => 5,
            'daysWindow' => 60,
            'userId' => 3,
        ]);

        $this->assertSame(5, $q->limit);
        $this->assertSame(60, $q->daysWindow);
        $this->assertSame(3, $q->userId);
    }
}
