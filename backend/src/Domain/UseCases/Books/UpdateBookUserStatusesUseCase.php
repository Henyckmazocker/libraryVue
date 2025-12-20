<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateBookStatusesCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateBookUserStatusesUseCase extends AbstractUseCase
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
        if (!$command instanceof UpdateBookStatusesCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateBookStatusesCommand');
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

        // Update the user's statuses for this book
        $this->userBookRepository->updateStatuses($command->userId, $command->isbn->toString(), $command->statuses);
        
        return true;
    }

    protected function getLogContext(): string
    {
        return 'UpdateBookUserStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Book statuses updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update book statuses';
    }
} 