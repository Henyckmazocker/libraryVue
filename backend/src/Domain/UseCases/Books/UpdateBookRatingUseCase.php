<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateBookRatingUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserBookEditionRepositoryInterface $userBookEditionRepository,
        private readonly EditionRepositoryInterface $editionRepository,
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

        // Find edition by ISBN
        $isbn = $command->isbn->toString();
        $edition = $this->editionRepository->findByIsbn($isbn);
        
        if (!$edition) {
            throw new InvalidArgumentException("Book with ISBN {$isbn} not found in database.");
        }

        // Check if user has this edition in their library
        if (!$this->userBookEditionRepository->hasEdition($command->userId, $edition->getEditionId())) {
            throw new InvalidArgumentException('Book not found in your library.');
        }

        // Update the user's work rating (Rating VO already validated in constructor)
        $this->userBookEditionRepository->updateRating(
            $command->userId,
            $edition->getEditionId(),
            $command->rating->toFloat(), // work_rating
            null // edition_rating - could be added as optional parameter
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