<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\Work;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WorkTest extends TestCase
{
    // ── Constructor ──

    #[Test]
    public function creates_work_with_required_fields(): void
    {
        $work = new Work('The Hitchhiker\'s Guide', ['Douglas Adams']);
        $this->assertEquals('The Hitchhiker\'s Guide', $work->getTitle());
        $this->assertEquals(['Douglas Adams'], $work->getAuthors());
        $this->assertNull($work->getWorkId());
        $this->assertNull($work->getOpenlibraryWorkKey());
        $this->assertFalse($work->isSynthetic());
        $this->assertFalse($work->needsReview());
        $this->assertFalse($work->isManuallyEdited());
    }

    #[Test]
    public function creates_work_with_optional_ids(): void
    {
        $work = new Work('Title', ['Author'], 42, '/works/OL123W', 'syn-key');
        $this->assertSame(42, $work->getWorkId());
        $this->assertEquals('/works/OL123W', $work->getOpenlibraryWorkKey());
        $this->assertEquals('syn-key', $work->getSyntheticWorkKey());
    }

    #[Test]
    public function throws_on_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Title cannot be empty');
        new Work('', ['Author']);
    }

    #[Test]
    public function throws_on_empty_authors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authors cannot be empty');
        new Work('Title', []);
    }

    // ── Setters ──

    #[Test]
    public function setters_work(): void
    {
        $work = new Work('Title', ['Author']);
        $work->setSubtitle('A Subtitle');
        $work->setDescription('Description here');
        $work->setSubjects(['sci-fi', 'humor']);
        $work->setFirstPublishYear(1979);
        $work->setOriginalLanguage('en');
        $work->setNeedsReview(true);

        $this->assertEquals('A Subtitle', $work->getSubtitle());
        $this->assertEquals('Description here', $work->getDescription());
        $this->assertEquals(['sci-fi', 'humor'], $work->getSubjects());
        $this->assertSame(1979, $work->getFirstPublishYear());
        $this->assertEquals('en', $work->getOriginalLanguage());
        $this->assertTrue($work->needsReview());
    }

    #[Test]
    public function mark_as_synthetic(): void
    {
        $work = new Work('Title', ['Author']);
        $this->assertFalse($work->isSynthetic());

        $work->markAsSynthetic('syn-123');
        $this->assertTrue($work->isSynthetic());
        $this->assertEquals('syn-123', $work->getSyntheticWorkKey());
    }

    #[Test]
    public function mark_as_manually_edited(): void
    {
        $work = new Work('Title', ['Author']);
        $this->assertFalse($work->isManuallyEdited());

        $work->markAsManuallyEdited(['title', 'description']);
        $this->assertTrue($work->isManuallyEdited());
    }

    #[Test]
    public function set_work_id(): void
    {
        $work = new Work('Title', ['Author']);
        $this->assertNull($work->getWorkId());
        $work->setWorkId(99);
        $this->assertSame(99, $work->getWorkId());
    }

    // ── toArray ──

    #[Test]
    public function to_array_contains_all_fields(): void
    {
        $work = new Work('Title', ['Author A', 'Author B'], 1, '/works/OL1W');
        $work->setSubtitle('Sub');
        $work->setDescription('Desc');
        $work->setSubjects(['Fiction']);
        $work->setFirstPublishYear(2000);
        $work->setOriginalLanguage('es');

        $arr = $work->toArray();

        $this->assertSame(1, $arr['work_id']);
        $this->assertEquals('/works/OL1W', $arr['openlibrary_work_key']);
        $this->assertNull($arr['synthetic_work_key']);
        $this->assertEquals('Title', $arr['title']);
        $this->assertEquals('Sub', $arr['subtitle']);
        $this->assertEquals(['Author A', 'Author B'], $arr['authors']);
        $this->assertEquals('Desc', $arr['description']);
        $this->assertEquals(['Fiction'], $arr['subjects']);
        $this->assertSame(2000, $arr['first_publish_year']);
        $this->assertEquals('es', $arr['original_language']);
        $this->assertFalse($arr['is_synthetic']);
        $this->assertFalse($arr['needs_review']);
        $this->assertFalse($arr['manually_edited']);
        $this->assertNull($arr['manually_edited_fields']);
    }

    // ── fromArray ──

    #[Test]
    public function from_array_creates_work(): void
    {
        $data = [
            'title' => 'From Array',
            'authors' => ['Writer'],
            'work_id' => 5,
            'openlibrary_work_key' => '/works/OL5W',
            'subtitle' => 'Subtitle',
            'description' => 'Desc',
            'subjects' => ['Drama'],
            'first_publish_year' => 1990,
            'original_language' => 'fr',
            'needs_review' => true,
        ];

        $work = Work::fromArray($data);
        $this->assertSame(5, $work->getWorkId());
        $this->assertEquals('From Array', $work->getTitle());
        $this->assertEquals('Subtitle', $work->getSubtitle());
        $this->assertEquals(['Drama'], $work->getSubjects());
        $this->assertSame(1990, $work->getFirstPublishYear());
        $this->assertTrue($work->needsReview());
    }

    #[Test]
    public function from_array_handles_manually_edited(): void
    {
        $data = [
            'title' => 'Work',
            'authors' => ['A'],
            'manually_edited' => true,
            'manually_edited_fields' => ['title'],
        ];

        $work = Work::fromArray($data);
        $this->assertTrue($work->isManuallyEdited());
    }

    // ── Round-trip ──

    #[Test]
    public function to_array_from_array_round_trip(): void
    {
        $original = new Work('Round Trip', ['Author'], 10, '/works/OL10W');
        $original->setDescription('Desc');
        $original->setSubjects(['Science']);
        $original->setFirstPublishYear(2020);

        $restored = Work::fromArray($original->toArray());

        $this->assertSame(10, $restored->getWorkId());
        $this->assertEquals('Round Trip', $restored->getTitle());
        $this->assertEquals(['Author'], $restored->getAuthors());
        $this->assertEquals('Desc', $restored->getDescription());
        $this->assertEquals(['Science'], $restored->getSubjects());
        $this->assertSame(2020, $restored->getFirstPublishYear());
    }
}
