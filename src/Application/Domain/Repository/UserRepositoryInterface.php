<?php
declare(strict_types=1);

namespace App\Application\Domain\Repository;

use App\Application\Domain\Model\User;

interface UserRepositoryInterface
{
    /**
     * Find user by Google ID
     */
    public function findByGoogleId(string $googleId): ?User;

    /**
     * Find user by ID
     */
    public function findById(int $id): ?User;

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Save new user
     */
    public function save(User $user): User;

    /**
     * Update existing user
     */
    public function update(User $user): User;
}
