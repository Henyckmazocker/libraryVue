<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Domain\Repository\MovieRepositoryInterface;

class GetMovieAllowedStatusesUseCase
{
    private MovieRepositoryInterface $movieRepository;

    public function __construct(MovieRepositoryInterface $movieRepository)
    {
        $this->movieRepository = $movieRepository;
    }

    /**
     * @return array
     */
    public function execute(): array
    {
        // Suponemos que el repositorio tiene un método público para esto, si no, hay que exponerlo
        if (method_exists($this->movieRepository, 'fetchAllowedStatuses')) {
            return $this->movieRepository->fetchAllowedStatuses();
        }
        // Si no existe, puedes crear un método público en el repositorio
        throw new \RuntimeException('MovieRepositoryInterface must expose fetchAllowedStatuses()');
    }
}
