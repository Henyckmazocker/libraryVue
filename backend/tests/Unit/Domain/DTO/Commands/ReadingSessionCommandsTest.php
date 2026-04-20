<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Commands;

use App\Domain\DTO\Commands\CompleteReadingSessionCommand;
use App\Domain\DTO\Commands\CreateReadingSessionCommand;
use App\Domain\DTO\Commands\ManageReadingSessionCommand;
use App\Domain\DTO\Commands\UpdateReadingProgressCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReadingSessionCommandsTest extends TestCase
{
    // ═══════════════════════════════════════
    // CreateReadingSessionCommand
    // ═══════════════════════════════════════

    #[Test]
    public function create_reading_session_constructor(): void
    {
        $cmd = new CreateReadingSessionCommand(
            userId: 1,
            isbn: '9783161484100',
            startPage: 50
        );

        $this->assertSame(1, $cmd->userId);
        $this->assertEquals('9783161484100', $cmd->isbn);
        $this->assertSame(50, $cmd->startPage);
    }

    #[Test]
    public function create_reading_session_defaults(): void
    {
        $cmd = new CreateReadingSessionCommand(userId: 1, isbn: '9783161484100');
        $this->assertNull($cmd->startPage);
    }

    #[Test]
    public function create_reading_session_from_array(): void
    {
        $cmd = CreateReadingSessionCommand::fromArray([
            'isbn' => '9783161484100',
            'startPage' => 25,
        ], 1);

        $this->assertSame(1, $cmd->userId);
        $this->assertEquals('9783161484100', $cmd->isbn);
        $this->assertSame(25, $cmd->startPage);
    }

    #[Test]
    public function create_reading_session_from_array_defaults(): void
    {
        $cmd = CreateReadingSessionCommand::fromArray([], 1);
        $this->assertEquals('', $cmd->isbn);
        $this->assertNull($cmd->startPage);
    }

    // ═══════════════════════════════════════
    // CompleteReadingSessionCommand
    // ═══════════════════════════════════════

    #[Test]
    public function complete_reading_session_constructor(): void
    {
        $cmd = new CompleteReadingSessionCommand(
            sessionId: 42,
            endPage: 200,
            reason: 'finished'
        );

        $this->assertSame(42, $cmd->sessionId);
        $this->assertSame(200, $cmd->endPage);
        $this->assertEquals('finished', $cmd->reason);
    }

    #[Test]
    public function complete_reading_session_default_reason(): void
    {
        $cmd = new CompleteReadingSessionCommand(sessionId: 1, endPage: 100);
        $this->assertEquals('completed', $cmd->reason);
    }

    #[Test]
    public function complete_reading_session_from_array(): void
    {
        $cmd = CompleteReadingSessionCommand::fromArray([
            'sessionId' => 42,
            'endPage' => 200,
            'reason' => 'abandoned',
        ]);

        $this->assertSame(42, $cmd->sessionId);
        $this->assertSame(200, $cmd->endPage);
        $this->assertEquals('abandoned', $cmd->reason);
    }

    #[Test]
    public function complete_reading_session_from_array_defaults(): void
    {
        $cmd = CompleteReadingSessionCommand::fromArray([]);
        $this->assertSame(0, $cmd->sessionId);
        $this->assertSame(0, $cmd->endPage);
        $this->assertEquals('completed', $cmd->reason);
    }

    // ═══════════════════════════════════════
    // ManageReadingSessionCommand
    // ═══════════════════════════════════════

    #[Test]
    public function manage_reading_session_constructor(): void
    {
        $cmd = new ManageReadingSessionCommand(sessionId: 42);
        $this->assertSame(42, $cmd->sessionId);
    }

    #[Test]
    public function manage_reading_session_from_array(): void
    {
        $cmd = ManageReadingSessionCommand::fromArray(['sessionId' => 42]);
        $this->assertSame(42, $cmd->sessionId);
    }

    #[Test]
    public function manage_reading_session_from_array_defaults(): void
    {
        $cmd = ManageReadingSessionCommand::fromArray([]);
        $this->assertSame(0, $cmd->sessionId);
    }

    // ═══════════════════════════════════════
    // UpdateReadingProgressCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_reading_progress_constructor(): void
    {
        $cmd = new UpdateReadingProgressCommand(
            userId: 1,
            isbn: '9783161484100',
            currentPage: 150,
            sessionId: 42
        );

        $this->assertSame(1, $cmd->userId);
        $this->assertEquals('9783161484100', $cmd->isbn);
        $this->assertSame(150, $cmd->currentPage);
        $this->assertSame(42, $cmd->sessionId);
    }

    #[Test]
    public function update_reading_progress_defaults(): void
    {
        $cmd = new UpdateReadingProgressCommand(userId: 1, isbn: '9783161484100', currentPage: 0);
        $this->assertNull($cmd->sessionId);
    }

    #[Test]
    public function update_reading_progress_from_array(): void
    {
        $cmd = UpdateReadingProgressCommand::fromArray([
            'isbn' => '9783161484100',
            'currentPage' => 75,
            'sessionId' => 10,
        ], 1);

        $this->assertSame(1, $cmd->userId);
        $this->assertEquals('9783161484100', $cmd->isbn);
        $this->assertSame(75, $cmd->currentPage);
        $this->assertSame(10, $cmd->sessionId);
    }

    #[Test]
    public function update_reading_progress_from_array_defaults(): void
    {
        $cmd = UpdateReadingProgressCommand::fromArray([], 1);
        $this->assertEquals('', $cmd->isbn);
        $this->assertSame(0, $cmd->currentPage);
        $this->assertNull($cmd->sessionId);
    }
}
