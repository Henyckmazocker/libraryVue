<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

class Friendship
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    private const VALID_STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED];

    public function __construct(
        private ?int    $id,
        private int     $requesterId,
        private int     $addresseeId,
        private string  $status,
        private ?string $createdAt = null,
        private ?string $updatedAt = null
    ) {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid friendship status: {$status}");
        }
        if ($requesterId === $addresseeId) {
            throw new InvalidArgumentException('A user cannot befriend themselves');
        }
    }

    public function getId(): ?int      { return $this->id; }
    public function getRequesterId(): int { return $this->requesterId; }
    public function getAddresseeId(): int { return $this->addresseeId; }
    public function getStatus(): string   { return $this->status; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isAccepted(): bool { return $this->status === self::STATUS_ACCEPTED; }

    public function accept(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \LogicException('Only pending friendships can be accepted');
        }
        $this->status = self::STATUS_ACCEPTED;
    }

    public function reject(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \LogicException('Only pending friendships can be rejected');
        }
        $this->status = self::STATUS_REJECTED;
    }

    public function involvesBothUsers(int $userId1, int $userId2): bool
    {
        return ($this->requesterId === $userId1 && $this->addresseeId === $userId2)
            || ($this->requesterId === $userId2 && $this->addresseeId === $userId1);
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'requester_id' => $this->requesterId,
            'addressee_id' => $this->addresseeId,
            'status'       => $this->status,
            'created_at'   => $this->createdAt,
            'updated_at'   => $this->updatedAt,
        ];
    }
}
