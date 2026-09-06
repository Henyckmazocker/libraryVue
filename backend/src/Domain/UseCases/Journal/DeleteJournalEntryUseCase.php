<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Journal;

use App\Domain\DTO\Commands\DeleteJournalEntryCommand;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class DeleteJournalEntryUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'DeleteJournalEntry'; }

    protected function doExecute($command): bool
    {
        if (!$command instanceof DeleteJournalEntryCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteJournalEntryCommand');
        }

        // Borrar una entrada **no toca la valoración del ítem**: quitarla del
        // diario no significa que ya no te guste. Es la contraparte de que un
        // alta sin rating tampoco despunte.
        if (!$this->journal->delete($command->entryId, $command->userId)) {
            throw new RuntimeException('Journal entry not found.');
        }

        return true;
    }
}
