<?php
declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\User;

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

    // User library methods
    public function getUserBooks(int $userId, array $filters = []): array;
    
    public function getUserMovies(int $userId, array $filters = []): array;
    
    public function getUserLibraryStats(int $userId): array;
    
    public function hasUserBook(int $userId, string $isbn): bool;
    
    public function hasUserMovie(int $userId, string $movieId): bool;
}
