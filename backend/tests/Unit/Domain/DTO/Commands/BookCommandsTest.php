<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Commands;

use App\Domain\DTO\Commands\AddBookCommand;
use App\Domain\DTO\Commands\DeleteBookCommand;
use App\Domain\DTO\Commands\EditUserBookCommand;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use App\Domain\DTO\Commands\UpdateBookStatusesCommand;
use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\ValueObjects\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BookCommandsTest extends TestCase
{
    // ═══════════════════════════════════════
    // AddBookCommand
    // ═══════════════════════════════════════

    #[Test]
    public function add_book_constructor_sets_all_properties(): void
    {
        $isbn = ISBN::fromString('9783161484100');
        $rating = Rating::fromFloat(4.0);
        $userRating = Rating::fromFloat(3.5);

        $cmd = new AddBookCommand(
            isbn: $isbn,
            title: 'Test Book',
            userId: 1,
            statuses: ['reading'],
            author: 'Author',
            publisher: 'Pub',
            publicationYear: 2024,
            coverUrl: 'https://cover.jpg',
            rating: $rating,
            userRating: $userRating,
            pages: 300,
            description: 'Desc',
            genres: ['Fiction'],
            language: 'en'
        );

        $this->assertSame($isbn, $cmd->isbn);
        $this->assertEquals('Test Book', $cmd->title);
        $this->assertSame(1, $cmd->userId);
        $this->assertEquals(['reading'], $cmd->statuses);
        $this->assertEquals('Author', $cmd->author);
        $this->assertEquals('Pub', $cmd->publisher);
        $this->assertSame(2024, $cmd->publicationYear);
        $this->assertEquals('https://cover.jpg', $cmd->coverUrl);
        $this->assertSame($rating, $cmd->rating);
        $this->assertSame($userRating, $cmd->userRating);
        $this->assertSame(300, $cmd->pages);
        $this->assertEquals('Desc', $cmd->description);
        $this->assertEquals(['Fiction'], $cmd->genres);
        $this->assertEquals('en', $cmd->language);
    }

    #[Test]
    public function add_book_defaults(): void
    {
        $cmd = new AddBookCommand(
            isbn: ISBN::fromString('9783161484100'),
            title: 'Test',
            userId: 1
        );

        $this->assertEquals([], $cmd->statuses);
        $this->assertNull($cmd->author);
        $this->assertNull($cmd->publisher);
        $this->assertNull($cmd->publicationYear);
        $this->assertNull($cmd->coverUrl);
        $this->assertNull($cmd->rating);
        $this->assertNull($cmd->userRating);
        $this->assertNull($cmd->pages);
        $this->assertNull($cmd->description);
        $this->assertEquals([], $cmd->genres);
        $this->assertNull($cmd->language);
    }

    #[Test]
    public function add_book_from_array_full(): void
    {
        $cmd = AddBookCommand::fromArray([
            'isbn' => '9783161484100',
            'title' => 'Test Book',
            'userStatuses' => ['reading'],
            'author' => 'Author',
            'publisher' => 'Pub',
            'publicationDate' => '2024',
            'coverUrl' => 'https://cover.jpg',
            'rating' => 4.0,
            'user_rating' => 3.5,
            'pages' => '300',
            'description' => 'Desc',
            'genres' => ['Fiction', 'Fantasy'],
            'language' => 'en',
        ], 1);

        $this->assertEquals('9783161484100', $cmd->isbn->toString());
        $this->assertEquals('Test Book', $cmd->title);
        $this->assertSame(1, $cmd->userId);
        $this->assertEquals(['reading'], $cmd->statuses);
        $this->assertSame(2024, $cmd->publicationYear);
        $this->assertSame(4.0, $cmd->rating->toFloat());
        $this->assertSame(3.5, $cmd->userRating->toFloat());
        $this->assertSame(300, $cmd->pages);
        $this->assertEquals(['Fiction', 'Fantasy'], $cmd->genres);
    }

    #[Test]
    public function add_book_from_array_genre_singular_fallback(): void
    {
        $cmd = AddBookCommand::fromArray([
            'isbn' => '9783161484100',
            'title' => 'Book',
            'genre' => 'Sci-Fi',
        ], 1);

        $this->assertEquals(['Sci-Fi'], $cmd->genres);
    }

    #[Test]
    public function add_book_from_array_cover_url_snake_case(): void
    {
        $cmd = AddBookCommand::fromArray([
            'isbn' => '9783161484100',
            'title' => 'Book',
            'cover_url' => 'https://snake-cover.jpg',
        ], 1);

        $this->assertEquals('https://snake-cover.jpg', $cmd->coverUrl);
    }

    #[Test]
    public function add_book_from_array_publication_year_snake_case(): void
    {
        $cmd = AddBookCommand::fromArray([
            'isbn' => '9783161484100',
            'title' => 'Book',
            'publication_year' => 2020,
        ], 1);

        $this->assertSame(2020, $cmd->publicationYear);
    }

    #[Test]
    public function add_book_from_array_description_array_joins(): void
    {
        $cmd = AddBookCommand::fromArray([
            'isbn' => '9783161484100',
            'title' => 'Book',
            'description' => ['Part one.', 'Part two.'],
        ], 1);

        $this->assertEquals('Part one. Part two.', $cmd->description);
    }

    #[Test]
    public function add_book_from_array_filters_empty_genres(): void
    {
        $cmd = AddBookCommand::fromArray([
            'isbn' => '9783161484100',
            'title' => 'Book',
            'genres' => ['Fiction', '', null, 'Fantasy'],
        ], 1);

        $this->assertCount(2, $cmd->genres);
    }

    #[Test]
    public function add_book_from_array_zero_rating_is_null(): void
    {
        $cmd = AddBookCommand::fromArray([
            'isbn' => '9783161484100',
            'title' => 'Book',
            'rating' => 0,
            'user_rating' => 0,
        ], 1);

        $this->assertNull($cmd->rating);
        $this->assertNull($cmd->userRating);
    }

    #[Test]
    public function add_book_to_array_contains_all_fields(): void
    {
        $cmd = AddBookCommand::fromArray([
            'isbn' => '9783161484100',
            'title' => 'Test',
            'author' => 'Author',
            'publisher' => 'Pub',
            'publicationDate' => '2024',
            'coverUrl' => 'https://cover.jpg',
            'rating' => 4.0,
            'user_rating' => 3.5,
            'pages' => 300,
            'description' => 'Desc',
            'userStatuses' => ['reading'],
            'genres' => ['Fiction'],
            'language' => 'en',
        ], 1);

        $arr = $cmd->toArray();

        $this->assertEquals('9783161484100', $arr['isbn']);
        $this->assertEquals('Test', $arr['title']);
        $this->assertEquals('https://cover.jpg', $arr['coverUrl']);
        $this->assertEquals('https://cover.jpg', $arr['cover_url']);
        $this->assertSame(2024, $arr['publication_year']);
        $this->assertSame(2024, $arr['publicationDate']);
        $this->assertEquals(['reading'], $arr['userStatuses']);
        $this->assertEquals(['Fiction'], $arr['genres']);
    }

    // ═══════════════════════════════════════
    // DeleteBookCommand
    // ═══════════════════════════════════════

    #[Test]
    public function delete_book_from_array(): void
    {
        $cmd = DeleteBookCommand::fromArray(['isbn' => '9783161484100'], 5);

        $this->assertEquals('9783161484100', $cmd->isbn->toString());
        $this->assertSame(5, $cmd->userId);
    }

    // ═══════════════════════════════════════
    // EditUserBookCommand
    // ═══════════════════════════════════════

    #[Test]
    public function edit_user_book_from_array_with_nested_data(): void
    {
        $cmd = EditUserBookCommand::fromArray([
            'isbn' => '9783161484100',
            'data' => [
                'personalRating' => 4.5,
                'statuses' => ['completed'],
                'current_page' => 150,
                'personal_notes' => 'Great',
                'consumed_at' => '2024-06-15',
            ],
            'tags' => [1, 2],
        ], 1);

        $this->assertEquals('9783161484100', $cmd->isbn->toString());
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(4.5, $cmd->userRating->toFloat());
        $this->assertEquals(['completed'], $cmd->statuses);
        $this->assertSame(150, $cmd->currentPage);
        $this->assertEquals('Great', $cmd->personalNotes);
        $this->assertEquals('2024-06-15', $cmd->consumedAt);
        $this->assertEquals([1, 2], $cmd->tags);
    }

    #[Test]
    public function edit_user_book_from_array_flat_data(): void
    {
        $cmd = EditUserBookCommand::fromArray([
            'isbn' => '9783161484100',
            'user_rating' => 3.0,
            'statuses' => ['reading'],
            'currentPage' => 50,
        ], 2);

        $this->assertSame(3.0, $cmd->userRating->toFloat());
        $this->assertEquals(['reading'], $cmd->statuses);
        $this->assertSame(50, $cmd->currentPage);
    }

    #[Test]
    public function edit_user_book_defaults(): void
    {
        $cmd = EditUserBookCommand::fromArray([
            'isbn' => '9783161484100',
        ], 1);

        $this->assertNull($cmd->userRating);
        $this->assertNull($cmd->statuses);
        $this->assertEquals([], $cmd->tags);
        $this->assertNull($cmd->currentPage);
        $this->assertNull($cmd->personalNotes);
        $this->assertNull($cmd->consumedAt);
    }

    // ═══════════════════════════════════════
    // UpdateBookRatingCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_book_rating_from_array(): void
    {
        $cmd = UpdateBookRatingCommand::fromArray([
            'isbn' => '9783161484100',
            'rating' => 4.5,
        ], 1);

        $this->assertEquals('9783161484100', $cmd->isbn->toString());
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(4.5, $cmd->rating->toFloat());
    }

    // ═══════════════════════════════════════
    // UpdateBookStatusesCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_book_statuses_from_array(): void
    {
        $cmd = UpdateBookStatusesCommand::fromArray([
            'isbn' => '9783161484100',
            'statuses' => ['reading', 'owned'],
        ], 1);

        $this->assertEquals('9783161484100', $cmd->isbn->toString());
        $this->assertSame(1, $cmd->userId);
        $this->assertEquals(['reading', 'owned'], $cmd->statuses);
    }

    #[Test]
    public function update_book_statuses_defaults_to_empty(): void
    {
        $cmd = UpdateBookStatusesCommand::fromArray([
            'isbn' => '9783161484100',
        ], 1);

        $this->assertEquals([], $cmd->statuses);
    }
}
