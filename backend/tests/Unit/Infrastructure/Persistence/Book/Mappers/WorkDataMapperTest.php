<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Book\Mappers;

use App\Infrastructure\Persistence\Book\Mappers\WorkDataMapper;
use App\Domain\Model\Work;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WorkDataMapperTest extends TestCase
{
    private WorkDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new WorkDataMapper();
    }

    private function fullDbRow(): array
    {
        return [
            'work_id' => 42,
            'openlibrary_work_key' => '/works/OL123W',
            'synthetic_work_key' => null,
            'title' => 'Test Work',
            'subtitle' => 'A Subtitle',
            'authors' => json_encode(['Author One', 'Author Two']),
            'description' => 'Work description',
            'subjects' => json_encode(['History', 'Science']),
            'first_publish_year' => 1999,
            'original_language' => 'en',
            'needs_review' => 0,
            'manually_edited' => 0,
            'manually_edited_fields' => null,
        ];
    }

    // ── toDomain ──

    #[Test]
    public function to_domain_maps_all_fields(): void
    {
        $work = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(Work::class, $work);
        $this->assertSame(42, $work->getWorkId());
        $this->assertEquals('/works/OL123W', $work->getOpenlibraryWorkKey());
        $this->assertNull($work->getSyntheticWorkKey());
        $this->assertEquals('Test Work', $work->getTitle());
        $this->assertEquals('A Subtitle', $work->getSubtitle());
        $this->assertEquals(['Author One', 'Author Two'], $work->getAuthors());
        $this->assertEquals('Work description', $work->getDescription());
        $this->assertEquals(['History', 'Science'], $work->getSubjects());
        $this->assertSame(1999, $work->getFirstPublishYear());
        $this->assertEquals('en', $work->getOriginalLanguage());
        $this->assertFalse($work->needsReview());
        $this->assertFalse($work->isManuallyEdited());
    }

    #[Test]
    public function to_domain_minimal_row(): void
    {
        $row = [
            'title' => 'Minimal Work',
            'authors' => json_encode(['Single Author']),
        ];

        $work = $this->mapper->toDomain($row);

        $this->assertEquals('Minimal Work', $work->getTitle());
        $this->assertEquals(['Single Author'], $work->getAuthors());
        $this->assertNull($work->getWorkId());
        $this->assertNull($work->getSubtitle());
        $this->assertNull($work->getDescription());
        $this->assertNull($work->getSubjects());
        $this->assertNull($work->getFirstPublishYear());
    }

    #[Test]
    public function to_domain_authors_already_array(): void
    {
        $row = $this->fullDbRow();
        $row['authors'] = ['Author A', 'Author B'];

        $work = $this->mapper->toDomain($row);
        $this->assertEquals(['Author A', 'Author B'], $work->getAuthors());
    }

    #[Test]
    public function to_domain_subjects_already_array(): void
    {
        $row = $this->fullDbRow();
        $row['subjects'] = ['Math'];

        $work = $this->mapper->toDomain($row);
        $this->assertEquals(['Math'], $work->getSubjects());
    }

    #[Test]
    public function to_domain_manually_edited_with_fields(): void
    {
        $row = $this->fullDbRow();
        $row['manually_edited'] = 1;
        $row['manually_edited_fields'] = json_encode(['title', 'description']);

        $work = $this->mapper->toDomain($row);

        $this->assertTrue($work->isManuallyEdited());
    }

    #[Test]
    public function to_domain_manually_edited_fields_already_array(): void
    {
        $row = $this->fullDbRow();
        $row['manually_edited'] = 1;
        $row['manually_edited_fields'] = ['title'];

        $work = $this->mapper->toDomain($row);
        $this->assertTrue($work->isManuallyEdited());
    }

    #[Test]
    public function to_domain_needs_review(): void
    {
        $row = $this->fullDbRow();
        $row['needs_review'] = 1;

        $work = $this->mapper->toDomain($row);
        $this->assertTrue($work->needsReview());
    }

    // ── toDatabase ──

    #[Test]
    public function to_database_maps_all_fields(): void
    {
        $work = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toDatabase($work);

        $this->assertSame(42, $data['work_id']);
        $this->assertEquals('/works/OL123W', $data['openlibrary_work_key']);
        $this->assertEquals('Test Work', $data['title']);
        $this->assertEquals('A Subtitle', $data['subtitle']);
        $this->assertJson($data['authors']);
        $this->assertEquals(['Author One', 'Author Two'], json_decode($data['authors'], true));
        $this->assertEquals('Work description', $data['description']);
        $this->assertJson($data['subjects']);
        $this->assertEquals(['History', 'Science'], json_decode($data['subjects'], true));
        $this->assertSame(1999, $data['first_publish_year']);
        $this->assertEquals('en', $data['original_language']);
        $this->assertSame(0, $data['is_synthetic']);
        $this->assertSame(0, $data['needs_review']);
        $this->assertSame(0, $data['manually_edited']);
        $this->assertNull($data['manually_edited_fields']);
    }

    #[Test]
    public function to_database_manually_edited_serializes_fields(): void
    {
        $row = $this->fullDbRow();
        $row['manually_edited'] = 1;
        $row['manually_edited_fields'] = json_encode(['title']);

        $work = $this->mapper->toDomain($row);
        $data = $this->mapper->toDatabase($work);

        $this->assertSame(1, $data['manually_edited']);
        $this->assertJson($data['manually_edited_fields']);
    }

    #[Test]
    public function to_database_null_subjects(): void
    {
        $row = [
            'title' => 'No Subjects',
            'authors' => json_encode(['A']),
        ];

        $work = $this->mapper->toDomain($row);
        $data = $this->mapper->toDatabase($work);

        $this->assertNull($data['subjects']);
    }

    // ── Round-trip ──

    #[Test]
    public function round_trip_preserves_core_data(): void
    {
        $original = $this->fullDbRow();
        $work = $this->mapper->toDomain($original);
        $data = $this->mapper->toDatabase($work);

        $this->assertSame((int) $original['work_id'], $data['work_id']);
        $this->assertEquals($original['title'], $data['title']);
        $this->assertEquals($original['subtitle'], $data['subtitle']);
        $this->assertEquals($original['description'], $data['description']);
        $this->assertSame((int) $original['first_publish_year'], $data['first_publish_year']);
        $this->assertEquals($original['original_language'], $data['original_language']);
    }
}
