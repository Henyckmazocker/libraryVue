<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\Book;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BookTest extends TestCase
{
    private const ALLOWED_STATUSES = ['reading', 'completed', 'to-read', 'owned'];

    private function makeBook(array $overrides = []): Book
    {
        $defaults = [
            'isbn' => ISBN::fromString('9783161484100'),
            'title' => 'Test Book',
            'author' => 'Test Author',
            'publisher' => 'Publisher',
            'publicationYear' => 2024,
            'coverUrl' => 'https://cover.jpg',
            'rating' => Rating::fromFloat(4.0),
            'userRating' => Rating::fromFloat(3.5),
            'pages' => 300,
            'description' => 'A test book',
            'userStatuses' => ['reading'],
            'allowedStatuses' => self::ALLOWED_STATUSES,
        ];
        $d = array_merge($defaults, $overrides);
        return new Book(
            $d['isbn'], $d['title'], $d['author'], $d['publisher'],
            $d['publicationYear'], $d['coverUrl'], $d['rating'], $d['userRating'],
            $d['pages'], $d['description'], $d['userStatuses'], $d['allowedStatuses'],
            $d['addedTimestamp'] ?? null, $d['currentPage'] ?? null,
            $d['tags'] ?? null, $d['allowedTags'] ?? null,
            $d['genre'] ?? null, $d['language'] ?? null,
            $d['activeReadingSessionId'] ?? null,
            $d['totalSessionsCompleted'] ?? null,
            $d['currentSessionNumber'] ?? null,
            $d['sessionStartedAt'] ?? null,
            $d['lastSessionCompletedAt'] ?? null,
            $d['personalNotes'] ?? null,
            $d['consumedAt'] ?? null
        );
    }

    // ── Constructor validation ──

    #[Test]
    public function creates_book_with_required_fields(): void
    {
        $book = $this->makeBook();
        $this->assertEquals('9783161484100', $book->getIsbn()->toString());
        $this->assertEquals('Test Book', $book->getTitle());
        $this->assertEquals('Test Author', $book->getAuthor());
        $this->assertSame(300, $book->getPages());
        $this->assertEquals(['reading'], $book->getUserStatuses());
    }

    #[Test]
    public function throws_on_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Title cannot be empty');
        $this->makeBook(['title' => '']);
    }

    #[Test]
    public function throws_on_zero_pages(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pages must be a positive integer');
        $this->makeBook(['pages' => 0]);
    }

    #[Test]
    public function throws_on_negative_pages(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeBook(['pages' => -1]);
    }

    #[Test]
    public function allows_null_pages(): void
    {
        $book = $this->makeBook(['pages' => null]);
        $this->assertNull($book->getPages());
    }

    #[Test]
    public function throws_on_negative_current_page(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Current page must be a non-negative');
        $this->makeBook(['currentPage' => -1]);
    }

    #[Test]
    public function throws_on_current_page_exceeding_total(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Current page cannot be greater than total pages');
        $this->makeBook(['pages' => 100, 'currentPage' => 150]);
    }

    #[Test]
    public function allows_current_page_equal_to_total(): void
    {
        $book = $this->makeBook(['pages' => 100, 'currentPage' => 100]);
        $this->assertSame(100, $book->getCurrentPage());
    }

    #[Test]
    public function throws_on_invalid_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status');
        $this->makeBook(['userStatuses' => ['invalid-status']]);
    }

    #[Test]
    public function allows_empty_user_statuses(): void
    {
        $book = $this->makeBook(['userStatuses' => []]);
        $this->assertEmpty($book->getUserStatuses());
    }

    #[Test]
    public function deduplicates_user_statuses(): void
    {
        $book = $this->makeBook(['userStatuses' => ['reading', 'reading', 'owned']]);
        $this->assertCount(2, $book->getUserStatuses());
    }

    // ── Default values ──

    #[Test]
    public function current_page_defaults_to_zero(): void
    {
        $book = $this->makeBook();
        $this->assertSame(0, $book->getCurrentPage());
    }

    #[Test]
    public function added_timestamp_defaults_to_now(): void
    {
        $book = $this->makeBook();
        $this->assertTrue($book->getAddedTimestamp()->isToday());
    }

    #[Test]
    public function total_sessions_defaults_to_zero(): void
    {
        $book = $this->makeBook();
        $arr = $book->toArray();
        $this->assertSame(0, $arr['total_sessions_completed']);
    }

    // ── Getters for optional fields ──

    #[Test]
    public function null_optional_fields(): void
    {
        $book = $this->makeBook([
            'author' => null, 'publisher' => null, 'publicationYear' => null,
            'coverUrl' => null, 'rating' => null, 'userRating' => null,
            'description' => null, 'language' => null,
        ]);

        $this->assertNull($book->getAuthor());
        $this->assertNull($book->getPublisher());
        $this->assertNull($book->getPublicationYear());
        $this->assertNull($book->getCoverUrl());
        $this->assertNull($book->getRating());
        $this->assertNull($book->getUserRating());
        $this->assertNull($book->getDescription());
        $this->assertNull($book->getLanguage());
        $this->assertNull($book->getPublicationDate());
    }

    #[Test]
    public function publication_date_backward_compat(): void
    {
        $book = $this->makeBook(['publicationYear' => 2020]);
        $this->assertEquals('2020', $book->getPublicationDate());
    }

    // ── Genre ──

    #[Test]
    public function genre_as_single_value(): void
    {
        $book = $this->makeBook(['genre' => Genre::fromString('Fiction')]);
        $this->assertEquals('Fiction', $book->getGenre()->toString());
        $this->assertEquals(['Fiction'], $book->getGenres());
    }

    #[Test]
    public function genres_returns_null_when_no_genre(): void
    {
        $book = $this->makeBook(); // no genre
        $this->assertNull($book->getGenre());
        $this->assertNull($book->getGenres());
    }

    // ── Setters ──

    #[Test]
    public function set_pages_validates(): void
    {
        $book = $this->makeBook();
        $book->setPages(500);
        $this->assertSame(500, $book->getPages());
    }

    #[Test]
    public function set_pages_throws_on_zero(): void
    {
        $book = $this->makeBook();
        $this->expectException(InvalidArgumentException::class);
        $book->setPages(0);
    }

    #[Test]
    public function set_current_page_validates(): void
    {
        $book = $this->makeBook(['pages' => 200]);
        $book->setCurrentPage(100);
        $this->assertSame(100, $book->getCurrentPage());
    }

    #[Test]
    public function set_current_page_throws_on_exceeding(): void
    {
        $book = $this->makeBook(['pages' => 200]);
        $this->expectException(InvalidArgumentException::class);
        $book->setCurrentPage(201);
    }

    #[Test]
    public function set_user_statuses_throws_on_empty(): void
    {
        $book = $this->makeBook();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one user status');
        $book->setUserStatuses([]);
    }

    // ── toArray ──

    #[Test]
    public function to_array_contains_all_fields(): void
    {
        $book = $this->makeBook([
            'genre' => Genre::fromString('Fantasy'),
            'language' => 'en',
            'personalNotes' => 'Great book',
            'consumedAt' => '2024-06-15',
            'tags' => ['favorite'],
        ]);

        $arr = $book->toArray();

        $this->assertEquals('9783161484100', $arr['isbn']);
        $this->assertEquals('Test Book', $arr['title']);
        $this->assertEquals('Test Author', $arr['author']);
        $this->assertEquals('Publisher', $arr['publisher']);
        $this->assertSame(2024, $arr['publication_year']);
        $this->assertEquals('2024', $arr['publicationDate']); // backward compat
        $this->assertEquals('https://cover.jpg', $arr['coverUrl']);
        $this->assertEquals('https://cover.jpg', $arr['cover_url']); // alias
        $this->assertEquals('en', $arr['language']);
        $this->assertSame(4.0, $arr['rating']);
        $this->assertSame(3.5, $arr['user_rating']);
        $this->assertSame(300, $arr['pages']);
        $this->assertEquals('A test book', $arr['description']);
        $this->assertSame(0, $arr['currentPage']);
        $this->assertSame(0, $arr['current_page']); // alias
        $this->assertEquals(['reading'], $arr['userStatuses']);
        $this->assertEquals('Fantasy', $arr['genre']);
        $this->assertEquals(['Fantasy'], $arr['genres']); // backward compat
        $this->assertEquals(['favorite'], $arr['tags']);
        $this->assertEquals('Great book', $arr['personal_notes']);
        $this->assertEquals('2024-06-15', $arr['consumed_at']);
    }

    // ── fromArray ──

    #[Test]
    public function from_array_creates_book(): void
    {
        $data = [
            'isbn' => '9783161484100',
            'title' => 'From Array Book',
            'author' => 'Author',
            'publisher' => 'Pub',
            'publication_year' => 2023,
            'coverUrl' => 'https://cover.jpg',
            'rating' => 4.5,
            'user_rating' => 3.0,
            'pages' => 200,
            'description' => 'Desc',
            'userStatuses' => ['reading'],
            'allowedStatuses' => self::ALLOWED_STATUSES,
            'genre' => 'Fiction',
            'language' => 'es',
            'tags' => ['tag1'],
        ];

        $book = Book::fromArray($data);
        $this->assertEquals('9783161484100', $book->getIsbn()->toString());
        $this->assertEquals('From Array Book', $book->getTitle());
        $this->assertSame(4.5, $book->getRating()->toFloat());
        $this->assertSame(3.0, $book->getUserRating()->toFloat());
        $this->assertSame(2023, $book->getPublicationYear());
        $this->assertEquals('Fiction', $book->getGenre()->toString());
    }

    #[Test]
    public function from_array_handles_genres_array_fallback(): void
    {
        $data = [
            'isbn' => '9783161484100',
            'title' => 'Book',
            'userStatuses' => [],
            'allowedStatuses' => [],
            'genres' => ['Fantasy', 'Adventure'],
        ];

        $book = Book::fromArray($data);
        $this->assertEquals('Fantasy', $book->getGenre()->toString());
    }

    #[Test]
    public function from_array_handles_publication_date_fallback(): void
    {
        $data = [
            'isbn' => '9783161484100',
            'title' => 'Book',
            'userStatuses' => [],
            'allowedStatuses' => [],
            'publicationDate' => '2020',
        ];

        $book = Book::fromArray($data);
        $this->assertSame(2020, $book->getPublicationYear());
    }

    #[Test]
    public function from_array_handles_cover_url_snake_case(): void
    {
        $data = [
            'isbn' => '9783161484100',
            'title' => 'Book',
            'userStatuses' => [],
            'allowedStatuses' => [],
            'cover_url' => 'https://cover-snake.jpg',
        ];

        $book = Book::fromArray($data);
        $this->assertEquals('https://cover-snake.jpg', $book->getCoverUrl());
    }

    // ── Round-trip ──

    #[Test]
    public function from_array_to_array_preserves_data(): void
    {
        $data = [
            'isbn' => '9783161484100',
            'title' => 'Round Trip',
            'author' => 'Author',
            'publisher' => 'Publisher',
            'publication_year' => 2024,
            'coverUrl' => 'https://cover.jpg',
            'rating' => 4.0,
            'user_rating' => 3.5,
            'pages' => 300,
            'description' => 'Description',
            'userStatuses' => ['reading'],
            'allowedStatuses' => self::ALLOWED_STATUSES,
            'genre' => 'Fantasy',
            'language' => 'en',
        ];

        $arr = Book::fromArray($data)->toArray();

        $this->assertEquals('9783161484100', $arr['isbn']);
        $this->assertEquals('Round Trip', $arr['title']);
        $this->assertSame(4.0, $arr['rating']);
        $this->assertSame(3.5, $arr['user_rating']);
        $this->assertEquals('Fantasy', $arr['genre']);
        $this->assertEquals('en', $arr['language']);
    }
}
