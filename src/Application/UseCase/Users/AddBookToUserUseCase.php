<?php
declare(strict_types=1);

namespace App\Application\UseCase\Users;

use App\Application\Domain\Repository\BookRepositoryInterface;
use App\Application\Domain\Repository\UserRepositoryInterface;
use RuntimeException;

class AddBookToUserUseCase
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

    public function execute(int $userId, string $isbn, array $statuses = []): array
    {
        try {
            // Validate user exists
            $user = $this->userRepository->findById($userId);
            if (!$user) {
                throw new RuntimeException("User with ID {$userId} not found");
            }

            // Validate book exists
            $book = $this->bookRepository->findById($isbn);
            if (!$book) {
                throw new RuntimeException("Book with ISBN {$isbn} not found");
            }

            // Check if user already has this book
            if ($this->userRepository->hasUserBook($userId, $isbn)) {
                throw new RuntimeException("User already has this book in their library");
            }

            // Add book to user's library
            $this->bookRepository->addBookToUser($userId, $isbn, $statuses);

            return [
                'status' => 'success',
                'message' => 'Book added to user library successfully',
                'data' => [
                    'user_id' => $userId,
                    'isbn' => $isbn,
                    'statuses' => $statuses
                ]
            ];

        } catch (RuntimeException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null
            ];
        } catch (\Throwable $e) {
            error_log("Unexpected error in AddBookToUserUseCase: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'An unexpected error occurred while adding book to user',
                'data' => null
            ];
        }
    }
}
