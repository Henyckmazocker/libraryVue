<?php
namespace App\Application\UseCase\Movies;

use App\Application\Domain\Repository\MovieRepositoryInterface;

class UpdateMovieUserStatusesUseCase
{
    private MovieRepositoryInterface $movieRepository;

    public function __construct(MovieRepositoryInterface $movieRepository)
    {
        $this->movieRepository = $movieRepository;
    }

    /**
     * @param string $imdbID
     * @param array $statuses
     * @return void
     */
    public function execute(string $imdbID, array $statuses): void
    {
        $this->movieRepository->updateUserStatuses($imdbID, $statuses);
    }
}
