<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Domain\Repository\BookRepositoryInterface;
use App\Application\Domain\Model\Book;

class GetLibraryUseCase
{
    private BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * @param array $filters Optional filters to apply when retrieving books.
     *                       Example: ['userStatus' => 'read']
     * @return Book[]
     */
    public function execute(array $filters = []): array
    {
        // The repository will handle the actual filtering logic.
        return $this->bookRepository->findAll($filters);
    }
} 