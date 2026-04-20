<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Book\Mappers;

use App\Infrastructure\Persistence\Book\Mappers\EditionDataMapper;
use App\Domain\Model\Edition;
use App\Domain\Model\ValueObjects\ISBN;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EditionDataMapperTest extends TestCase
{
    private EditionDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new EditionDataMapper();
    }

    private function fullDbRow(): array
    {
        return [
            'edition_id' => 42,
            'work_id' => 10,
            'openlibrary_edition_key' => '/books/OL123M',
            'title' => 'Test Edition',
            'isbn_13' => '9783161484100',
            'isbn_10' => '0451524934',
            'google_books_id' => 'abc123',
            'subtitle' => 'A Subtitle',
            'publisher' => 'Test Publisher',
            'publish_date' => '2024-01-15',
            'publish_year' => 2024,
            'publish_place' => 'New York',
            'format' => 'hardcover',
            'pages' => 350,
            'description' => 'Edition description',
            'languages' => json_encode(['en', 'es']),
            'illustrators' => json_encode(['Artist One']),
            'translators' => json_encode(['Translator One']),
            'cover_url_small' => 'https://small.jpg',
            'cover_url_medium' => 'https://medium.jpg',
            'cover_url_large' => 'https://large.jpg',
            'covers' => json_encode([12345]),
            'series' => json_encode(['Lord of the Rings']),
            'series_position' => '1',
            'preview_link' => 'https://preview.com',
            'info_link' => 'https://info.com',
        ];
    }

    // ── toDomain ──

    #[Test]
    public function to_domain_maps_all_fields(): void
    {
        $edition = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(Edition::class, $edition);
        $this->assertSame(42, $edition->getEditionId());
        $this->assertSame(10, $edition->getWorkId());
        $this->assertEquals('/books/OL123M', $edition->getOpenlibraryEditionKey());
        $this->assertEquals('Test Edition', $edition->getTitle());
        $this->assertEquals('9783161484100', $edition->getIsbn13()->toString());
        $this->assertEquals('0451524934', $edition->getIsbn10()->toString());
        $this->assertEquals('abc123', $edition->getGoogleBooksId());
        $this->assertEquals('A Subtitle', $edition->getSubtitle());
        $this->assertEquals('Test Publisher', $edition->getPublisher());
        $this->assertEquals('2024-01-15', $edition->getPublishDate());
        $this->assertSame(2024, $edition->getPublishYear());
        $this->assertEquals('New York', $edition->getPublishPlace());
        $this->assertEquals('hardcover', $edition->getFormat());
        $this->assertSame(350, $edition->getPages());
        $this->assertEquals('Edition description', $edition->getDescription());
        $this->assertEquals(['en', 'es'], $edition->getLanguages());
        $this->assertEquals(['Artist One'], $edition->getIllustrators());
        $this->assertEquals(['Translator One'], $edition->getTranslators());
        $this->assertEquals('https://small.jpg', $edition->getCoverUrlSmall());
        $this->assertEquals('https://medium.jpg', $edition->getCoverUrlMedium());
        $this->assertEquals('https://large.jpg', $edition->getCoverUrlLarge());
        $this->assertEquals([12345], $edition->getCovers());
        $array = $edition->toArray();
        $this->assertEquals('https://preview.com', $array['preview_link']);
        $this->assertEquals('https://info.com', $array['info_link']);
    }

    #[Test]
    public function to_domain_minimal_row(): void
    {
        $row = [
            'work_id' => 5,
            'openlibrary_edition_key' => '/books/OL999M',
            'title' => 'Minimal Edition',
        ];

        $edition = $this->mapper->toDomain($row);

        $this->assertSame(5, $edition->getWorkId());
        $this->assertEquals('Minimal Edition', $edition->getTitle());
        $this->assertNull($edition->getEditionId());
        $this->assertNull($edition->getIsbn13());
        $this->assertNull($edition->getIsbn10());
        $this->assertNull($edition->getGoogleBooksId());
        $this->assertNull($edition->getSubtitle());
        $this->assertNull($edition->getPublisher());
        $this->assertNull($edition->getPages());
        $this->assertNull($edition->getDescription());
    }

    #[Test]
    public function to_domain_handles_json_arrays_already_decoded(): void
    {
        $row = $this->fullDbRow();
        $row['languages'] = ['fr'];
        $row['illustrators'] = ['Artist'];
        $row['translators'] = ['Trans'];
        $row['covers'] = [111];
        $row['series'] = ['Series A'];

        $edition = $this->mapper->toDomain($row);

        $this->assertEquals(['fr'], $edition->getLanguages());
        $this->assertEquals(['Artist'], $edition->getIllustrators());
        $this->assertEquals(['Trans'], $edition->getTranslators());
        $this->assertEquals([111], $edition->getCovers());
    }

    // ── toDatabase ──

    #[Test]
    public function to_database_maps_all_fields(): void
    {
        $edition = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toDatabase($edition);

        $this->assertSame(42, $data['edition_id']);
        $this->assertSame(10, $data['work_id']);
        $this->assertEquals('/books/OL123M', $data['openlibrary_edition_key']);
        $this->assertEquals('9783161484100', $data['isbn_13']);
        $this->assertEquals('0451524934', $data['isbn_10']);
        $this->assertEquals('Test Edition', $data['title']);
        $this->assertEquals('A Subtitle', $data['subtitle']);
        $this->assertEquals('Test Publisher', $data['publisher']);
        $this->assertEquals('2024-01-15', $data['publish_date']);
        $this->assertSame(2024, $data['publish_year']);
        $this->assertEquals('hardcover', $data['format']);
        $this->assertSame(350, $data['pages']);
        $this->assertEquals('Edition description', $data['description']);
        // JSON-encoded arrays
        $this->assertJson($data['languages']);
        $this->assertJson($data['illustrators']);
        $this->assertJson($data['translators']);
        $this->assertJson($data['covers']);
    }

    #[Test]
    public function to_database_null_isbn_fields(): void
    {
        $row = [
            'work_id' => 5,
            'openlibrary_edition_key' => '/books/OL999M',
            'title' => 'No ISBN',
        ];

        $edition = $this->mapper->toDomain($row);
        $data = $this->mapper->toDatabase($edition);

        $this->assertNull($data['isbn_13']);
        $this->assertNull($data['isbn_10']);
        $this->assertNull($data['languages']);
        $this->assertNull($data['illustrators']);
        $this->assertNull($data['translators']);
        $this->assertNull($data['covers']);
    }

    // ── Round-trip ──

    #[Test]
    public function round_trip_preserves_key_data(): void
    {
        $original = $this->fullDbRow();
        $edition = $this->mapper->toDomain($original);
        $data = $this->mapper->toDatabase($edition);

        $this->assertSame((int) $original['edition_id'], $data['edition_id']);
        $this->assertSame((int) $original['work_id'], $data['work_id']);
        $this->assertEquals($original['title'], $data['title']);
        $this->assertEquals($original['isbn_13'], $data['isbn_13']);
        $this->assertEquals($original['isbn_10'], $data['isbn_10']);
        $this->assertEquals($original['publisher'], $data['publisher']);
        $this->assertSame((int) $original['pages'], $data['pages']);
    }
}
