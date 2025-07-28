<?php
namespace App\Application\UseCase\Movies;

use App\Application\Domain\Repository\MovieRepositoryInterface;

class GetMoviesUseCase
{
    private MovieRepositoryInterface $movieRepository;

    public function __construct(MovieRepositoryInterface $movieRepository)
    {
        $this->movieRepository = $movieRepository;
    }

    /**
     * @param array $filters ['title' => string|null, 'status' => string|null]
     * @return array
     */
    public function execute(array $filters = []): array
    {
        return $this->movieRepository->findAllWithFilters($filters);
    }
}
