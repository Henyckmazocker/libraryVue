<?php

declare(strict_types=1);

namespace App\Domain\DTO\Queries;

final readonly class GetPublicProfileQuery
{
    public function __construct(
        public string $username,
        public int    $viewerUserId
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        $username = trim((string) ($data['username'] ?? ''));
        return new self(username: $username, viewerUserId: $userId);
    }
}
