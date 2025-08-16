<?php
namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\MovieRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class UpdateMovieUserStatusesUseCase
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
     * @param int $userId ID of the user updating the movie
     * @param string $movieId Movie ID 
     * @param array $userStatuses The new array of user statuses
     * @return bool True if update was successful
     * @throws InvalidArgumentException if user or movie not found, or if user doesn't have this movie
     */
    public function execute(int $userId, string $movieId, array $userStatuses): bool
    {
        if (empty($movieId)) {
            throw new InvalidArgumentException('Movie ID is required to update movie statuses.');
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

        // Update the user's statuses for this movie
        $this->movieRepository->updateUserMovieStatuses($userId, $movieId, $userStatuses);
        
        return true;
    }
}
