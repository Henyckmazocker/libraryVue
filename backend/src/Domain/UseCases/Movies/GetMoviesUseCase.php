<?php
namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\MovieRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class GetMoviesUseCase
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
     * @param int $userId ID of the user whose movies to retrieve
     * @param array $filters ['title' => string|null, 'status' => string|null]
     * @return array
     * @throws InvalidArgumentException if user not found
     */
    public function execute(int $userId, array $filters = []): array
    {
        // Validate user exists
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        // Get movies for this specific user
        $movies = $this->movieRepository->findMoviesByUser((int)$userId, $filters);
        
        // Convert to array format if needed
        return array_map(function($movie) {
            return is_object($movie) && method_exists($movie, 'toArray') ? $movie->toArray() : $movie;
        }, $movies);
    }
}
