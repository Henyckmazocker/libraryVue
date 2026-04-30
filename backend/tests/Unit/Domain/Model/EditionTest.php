<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\Edition;
use App\Domain\Model\ValueObjects\ISBN;
use App\Domain\Model\Work;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EditionTest extends TestCase
{
    // ── Constructor ──

    #[Test]
    public function creates_edition_with_required_fields(): void
    {
        $edition = new Edition(1, 'OL123M', 'Test Edition');
        $this->assertSame(1, $edition->getWorkId());
        $this->assertEquals('OL123M', $edition->getOpenlibraryEditionKey());
        $this->assertEquals('Test Edition', $edition->getTitle());
        $this->assertNull($edition->getEditionId());
        $this->assertEquals('openlibrary', $edition->getDataSource());
    }

    #[Test]
    public function creates_with_edition_id(): void
    {
        $edition = new Edition(1, 'OL123M', 'Title', 42);
        $this->assertSame(42, $edition->getEditionId());
    }

    #[Test]
    public function throws_on_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Title cannot be empty');
        new Edition(1, 'OL123M', '');
    }

    #[Test]
    public function initializes_nullable_properties_to_null(): void
    {
        $edition = new Edition(1, 'OL123M', 'Title');
        $this->assertNull($edition->getIsbn13());
        $this->assertNull($edition->getIsbn10());
        $this->assertNull($edition->getGoogleBooksId());
        $this->assertNull($edition->getSubtitle());
        $this->assertNull($edition->getPublisher());
        $this->assertNull($edition->getPublishDate());
        $this->assertNull($edition->getPublishYear());
        $this->assertNull($edition->getPages());
        $this->assertNull($edition->getFormat());
        $this->assertNull($edition->getDescription());
        $this->assertNull($edition->getLanguages());
        $this->assertNull($edition->getCoverUrlSmall());
        $this->assertNull($edition->getCoverUrlMedium());
        $this->assertNull($edition->getCoverUrlLarge());
    }

    // ── Setters ──

    #[Test]
    public function set_isbn_values(): void
    {
        $edition = new Edition(1, 'OL123M', 'Title');
        $isbn13 = ISBN::fromString('9783161484100');
        $isbn10 = ISBN::fromString('0306406152');

        $edition->setIsbn13($isbn13);
        $edition->setIsbn10($isbn10);

        $this->assertEquals('9783161484100', $edition->getIsbn13()->toString());
        $this->assertEquals('0306406152', $edition->getIsbn10()->toString());
    }

    #[Test]
    public function set_publish_info(): void
    {
        $edition = new Edition(1, 'OL123M', 'Title');
        $edition->setPublishInfo('January 2024', 2024, 'New York');

        $this->assertEquals('January 2024', $edition->getPublishDate());
        $this->assertSame(2024, $edition->getPublishYear());
        $this->assertEquals('New York', $edition->getPublishPlace());
    }

    #[Test]
    public function set_cover_urls(): void
    {
        $edition = new Edition(1, 'OL123M', 'Title');
        $edition->setCoverUrls('https://s.jpg', 'https://m.jpg', 'https://l.jpg');

        $this->assertEquals('https://s.jpg', $edition->getCoverUrlSmall());
        $this->assertEquals('https://m.jpg', $edition->getCoverUrlMedium());
        $this->assertEquals('https://l.jpg', $edition->getCoverUrlLarge());
    }

    #[Test]
    public function set_series(): void
    {
        $edition = new Edition(1, 'OL123M', 'Title');
        $edition->setSeries(['Harry Potter'], '1');
        // No getter for series directly, but toArray should contain it
        $arr = $edition->toArray();
        $this->assertEquals(['Harry Potter'], $arr['series']);
        $this->assertEquals('1', $arr['series_position']);
    }

    #[Test]
    public function set_links(): void
    {
        $edition = new Edition(1, 'OL123M', 'Title');
        $edition->setLinks('https://preview.com', 'https://info.com');
        $arr = $edition->toArray();
        $this->assertEquals('https://preview.com', $arr['preview_link']);
        $this->assertEquals('https://info.com', $arr['info_link']);
    }

    // ── toArray ──

    #[Test]
    public function to_array_contains_all_fields(): void
    {
        $edition = new Edition(1, 'OL123M', 'Test Edition', 5);
        $edition->setIsbn13(ISBN::fromString('9783161484100'));
        $edition->setPublisher('Penguin');
        $edition->setPages(300);
        $edition->setFormat('Hardcover');
        $edition->setDescription('A great book');
        $edition->setLanguages(['en', 'es']);

        $arr = $edition->toArray();

        $this->assertSame(5, $arr['edition_id']);
        $this->assertSame(1, $arr['work_id']);
        $this->assertEquals('OL123M', $arr['openlibrary_edition_key']);
        $this->assertEquals('9783161484100', $arr['isbn_13']);
        $this->assertNull($arr['isbn_10']);
        $this->assertEquals('Test Edition', $arr['title']);
        $this->assertEquals('Penguin', $arr['publisher']);
        $this->assertSame(300, $arr['pages']);
        $this->assertEquals('Hardcover', $arr['format']);
        $this->assertEquals('A great book', $arr['description']);
        $this->assertEquals(['en', 'es'], $arr['languages']);
        $this->assertEquals('openlibrary', $arr['data_source']);
    }

    // ── fromArray ──

    #[Test]
    public function from_array_creates_edition(): void
    {
        $data = [
            'work_id' => 1,
            'openlibrary_edition_key' => 'OL456M',
            'title' => 'From Array Edition',
            'edition_id' => 10,
            'isbn_13' => '9783161484100',
            'isbn_10' => '0306406152',
            'google_books_id' => 'gbooks123',
            'subtitle' => 'A Subtitle',
            'publisher' => 'Publisher',
            'publish_date' => 'March 2020',
            'publish_year' => 2020,
            'publish_place' => 'London',
            'format' => 'Paperback',
            'pages' => 250,
            'description' => 'Desc',
            'languages' => ['en'],
            'cover_url_small' => 'https://s.jpg',
            'cover_url_medium' => 'https://m.jpg',
            'cover_url_large' => 'https://l.jpg',
            'series' => ['Series Name'],
            'series_position' => '2',
            'preview_link' => 'https://preview.com',
            'info_link' => 'https://info.com',
        ];

        $edition = Edition::fromArray($data);

        $this->assertSame(10, $edition->getEditionId());
        $this->assertSame(1, $edition->getWorkId());
        $this->assertEquals('From Array Edition', $edition->getTitle());
        $this->assertEquals('9783161484100', $edition->getIsbn13()->toString());
        $this->assertEquals('0306406152', $edition->getIsbn10()->toString());
        $this->assertEquals('gbooks123', $edition->getGoogleBooksId());
        $this->assertEquals('A Subtitle', $edition->getSubtitle());
        $this->assertEquals('Publisher', $edition->getPublisher());
        $this->assertSame(2020, $edition->getPublishYear());
        $this->assertEquals('Paperback', $edition->getFormat());
        $this->assertSame(250, $edition->getPages());
        $this->assertEquals('Desc', $edition->getDescription());
        $this->assertEquals(['en'], $edition->getLanguages());
    }

    // ── toLegacyFormat ──

    #[Test]
    public function to_legacy_format_maps_correctly(): void
    {
        $work = new Work('Work Title', ['Author A']);
        $work->setDescription('Work description');
        $work->setSubjects(['Fiction']);

        $edition = new Edition(1, 'OL123M', 'Edition Title', 5);
        $edition->setIsbn13(ISBN::fromString('9783161484100'));
        $edition->setPublisher('Publisher');
        $edition->setPages(200);
        $edition->setCoverUrls(null, 'https://m.jpg', null);
        $edition->setPublishInfo(null, 2024, null);

        $legacy = $edition->toLegacyFormat($work);

        $this->assertEquals('9783161484100', $legacy['isbn']);
        $this->assertEquals('Edition Title', $legacy['title']);
        $this->assertEquals('Author A', $legacy['author']);
        $this->assertEquals(['Author A'], $legacy['authors']);
        $this->assertEquals('Publisher', $legacy['publisher']);
        $this->assertSame(200, $legacy['pages']);
        $this->assertEquals('https://m.jpg', $legacy['coverUrl']);
        $this->assertEquals('Work description', $legacy['description']); // falls back to work
        $this->assertEquals(['Fiction'], $legacy['genres']); // from work subjects
        $this->assertSame(5, $legacy['edition_id']);
        $this->assertSame(1, $legacy['work_id']);
    }

    #[Test]
    public function to_legacy_format_uses_edition_description_over_work(): void
    {
        $work = new Work('Work', ['Author']);
        $work->setDescription('Work desc');

        $edition = new Edition(1, 'OL123M', 'Edition');
        $edition->setDescription('Edition desc');

        $legacy = $edition->toLegacyFormat($work);
        $this->assertEquals('Edition desc', $legacy['description']);
    }

    // ── Round-trip ──

    #[Test]
    public function to_array_from_array_round_trip(): void
    {
        $original = new Edition(1, 'OL123M', 'Round Trip', 7);
        $original->setIsbn13(ISBN::fromString('9783161484100'));
        $original->setPublisher('Pub');
        $original->setPages(100);

        $arr = $original->toArray();
        // fromArray expects cover_url_small/medium/large separately
        $arr['cover_url_small'] = $arr['cover_urls']['small'] ?? null;
        $arr['cover_url_medium'] = $arr['cover_urls']['medium'] ?? null;
        $arr['cover_url_large'] = $arr['cover_urls']['large'] ?? null;

        $restored = Edition::fromArray($arr);

        $this->assertSame(7, $restored->getEditionId());
        $this->assertEquals('Round Trip', $restored->getTitle());
        $this->assertEquals('9783161484100', $restored->getIsbn13()->toString());
        $this->assertEquals('Pub', $restored->getPublisher());
        $this->assertSame(100, $restored->getPages());
    }
}
