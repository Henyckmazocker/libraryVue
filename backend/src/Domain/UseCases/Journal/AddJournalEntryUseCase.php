<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Journal;

use App\Domain\DTO\Commands\AddJournalEntryCommand;
use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Domain\Services\JournalItemResolver;
use App\Domain\Services\JournalRatingWriter;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Alta manual: «el 12 de julio vi Dune».
 *
 * El título y la portada NO viajan en la petición: se resuelven contra el
 * catálogo, para que el cliente no pueda escribir una entrada que diga lo que
 * quiera sobre un ítem.
 */
class AddJournalEntryUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        private readonly JournalItemResolver        $resolver,
        private readonly JournalRatingWriter        $ratings,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'AddJournalEntry'; }

    protected function doExecute($command): array
    {
        if (!$command instanceof AddJournalEntryCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddJournalEntryCommand');
        }

        if (!in_array($command->media, JournalEntry::MEDIA, true)) {
            throw new InvalidArgumentException("Unknown media: {$command->media}");
        }
        if ($command->entityId === '') {
            throw new InvalidArgumentException('entityId is required');
        }

        $item = $this->resolver->resolve($command->media, $command->entityId);

        if ($item === null) {
            throw new InvalidArgumentException('That item is not in the catalog.');
        }

        [$titulo, $portada] = $item;

        // El constructor de `JournalEntry` valida la fecha y el rango del rating.
        $id = $this->journal->add($command->toEntry($titulo, $portada));

        // «La última entrada manda»: se propaga después de guardar, y si falla
        // no se lleva por delante la entrada — el escritor se traga sus errores.
        $this->ratings->write(
            $command->userId,
            $command->media,
            $command->entityId,
            $command->rating
        );

        return ['id' => $id];
    }
}
