<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\Services\JournalService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateBookStatusesCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateBookUserStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserBookRepositoryInterface $userBookRepository,
        private readonly EditionRepositoryInterface $editionRepository,
        private readonly FeedEventService $feedEventService,
        private readonly JournalService $journalService,
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

        $estadosPrevios = $this->userBookRepository->getUserStatuses($command->userId, $command->isbn->toString());

        // Update the user's statuses for this book
        $this->userBookRepository->updateStatuses($command->userId, $command->isbn->toString(), $command->statuses);

        $edition = $this->editionRepository->findByIsbn($command->isbn->toString());
        if ($edition && !empty($command->statuses)) {
            $this->feedEventService->recordStatusChanged(
                $command->userId,
                'book',
                $command->isbn->toString(),
                $edition->getTitle(),
                null,
                '',
                implode(', ', $command->statuses)
            );

            // El diario apunta SOLO en la transición no-consumido → consumido.
            // Los estados previos se leen arriba, antes de sustituirlos: este
            // caso de uso recibe el conjunto entero y por sí solo no sabe qué
            // cambió. La regla de qué estado cuenta vive en
            // `JournalEntry::CONSUMED_STATUSES`, no aquí.
            $this->journalService->recordIfConsumed(
                $command->userId,
                'book',
                $command->isbn->toString(),
                $edition->getTitle(),
                $edition->getCoverUrlMedium(),
                $estadosPrevios,
                $command->statuses
            );
        }
        
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