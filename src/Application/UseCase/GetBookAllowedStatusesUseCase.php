<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Domain\Repository\BookRepositoryInterface;

class GetBookAllowedStatusesUseCase
{
    private BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * @return array
     */
    public function execute(): array
    {
        // Suponemos que el repositorio tiene un método público para esto, si no, hay que exponerlo
        if (method_exists($this->bookRepository, 'fetchAllowedStatuses')) {
            return $this->bookRepository->fetchAllowedStatuses();
        }
        // Si no existe, puedes crear un método público en el repositorio
        throw new \RuntimeException('BookRepositoryInterface must expose fetchAllowedStatuses()');
    }
}
