<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

final readonly class GetInboxCountQuery
{
    public function __construct(
        public int $userId
    ) {}
}
