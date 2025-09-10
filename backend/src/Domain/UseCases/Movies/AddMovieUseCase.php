<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Model\Movie;
use App\Domain\Repository\MovieRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class AddMovieUseCase
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
     * @param array $movieData Datos crudos de la película, incluyendo userStatuses.
     * @param int $userId ID of the user to associate the movie with
     * @return Movie La película agregada.
     * @throws InvalidArgumentException si los datos son inválidos o la relación usuario-película ya existe.
     */
    public function execute(array $movieData, int $userId): Movie
    {
        if (empty($movieData['id'])) {
            throw new InvalidArgumentException('ID is required to add a movie.');
        }
        if (empty($movieData['title'])) {
            throw new InvalidArgumentException('Title is required to add a movie.');
        }
        if (empty($movieData['userStatuses']) || !is_array($movieData['userStatuses'])) {
            throw new InvalidArgumentException('User statuses are required and must be an array.');
        }

        // Validate user exists
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        // Check if user already has this movie - this is the only error case
        if ($this->userRepository->hasUserMovie($userId, $movieData['id'])) {
            throw new InvalidArgumentException('You already have this movie in your library.');
        }

        // Check if movie exists in the system
        $existingMovie = $this->movieRepository->findById($movieData['id']);
        
        if (!$existingMovie) {
            // Movie doesn't exist, create it first
            try {
                $movie = Movie::fromArray([
                    'id' => $movieData['id'],
                    'title' => $movieData['title'],
                    'originalTitle' => $movieData['originalTitle'] ?? null,
                    'director' => $movieData['director'] ?? null,
                    'coverUrl' => $movieData['coverUrl'] ?? null,
                    'rating' => isset($movieData['rating']) && is_numeric($movieData['rating']) ? (float)$movieData['rating'] : null,
                    'description' => $movieData['description'] ?? null,
                    'userStatuses' => $movieData['userStatuses'],
                    'addedTimestamp' => $movieData['addedTimestamp'] ?? time(),
                    'allowedStatuses' => $movieData['allowedStatuses'] ?? []
                ]);
            } catch (\InvalidArgumentException $e) {
                throw new InvalidArgumentException('Invalid movie data: ' . $e->getMessage());
            }
            
            // Save the movie to the system
            $this->movieRepository->save($movie);
        } else {
            // Movie exists, we need to convert the array to Movie object
            $movie = Movie::fromArray(array_merge($existingMovie, [
                'allowedStatuses' => $movieData['allowedStatuses'] ?? []
            ]));
        }

        // Add the movie to user's library with their specific statuses
        $this->movieRepository->addMovieToUser((int)$userId, $movieData['id'], $movieData['userStatuses']);
        
        // Update user's personal rating if provided
        if (isset($movieData['rating']) && is_numeric($movieData['rating'])) {
            $personalRating = (float)$movieData['rating'];
            if ($personalRating >= 0 && $personalRating <= 5) {
                $this->movieRepository->updateUserMovieRating((int)$userId, $movieData['id'], $personalRating);
            }
        }
        
        return $movie;
    }
}
