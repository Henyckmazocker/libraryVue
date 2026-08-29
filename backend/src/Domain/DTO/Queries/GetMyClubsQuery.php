<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

final readonly class GetMyClubsQuery
{
    public function __construct(
        public int $userId
    ) {}
}
