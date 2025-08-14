<?php
namespace App\Application\UseCase\Books;

use App\Application\Domain\Repository\BookRepositoryInterface;
use App\Application\Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

class GetBooksUseCase
{
    private BookRepositoryInterface $bookRepository;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        BookRepositoryInterface $bookRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->bookRepository = $bookRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param int $userId ID of the user whose books to retrieve
     * @param array $filters Opcional: ['userStatus' => 'read', ...]
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

        // Get books for this specific user
        $books = $this->bookRepository->findBooksByUser((int)$userId, $filters);
        
        // Convert to array format if needed
        return array_map(function($book) {
            return is_object($book) && method_exists($book, 'toArray') ? $book->toArray() : $book;
        }, $books);
    }
}
