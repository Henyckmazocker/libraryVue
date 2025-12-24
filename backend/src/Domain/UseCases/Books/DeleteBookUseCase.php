<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\DeleteBookCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class DeleteBookUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserBookRepositoryInterface $userBookRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof DeleteBookCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteBookCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if user has this book in their library
        if (!$this->userBookRepository->hasBook($command->userId, $command->isbn->toString())) {
            throw new InvalidArgumentException('Book not found in your library.');
        }

        // Remove the book from user's library
        return $this->userBookRepository->remove($command->userId, $command->isbn->toString());
    }

    protected function getLogContext(): string
    {
        return 'DeleteBookUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Book removed successfully from user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to remove book from user library';
    }
} 