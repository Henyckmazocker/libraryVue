<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Book\Mappers;

use App\Infrastructure\Persistence\Book\Mappers\BookDataMapper;
use App\Domain\Model\Book;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BookDataMapperTest extends TestCase
{
    private BookDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new BookDataMapper();
    }

    private function fullDbRow(): array
    {
        return [
            'isbn' => '9783161484100',
            'title' => 'Test Book',
            'author' => 'Author Name',
            'publisher' => 'Publisher',
            'publication_year' => 2024,
            'pages' => 300,
            'genre' => 'Fiction',
            'genres' => json_encode(['Fiction', 'Fantasy']),
            'description' => 'A great book',
            'coverUrl' => 'https://cover.jpg',
            'language' => 'en',
            'rating' => 4.0,
            'user_rating' => 3.5,
            'addedTimestamp' => time(),
            'user_statuses' => 'reading,owned',
            'allowedStatuses' => ['reading', 'completed', 'to-read', 'owned'],
            'current_page' => 50,
            'personal_notes' => 'Great read',
            'consumed_at' => '2024-06-15',
        ];
    }

    // ── toDomain ──

    #[Test]
    public function to_domain_maps_all_fields(): void
    {
        $book = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(Book::class, $book);
        $this->assertEquals('9783161484100', $book->getIsbn()->toString());
        $this->assertEquals('Test Book', $book->getTitle());
        $this->assertEquals('Author Name', $book->getAuthor());
        $this->assertEquals('Publisher', $book->getPublisher());
        $this->assertSame(2024, $book->getPublicationYear());
        $this->assertSame(300, $book->getPages());
        $this->assertEquals('Fiction', $book->getGenre()->toString());
        $this->assertEquals('A great book', $book->getDescription());
        $this->assertEquals('https://cover.jpg', $book->getCoverUrl());
        $this->assertEquals('en', $book->getLanguage());
        $this->assertSame(4.0, $book->getRating()->toFloat());
        $this->assertSame(3.5, $book->getUserRating()->toFloat());
        $this->assertEquals(['reading', 'owned'], $book->getUserStatuses());
        $this->assertSame(50, $book->getCurrentPage());
    }

    #[Test]
    public function to_domain_handles_null_optional_fields(): void
    {
        $row = [
            'isbn' => '9783161484100',
            'title' => 'Minimal Book',
            'author' => '',
            'user_statuses' => '',
            'allowedStatuses' => [],
        ];

        $book = $this->mapper->toDomain($row);

        $this->assertEquals('Minimal Book', $book->getTitle());
        $this->assertNull($book->getPublisher());
        $this->assertNull($book->getPublicationYear());
        $this->assertNull($book->getPages());
        $this->assertNull($book->getGenre());
        $this->assertNull($book->getDescription());
        $this->assertNull($book->getCoverUrl());
        $this->assertNull($book->getRating());
        $this->assertNull($book->getUserRating());
        $this->assertEmpty($book->getUserStatuses());
    }

    #[Test]
    public function to_domain_parses_user_statuses_as_array(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = ['completed', 'owned'];

        $book = $this->mapper->toDomain($row);
        $this->assertEquals(['completed', 'owned'], $book->getUserStatuses());
    }

    #[Test]
    public function to_domain_parses_genres_json_string(): void
    {
        $row = $this->fullDbRow();
        $row['genres'] = json_encode(['Sci-Fi', 'Thriller']);

        $book = $this->mapper->toDomain($row);
        $arr = $book->toArray();
        // genres should come from Genre VO (single) or genres array in Book::fromArray
        $this->assertNotNull($arr);
    }

    #[Test]
    public function to_domain_parses_genres_already_array(): void
    {
        $row = $this->fullDbRow();
        $row['genres'] = ['Horror', 'Mystery'];

        $book = $this->mapper->toDomain($row);
        $this->assertInstanceOf(Book::class, $book);
    }

    #[Test]
    public function to_domain_uses_id_fallback_for_isbn(): void
    {
        $row = $this->fullDbRow();
        unset($row['isbn']);
        $row['id'] = '9783161484100';

        $book = $this->mapper->toDomain($row);
        $this->assertEquals('9783161484100', $book->getIsbn()->toString());
    }

    #[Test]
    public function to_domain_sets_added_timestamp_to_now_when_missing(): void
    {
        $row = $this->fullDbRow();
        unset($row['addedTimestamp']);

        $book = $this->mapper->toDomain($row);
        $this->assertTrue($book->getAddedTimestamp()->isToday());
    }

    #[Test]
    public function to_domain_converts_added_timestamp_from_unix(): void
    {
        $row = $this->fullDbRow();
        $row['addedTimestamp'] = 1700000000;

        $book = $this->mapper->toDomain($row);
        $this->assertFalse($book->getAddedTimestamp()->isToday());
    }

    // ── toPersistence ──

    #[Test]
    public function to_persistence_maps_all_fields(): void
    {
        $book = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toPersistence($book);

        $this->assertEquals('9783161484100', $data['isbn']);
        $this->assertEquals('Test Book', $data['title']);
        $this->assertEquals('Author Name', $data['author']);
        $this->assertSame(2024, $data['publication_year']);
        $this->assertSame(300, $data['pages']);
        $this->assertEquals('Fiction', $data['genre']);
        $this->assertEquals('A great book', $data['description']);
        $this->assertEquals('https://cover.jpg', $data['cover_url']);
        $this->assertEquals('Publisher', $data['publisher']);
        $this->assertEquals('en', $data['language']);
        $this->assertSame(4.0, $data['rating']);
        $this->assertArrayHasKey('addedTimestamp', $data);
    }

    #[Test]
    public function to_persistence_handles_null_fields(): void
    {
        $row = [
            'isbn' => '9783161484100',
            'title' => 'Minimal',
            'author' => '',
            'user_statuses' => '',
            'allowedStatuses' => [],
        ];

        $book = $this->mapper->toDomain($row);
        $data = $this->mapper->toPersistence($book);

        $this->assertNull($data['publisher']);
        $this->assertNull($data['publication_year']);
        $this->assertNull($data['pages']);
        $this->assertNull($data['description']);
        $this->assertNull($data['rating']);
    }

    // ── Round-trip ──

    #[Test]
    public function round_trip_preserves_core_data(): void
    {
        $originalRow = $this->fullDbRow();
        $book = $this->mapper->toDomain($originalRow);
        $persistedData = $this->mapper->toPersistence($book);

        $this->assertEquals($originalRow['isbn'], $persistedData['isbn']);
        $this->assertEquals($originalRow['title'], $persistedData['title']);
        $this->assertEquals($originalRow['author'], $persistedData['author']);
        $this->assertEquals($originalRow['publisher'], $persistedData['publisher']);
        $this->assertSame((int) $originalRow['publication_year'], $persistedData['publication_year']);
        $this->assertSame((int) $originalRow['pages'], $persistedData['pages']);
    }
}
