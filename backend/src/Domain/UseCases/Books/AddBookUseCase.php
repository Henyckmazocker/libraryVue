<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Model\Edition;
use App\Domain\Model\Work;
use App\Domain\Model\UserBookEdition;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\WorkRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Services\BookImportServiceInterface;
use App\Domain\Services\CoverService;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\AddBookCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use RuntimeException;

class AddBookUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly BookImportServiceInterface $bookImportService,
        private readonly EditionRepositoryInterface $editionRepository,
        private readonly WorkRepositoryInterface $workRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserBookEditionRepositoryInterface $userBookEditionRepository,
        private readonly FeedEventService $feedEventService,
        private readonly CoverService $coverService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with AddBookCommand
     * Imports book from OpenLibrary and adds to user's library
     *
     * @return array Legacy format for frontend compatibility
     */
    protected function doExecute($command): array
    {
        // Validate command is AddBookCommand
        if (!$command instanceof AddBookCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddBookCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if edition exists by ISBN
        $isbn = $command->isbn->toString();
        $edition = $this->editionRepository->findByIsbn($isbn);
        $work = null;

        if ($edition) {
            // Edition already exists in database
            $this->logger->info('Edition found in database', ['edition_id' => $edition->getEditionId()]);
            
            // Check if user already has this edition
            if ($this->userBookEditionRepository->hasEdition($command->userId, $edition->getEditionId())) {
                throw new InvalidArgumentException('You already have this book in your library.');
            }
            
            // Backfill pages if missing in DB but provided in command
            if ($edition->getPages() === null && $command->pages !== null) {
                $this->editionRepository->updatePages($edition->getEditionId(), $command->pages);
                $edition->setPages($command->pages);
                $this->logger->info('Backfilled missing pages for existing edition', [
                    'edition_id' => $edition->getEditionId(),
                    'pages' => $command->pages
                ]);
            }
            
            // Get the associated work
            $work = $this->workRepository->findById($edition->getWorkId());
        } else {
            // Edition not found - use BookImportService to handle import/creation
            $this->logger->info('Edition not found, using BookImportService', ['isbn' => $isbn]);
            
            try {
                // BookImportService handles:
                // 1. Checking for existing Work/Edition by OpenLibrary keys
                // 2. Avoiding duplicates by ISBN
                // 3. Proper data mapping from APIs
                // 4. Transactional saves to database
                $result = $this->bookImportService->importFromOpenLibrary([
                    'title' => $command->title,
                    'authors' => !empty($command->author) ? [['name' => $command->author]] : [],
                    'isbn_13' => strlen($isbn) === 13 ? [$isbn] : [],
                    'isbn_10' => strlen($isbn) === 10 ? [$isbn] : [],
                    'publishers' => !empty($command->publisher) ? [$command->publisher] : [],
                    'publish_date' => $command->publicationYear ? (string)$command->publicationYear : null,
                    'number_of_pages' => $command->pages,
                    'description' => $command->description,
                    'subjects' => $command->genres, // Pass genres from command
                    'covers' => []
                ]);

                $work = $result['work'];
                $edition = $result['edition'];

                $this->logger->info('BookImportService created work and edition', [
                    'work_id' => $work->getWorkId(),
                    'edition_id' => $edition->getEditionId()
                ]);

            } catch (\Exception $e) {
                // If BookImportService fails, fall back to manual creation
                $this->logger->warning('BookImportService failed, falling back to manual creation', [
                    'error' => $e->getMessage()
                ]);
                
                // Create or find work manually
                $authors = !empty($command->author) ? [$command->author] : [];
                
                if (!empty($command->title) && !empty($authors)) {
                    $work = $this->workRepository->findByTitleAndAuthors($command->title, $authors);
                }
                
                if (!$work) {
                    $syntheticWorkKey = 'synthetic_' . md5($command->title . implode('', $authors));

                    $work = Work::fromArray([
                        'title' => $command->title,
                        'authors' => $authors,
                        'subjects' => $command->genres, // Pass genres from command
                        'first_publish_year' => $command->publicationYear,
                        'synthetic_work_key' => $syntheticWorkKey
                    ]);

                    $work->markAsSynthetic($syntheticWorkKey);
                    $work = $this->workRepository->save($work);
                }

                // Create edition manually
                $syntheticEditionKey = 'synthetic_' . $isbn;
                
                $edition = Edition::fromArray([
                    'work_id' => $work->getWorkId(),
                    'openlibrary_edition_key' => $syntheticEditionKey,
                    'isbn_13' => strlen($isbn) === 13 ? $isbn : null,
                    'isbn_10' => strlen($isbn) === 10 ? $isbn : null,
                    'title' => $command->title,
                    'description' => $command->description,
                    'publisher' => $command->publisher,
                    'publish_year' => $command->publicationYear,
                    'pages' => $command->pages,
                    'languages' => $command->language ? [$command->language] : [],
                    'cover_url_large' => $command->coverUrl
                ]);
                
                $edition = $this->editionRepository->save($edition);
            }
        }

        // Add edition to user's library
        $userBookEdition = $this->userBookEditionRepository->add(
            $command->userId,
            $edition->getEditionId(),
            $command->statuses ?? [],
            $command->ownershipFormatId
        );

        // Update user's work rating if provided
        if ($command->userRating !== null) {
            $this->userBookEditionRepository->updateRating(
                $command->userId,
                $edition->getEditionId(),
                $command->userRating->toFloat(), // work_rating
                null // edition_rating not provided in this context
            );
        }

        // Ensure we have the work (should always be set at this point)
        if (!$work) {
            $work = $this->workRepository->findById($edition->getWorkId());
            if (!$work) {
                throw new RuntimeException("Work not found for edition");
            }
        }

        // Return in legacy format for frontend compatibility
        $legacyFormat = $edition->toLegacyFormat($work);

        $this->feedEventService->recordItemAdded(
            $command->userId,
            'book',
            $command->isbn->toString(),
            $edition->getTitle(),
            $legacyFormat['cover'] ?? null
        );

        // Copia local de la portada: registra la fila ahora (sin red) y deja la
        // descarga para después de la respuesta. Un fallo aquí nunca afecta al
        // guardado; lo pendiente lo recoge `bin/mirror covers:backfill`.
        $this->coverService->recordCover(
            'book',
            $command->isbn->toString(),
            $legacyFormat['cover'] ?? null
        );

        return $legacyFormat;
    }

    protected function getLogContext(): string
    {
        return 'AddBookUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Book added successfully to user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to add book to user library';
    }
}