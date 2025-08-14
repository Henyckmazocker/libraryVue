<?php

declare(strict_types=1);

namespace App\Application\UseCase\Movies;

use App\Application\Domain\Repository\MovieRepositoryInterface;
use App\Application\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class DeleteMovieUseCase
{
    private MovieRepositoryInterface $movieRepository;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        MovieRepositoryInterface $movieRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->movieRepository = $movieRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param int $userId ID of the user removing the movie from their library
     * @param string $movieId The ID of the movie to remove from user's library.
     * @return bool True if removal was successful.
     * @throws InvalidArgumentException if user or movie not found, or user doesn't have this movie.
     */
    public function execute(int $userId, string $movieId): bool
    {
        if (empty($movieId)) {
            throw new InvalidArgumentException('Movie ID is required to remove a movie.');
        }

        // Validate user exists
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        // Check if user has this movie in their library
        if (!$this->userRepository->hasUserMovie($userId, $movieId)) {
            throw new InvalidArgumentException('Movie not found in your library.');
        }

        // Remove the movie from user's library (not from the system)
        return $this->movieRepository->removeMovieFromUser($userId, $movieId);
    }
}
