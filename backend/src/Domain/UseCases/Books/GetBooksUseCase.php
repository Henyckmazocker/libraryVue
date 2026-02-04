<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\WorkRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetBooksByUserQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetBooksUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserBookEditionRepositoryInterface $userBookEditionRepository,
        private readonly EditionRepositoryInterface $editionRepository,
        private readonly WorkRepositoryInterface $workRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetBooksByUserQuery
     * Get books for a specific user with optional filters
     * Returns data in legacy format for frontend compatibility
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetBooksByUserQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetBooksByUserQuery');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Get user's book editions with filters
        $userBookEditions = $this->userBookEditionRepository->findByUser($command->userId, $command->filters);

        // Convert to legacy format
        $books = [];
        foreach ($userBookEditions as $userBookEdition) {
            // Fetch edition
            $edition = $this->editionRepository->findById($userBookEdition->getEditionId());
            if (!$edition) {
                $this->logger->warning('Edition not found for user book', [
                    'user_book_edition_id' => $userBookEdition->getId(),
                    'edition_id' => $userBookEdition->getEditionId()
                ]);
                continue;
            }

            // Fetch work
            $work = $this->workRepository->findById($edition->getWorkId());
            if (!$work) {
                $this->logger->warning('Work not found for edition', [
                    'edition_id' => $edition->getEditionId(),
                    'work_id' => $edition->getWorkId()
                ]);
                continue;
            }

            // Merge edition + work data in legacy format
            $legacyBook = $edition->toLegacyFormat($work);
            
            // Add user-specific data from UserBookEdition
            $userLegacyData = $userBookEdition->toLegacyFormat();
            $legacyBook = array_merge($legacyBook, $userLegacyData);
            
            // Fetch and add user statuses for this book edition
            $statuses = $this->userBookEditionRepository->getStatusesForEdition(
                $command->userId,
                $userBookEdition->getEditionId()
            );
            $legacyBook['userStatuses'] = $statuses;

            $books[] = $legacyBook;
        }

        return $books;
    }

    /**
     * Get log context for this use case
     */
    protected function getLogContext(): string
    {
        return 'GetBooksUseCase';
    }
}
