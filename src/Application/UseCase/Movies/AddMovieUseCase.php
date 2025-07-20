<?php

declare(strict_types=1);

namespace App\Application\UseCase\Movies;

use App\Application\Domain\Model\Movie;
use App\Application\Domain\Repository\MovieRepositoryInterface;
use InvalidArgumentException;

class AddMovieUseCase
{
    private MovieRepositoryInterface $movieRepository;

    public function __construct(MovieRepositoryInterface $movieRepository)
    {
        $this->movieRepository = $movieRepository;
    }

    /**
     * @param array $movieData Datos crudos de la película, incluyendo userStatuses.
     * @return Movie La película agregada.
     * @throws InvalidArgumentException si los datos son inválidos o la película ya existe.
     */
    public function execute(array $movieData): Movie
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

        if ($this->movieRepository->findById($movieData['id'])) {
            throw new InvalidArgumentException('Movie with ID ' . $movieData['id'] . ' already exists.');
        }

        try {
            $movie = Movie::fromArray([
                'id' => $movieData['id'],
                'title' => $movieData['title'],
                'originalTitle' => $movieData['originalTitle'] ?? null,
                'director' => $movieData['director'] ?? null,
                'coverUrl' => $movieData['coverUrl'] ?? null,
                'rating' => isset($movieData['rating']) && is_numeric($movieData['rating']) ? (float)$movieData['rating'] : null,
                'userStatuses' => $movieData['userStatuses'],
                'addedTimestamp' => $movieData['addedTimestamp'] ?? time()
            ],
                $movieData['allowedStatuses'] ?? []
            );
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException('Invalid movie data: ' . $e->getMessage());
        }

        $this->movieRepository->save($movie);
        return $movie;
    }
}
