<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Social\Mappers;

use App\Domain\Model\Friendship;
use App\Infrastructure\Persistence\Concerns\HydrationHelpersTrait;

class FriendshipDataMapper
{
    use HydrationHelpersTrait;

    public function toDomain(array $row): Friendship
    {
        return new Friendship(
            id:          $this->extractInt($row, 'id', null),
            requesterId: $this->extractRequiredInt($row, 'requester_id'),
            addresseeId: $this->extractRequiredInt($row, 'addressee_id'),
            status:      $this->extractString($row, 'status', Friendship::STATUS_PENDING),
            createdAt:   $this->extractString($row, 'created_at', null),
            updatedAt:   $this->extractString($row, 'updated_at', null)
        );
    }

    public function toDomainCollection(array $rows): array
    {
        return array_map(fn(array $row) => $this->toDomain($row), $rows);
    }

    public function toPersistence(Friendship $friendship): array
    {
        return [
            'requester_id' => $friendship->getRequesterId(),
            'addressee_id' => $friendship->getAddresseeId(),
            'status'       => $friendship->getStatus(),
        ];
    }
}
