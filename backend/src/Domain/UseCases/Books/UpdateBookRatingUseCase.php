<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateBookRatingUseCase extends AbstractUseCase
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
        // Validate command
        if (!$command instanceof UpdateBookRatingCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateBookRatingCommand');
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

        // Update the user's rating (Rating VO already validated in constructor)
        $this->userBookRepository->updateRating(
            $command->userId, 
            $command->isbn->toString(), 
            $command->rating->toFloat()
        );
        
        return true;
    }

    protected function getLogContext(): string
    {
        return 'UpdateBookRatingUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Book rating updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update book rating';
    }
} 