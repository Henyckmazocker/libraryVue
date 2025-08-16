<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\MovieRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class UpdateMovieRatingUseCase
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
     * @param int $userId ID of the user updating the rating
     * @param string $movieId Movie ID (can be imdbID)
     * @param float|null $rating The new rating (0.5-5, multiple of 0.5, or null to unrate)
     * @return bool True if update was successful
     * @throws InvalidArgumentException if user or movie not found, user doesn't have movie, or rating is invalid
     */
    public function execute(int $userId, string $movieId, ?float $rating): bool
    {
        if (empty($movieId)) {
            throw new InvalidArgumentException('Movie ID is required to update a rating.');
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

        // Validate rating value
        if ($rating !== null && ($rating < 0.5 || $rating > 5 || fmod($rating * 2, 1) !== 0.0)) {
            throw new InvalidArgumentException('Rating must be between 0.5 and 5 in increments of 0.5, or null');
        }

        // Update the user's rating for this movie
        $this->movieRepository->updateUserMovieRating($userId, $movieId, $rating);
        
        return true;
    }
}
