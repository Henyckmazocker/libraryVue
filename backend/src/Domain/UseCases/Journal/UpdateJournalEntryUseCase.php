<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Journal;

use App\Domain\DTO\Commands\UpdateJournalEntryCommand;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Domain\Services\JournalRatingWriter;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class UpdateJournalEntryUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        private readonly JournalRatingWriter        $ratings,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'UpdateJournalEntry'; }

    protected function doExecute($command): array
    {
        if (!$command instanceof UpdateJournalEntryCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateJournalEntryCommand');
        }

        if ($command->entryDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $command->entryDate) !== 1) {
            throw new InvalidArgumentException('entryDate must be YYYY-MM-DD');
        }
        if ($command->rating !== null && ($command->rating < 0.0 || $command->rating > 5.0)) {
            throw new InvalidArgumentException('Rating must be between 0 and 5');
        }

        // Se lee ANTES de escribir: la entrada dice de qué ítem es, y sin eso no
        // se puede propagar la valoración. Además resuelve la pertenencia.
        $entrada = $this->journal->findById($command->entryId, $command->userId);

        if ($entrada === null) {
            throw new RuntimeException('Journal entry not found.');
        }

        $this->journal->update($command->entryId, $command->userId, $command->entryDate, $command->rating);

        $this->ratings->write(
            $command->userId,
            $entrada->getMedia(),
            $entrada->getEntityId(),
            $command->rating,
            $entrada->getSource(),
            $entrada->getSourceId()
        );

        return ['id' => $command->entryId];
    }
}
