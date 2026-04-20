<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\Movie;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MovieTest extends TestCase
{
    private function makeMovie(array $overrides = []): Movie
    {
        $defaults = [
            'id' => MovieIdentifier::fromTmdb('550'),
            'title' => 'Fight Club',
            'originalTitle' => 'Fight Club',
            'director' => 'David Fincher',
            'coverUrl' => 'https://img.test/cover.jpg',
            'rating' => Rating::fromFloat(4.5),
            'userRating' => Rating::fromFloat(5.0),
            'description' => 'A great film',
            'userStatuses' => ['watched'],
            'addedTimestamp' => Timestamp::fromString('2024-06-15 10:00:00'),
            'allowedStatuses' => ['watched', 'to-watch'],
        ];
        $d = array_merge($defaults, $overrides);
        return new Movie(
            $d['id'], $d['title'], $d['originalTitle'], $d['director'],
            $d['coverUrl'], $d['rating'], $d['userRating'], $d['description'],
            $d['userStatuses'], $d['addedTimestamp'], $d['allowedStatuses'],
            $d['tags'] ?? null, $d['allowedTags'] ?? null, $d['genres'] ?? null
        );
    }

    // ── Constructor ──

    #[Test]
    public function creates_movie_with_all_fields(): void
    {
        $movie = $this->makeMovie([
            'genres' => [Genre::fromString('Drama'), Genre::fromString('Thriller')],
        ]);

        $this->assertEquals('Fight Club', $movie->getTitle());
        $this->assertEquals('Fight Club', $movie->getOriginalTitle());
        $this->assertEquals('David Fincher', $movie->getDirector());
        $this->assertSame(4.5, $movie->getRating()->toFloat());
        $this->assertSame(5.0, $movie->getUserRating()->toFloat());
        $this->assertCount(2, $movie->getGenres());
        $this->assertEquals(['watched'], $movie->getUserStatuses());
    }

    #[Test]
    public function creates_movie_with_null_optionals(): void
    {
        $movie = $this->makeMovie([
            'originalTitle' => null,
            'director' => null,
            'coverUrl' => null,
            'rating' => null,
            'userRating' => null,
            'description' => null,
            'genres' => null,
        ]);

        $this->assertNull($movie->getOriginalTitle());
        $this->assertNull($movie->getDirector());
        $this->assertNull($movie->getCoverUrl());
        $this->assertNull($movie->getRating());
        $this->assertNull($movie->getUserRating());
        $this->assertNull($movie->getDescription());
        $this->assertNull($movie->getGenres());
    }

    // ── Setters ──

    #[Test]
    public function setters_update_fields(): void
    {
        $movie = $this->makeMovie();
        $movie->setTitle('New Title');
        $movie->setDirector('New Director');
        $movie->setUserStatuses(['to-watch']);

        $this->assertEquals('New Title', $movie->getTitle());
        $this->assertEquals('New Director', $movie->getDirector());
        $this->assertEquals(['to-watch'], $movie->getUserStatuses());
    }

    #[Test]
    public function set_genres_updates_genres(): void
    {
        $movie = $this->makeMovie();
        $movie->setGenres([Genre::fromString('Comedy')]);
        $this->assertCount(1, $movie->getGenres());
    }

    // ── toArray ──

    #[Test]
    public function to_array_includes_aliases(): void
    {
        $movie = $this->makeMovie([
            'genres' => [Genre::fromString('Drama')],
        ]);
        $arr = $movie->toArray();

        $this->assertEquals('550', $arr['id']);
        $this->assertEquals('550', $arr['isbn']); // legacy alias
        $this->assertEquals('550', $arr['imdbID']); // frontend alias
        $this->assertEquals('Fight Club', $arr['title']);
        $this->assertEquals('Fight Club', $arr['originalTitle']);
        $this->assertSame(4.5, $arr['rating']);
        $this->assertSame(5.0, $arr['user_rating']);
        $this->assertEquals(['Drama'], $arr['genres']);
    }

    #[Test]
    public function to_array_handles_null_genres(): void
    {
        $movie = $this->makeMovie(['genres' => null]);
        $arr = $movie->toArray();
        $this->assertNull($arr['genres']);
    }

    // ── fromArray ──

    #[Test]
    public function from_array_creates_movie(): void
    {
        $data = [
            'id' => 'tt0137523',
            'title' => 'Fight Club',
            'originalTitle' => 'Fight Club',
            'director' => 'David Fincher',
            'coverUrl' => 'https://cover.jpg',
            'rating' => 4.5,
            'user_rating' => 5.0,
            'description' => 'Great',
            'userStatuses' => ['watched'],
            'genres' => ['Drama', 'Thriller'],
        ];

        $movie = Movie::fromArray($data);
        $this->assertEquals('tt0137523', $movie->getId()->toString());
        $this->assertTrue($movie->getId()->isImdb());
        $this->assertEquals('Fight Club', $movie->getTitle());
        $this->assertCount(2, $movie->getGenres());
    }

    #[Test]
    public function from_array_uses_isbn_fallback(): void
    {
        $data = [
            'isbn' => '12345', // TMDb-like ID via legacy isbn field
            'title' => 'Movie',
            'userStatuses' => ['to-watch'],
        ];
        $movie = Movie::fromArray($data);
        $this->assertEquals('12345', $movie->getId()->toString());
    }

    #[Test]
    public function from_array_throws_on_missing_id_and_isbn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ID and title are required');
        Movie::fromArray(['title' => 'Test', 'userStatuses' => ['watched']]);
    }

    #[Test]
    public function from_array_throws_on_missing_title(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Movie::fromArray(['id' => '550', 'userStatuses' => ['watched']]);
    }

    #[Test]
    public function from_array_throws_on_missing_statuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User statuses are required');
        Movie::fromArray(['id' => '550', 'title' => 'Test']);
    }

    // ── Round-trip ──

    #[Test]
    public function from_array_to_array_preserves_key_fields(): void
    {
        $data = [
            'id' => 'tt0137523',
            'title' => 'Fight Club',
            'originalTitle' => 'Fight Club EN',
            'director' => 'David Fincher',
            'coverUrl' => 'https://cover.jpg',
            'rating' => 4.0,
            'user_rating' => 3.5,
            'description' => 'Desc',
            'userStatuses' => ['watched'],
            'genres' => ['Drama'],
        ];

        $arr = Movie::fromArray($data)->toArray();
        $this->assertEquals('tt0137523', $arr['id']);
        $this->assertEquals('Fight Club', $arr['title']);
        $this->assertSame(4.0, $arr['rating']);
        $this->assertSame(3.5, $arr['user_rating']);
        $this->assertEquals(['Drama'], $arr['genres']);
    }
}
