<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\Repository\Book\BookTagRepositoryInterface;
use App\Domain\Repository\Book\BookNoteRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\EditUserBookCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class EditUserBookUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserBookRepositoryInterface $userBookRepository,
        private readonly BookTagRepositoryInterface $bookTagRepository,
        private readonly BookNoteRepositoryInterface $bookNoteRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): void
    {
        if (!$command instanceof EditUserBookCommand) {
            throw new InvalidArgumentException('Command must be an instance of EditUserBookCommand');
        }

        $isbn = $command->isbn->toString();
        $userId = $command->userId;

        $this->logger->info('EditUserBook - Command data', [
            'isbn' => $isbn,
            'userId' => $userId,
            'userRating' => $command->userRating?->toFloat(),
            'statuses' => $command->statuses,
            'tags' => $command->tags,
            'currentPage' => $command->currentPage,
            'personalNotes' => $command->personalNotes,
            'consumedAt' => $command->consumedAt
        ]);

        // Prepare data for edit method
        $editData = [];
        
        if ($command->userRating !== null) {
            $editData['personal_rating'] = $command->userRating->toFloat();
        }
        
        if ($command->currentPage !== null) {
            $editData['current_page'] = $command->currentPage;
        }
        
        if ($command->personalNotes !== null) {
            $editData['personal_notes'] = $command->personalNotes;
        }
        
        if ($command->consumedAt !== null) {
            $editData['consumed_at'] = $command->consumedAt;
        }
        
        // Update user book data if there's anything to update
        if (!empty($editData)) {
            $this->userBookRepository->edit($userId, $isbn, $editData);
        }

        // Update statuses (allow clearing all statuses with empty array)
        if ($command->statuses !== null) {
            $this->userBookRepository->updateStatuses($userId, $isbn, $command->statuses);
        }

        // Remove all existing tags
        $this->bookTagRepository->removeAll($userId, $isbn);

        // Add new tags
        foreach ($command->tags as $tag) {
            // If tag is numeric ID, assign directly
            if (is_numeric($tag)) {
                $this->bookTagRepository->assign($userId, $isbn, (int)$tag);
            }
        }
    }

    protected function getLogContext(): string
    {
        return 'EditUserBookUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'User book edited successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to edit user book';
    }
}
