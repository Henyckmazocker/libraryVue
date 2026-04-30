<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Books;

use App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase;
use App\Domain\Repository\Book\BookRepositoryInterface;
use App\Domain\DTO\Queries\GetAllowedStatusesQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use InvalidArgumentException;

class GetBookAllowedStatusesUseCaseTest extends TestCase
{
    private GetBookAllowedStatusesUseCase $useCase;
    private BookRepositoryInterface $bookRepo;

    protected function setUp(): void
    {
        $this->bookRepo = $this->createMock(BookRepositoryInterface::class);
        $this->useCase = new GetBookAllowedStatusesUseCase($this->bookRepo, new NullLogger());
    }

    #[Test]
    public function successfully_returns_allowed_statuses(): void
    {
        $statuses = ['reading', 'completed', 'wishlist', 'owned'];
        $this->bookRepo->method('fetchAllowedStatuses')->willReturn($statuses);

        $result = $this->useCase->execute(GetAllowedStatusesQuery::forBooks());
        $this->assertSame($statuses, $result);
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    #[Test]
    public function throws_on_wrong_entity_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only handles book statuses');
        $this->useCase->execute(GetAllowedStatusesQuery::forMovies());
    }
}
