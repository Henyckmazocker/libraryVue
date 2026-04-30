<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Movie\Mappers;

use App\Infrastructure\Persistence\Movie\Mappers\MovieDataMapper;
use App\Domain\Model\Movie;
use App\Domain\Model\ValueObjects\MovieIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MovieDataMapperTest extends TestCase
{
    private MovieDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new MovieDataMapper();
    }

    private function fullDbRow(): array
    {
        return [
            'isbn' => 'tt1234567',
            'title' => 'Inception',
            'original_title' => 'Inception (Original)',
            'director' => 'Christopher Nolan',
            'coverUrl' => 'https://example.com/inception.jpg',
            'rating' => 4.5,
            'user_rating' => 5.0,
            'description' => 'A mind-bending thriller',
            'genres' => json_encode(['Sci-Fi', 'Thriller']),
            'user_statuses' => 'watched, owned',
            'user_added_at' => '2024-02-15 10:00:00',
        ];
    }

    // ── toDomain ──

    #[Test]
    public function to_domain_maps_all_fields(): void
    {
        $movie = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(Movie::class, $movie);
        $this->assertEquals('tt1234567', $movie->getId()->toString());
        $this->assertEquals('Inception', $movie->getTitle());
        $this->assertEquals('Inception (Original)', $movie->getOriginalTitle());
        $this->assertEquals('Christopher Nolan', $movie->getDirector());
        $this->assertEquals('https://example.com/inception.jpg', $movie->getCoverUrl());
        $this->assertNotNull($movie->getRating());
        $this->assertSame(4.5, $movie->getRating()->toFloat());
        $this->assertNotNull($movie->getUserRating());
        $this->assertSame(5.0, $movie->getUserRating()->toFloat());
        $this->assertEquals('A mind-bending thriller', $movie->getDescription());
    }

    #[Test]
    public function to_domain_movie_identifier_from_isbn_field(): void
    {
        $movie = $this->mapper->toDomain($this->fullDbRow());
        $this->assertEquals('tt1234567', $movie->getId()->toString());
    }

    #[Test]
    public function to_domain_parses_user_statuses_comma_string(): void
    {
        $movie = $this->mapper->toDomain($this->fullDbRow());

        $statuses = $movie->getUserStatuses();
        $this->assertIsArray($statuses);
        $this->assertContains('watched', $statuses);
        $this->assertContains('owned', $statuses);
    }

    #[Test]
    public function to_domain_parses_user_statuses_array(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = ['watching', 'wishlist'];

        $movie = $this->mapper->toDomain($row);
        $this->assertEquals(['watching', 'wishlist'], $movie->getUserStatuses());
    }

    #[Test]
    public function to_domain_null_user_statuses(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = null;

        $movie = $this->mapper->toDomain($row);
        $this->assertEmpty($movie->getUserStatuses());
    }

    #[Test]
    public function to_domain_empty_user_statuses(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = '';

        $movie = $this->mapper->toDomain($row);
        $this->assertEmpty($movie->getUserStatuses());
    }

    #[Test]
    public function to_domain_parses_genres(): void
    {
        $movie = $this->mapper->toDomain($this->fullDbRow());

        $genres = $movie->getGenres();
        $this->assertNotNull($genres);
        $this->assertCount(2, $genres);
        $this->assertInstanceOf(Genre::class, $genres[0]);
    }

    #[Test]
    public function to_domain_null_optional_fields(): void
    {
        $row = [
            'isbn' => 'tt0000001',
            'title' => 'Minimal Movie',
        ];

        $movie = $this->mapper->toDomain($row);

        $this->assertNull($movie->getOriginalTitle());
        $this->assertNull($movie->getDirector());
        $this->assertNull($movie->getCoverUrl());
        $this->assertNull($movie->getRating());
        $this->assertNull($movie->getUserRating());
        $this->assertNull($movie->getDescription());
    }

    #[Test]
    public function to_domain_timestamp_from_user_added_at(): void
    {
        $movie = $this->mapper->toDomain($this->fullDbRow());
        $this->assertNotNull($movie->getAddedTimestamp());
    }

    #[Test]
    public function to_domain_timestamp_defaults_when_missing(): void
    {
        $row = [
            'isbn' => 'tt0000001',
            'title' => 'Test',
        ];

        $movie = $this->mapper->toDomain($row);
        $this->assertNotNull($movie->getAddedTimestamp());
    }

    #[Test]
    public function to_domain_without_ratings(): void
    {
        $row = [
            'isbn' => 'tt0000001',
            'title' => 'Test',
        ];

        $movie = $this->mapper->toDomain($row);
        $this->assertNull($movie->getRating());
        $this->assertNull($movie->getUserRating());
    }

    // ── toPersistence ──

    #[Test]
    public function to_persistence_maps_core_fields(): void
    {
        $movie = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toPersistence($movie);

        $this->assertArrayHasKey('isbn', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertEquals('Inception', $data['title']);
        $this->assertArrayHasKey('addedTimestamp', $data);
    }

    #[Test]
    public function to_persistence_null_optional_fields(): void
    {
        $row = [
            'isbn' => 'tt0000001',
            'title' => 'Minimal',
        ];

        $movie = $this->mapper->toDomain($row);
        $data = $this->mapper->toPersistence($movie);

        $this->assertNull($data['original_title']);
        $this->assertNull($data['director']);
        $this->assertNull($data['coverUrl']);
        $this->assertNull($data['description']);
    }

    // ── toDomainCollection ──

    #[Test]
    public function to_domain_collection_maps_multiple_rows(): void
    {
        $rows = [
            array_merge($this->fullDbRow(), ['isbn' => 'tt001', 'title' => 'Movie 1']),
            array_merge($this->fullDbRow(), ['isbn' => 'tt002', 'title' => 'Movie 2']),
        ];

        $movies = $this->mapper->toDomainCollection($rows);

        $this->assertCount(2, $movies);
        $this->assertInstanceOf(Movie::class, $movies[0]);
        $this->assertInstanceOf(Movie::class, $movies[1]);
        $this->assertEquals('Movie 1', $movies[0]->getTitle());
        $this->assertEquals('Movie 2', $movies[1]->getTitle());
    }

    #[Test]
    public function to_domain_collection_empty(): void
    {
        $movies = $this->mapper->toDomainCollection([]);
        $this->assertEmpty($movies);
    }

    // ── Round-trip ──

    #[Test]
    public function round_trip_preserves_core_data(): void
    {
        $original = $this->fullDbRow();
        $movie = $this->mapper->toDomain($original);
        $data = $this->mapper->toPersistence($movie);

        $this->assertEquals($original['title'], $data['title']);
        $this->assertEquals($original['original_title'], $data['original_title']);
        $this->assertEquals($original['director'], $data['director']);
        $this->assertEquals($original['coverUrl'], $data['coverUrl']);
    }
}
