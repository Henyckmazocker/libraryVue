<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\DeleteBookCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class DeleteBookUseCase extends AbstractUseCase
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
        if (!$command instanceof DeleteBookCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteBookCommand');
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

        // Remove the edition from user's library
        // This will CASCADE delete related data (statuses, notes, progress)
        return $this->userBookEditionRepository->remove($command->userId, $edition->getEditionId());
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