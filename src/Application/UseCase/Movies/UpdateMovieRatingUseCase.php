<?php

declare(strict_types=1);

namespace App\Application\UseCase\Movies;

use App\Application\Domain\Repository\MovieRepositoryInterface;
use RuntimeException;

class UpdateMovieRatingUseCase
{
    private MovieRepositoryInterface $movieRepository;

    public function __construct(MovieRepositoryInterface $movieRepository)
    {
        $this->movieRepository = $movieRepository;
    }

    /**
     * @param string $id Puede ser imdbID o isbn
     * @param float $rating
     * @return void
     */
    public function execute(string $id, float $rating): void
    {
        if ($rating < 0 || $rating > 5) {
            throw new RuntimeException('El rating debe estar entre 0 y 5.');
        }
        $this->movieRepository->updateMovieRating($id, $rating);
    }
}
