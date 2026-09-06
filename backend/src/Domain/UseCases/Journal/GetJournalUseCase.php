<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Journal;

use App\Domain\DTO\Queries\GetJournalQuery;
use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class GetJournalUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly JournalRepositoryInterface $journal,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetJournal'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetJournalQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetJournalQuery');
        }

        $entradas = $this->journal->findByUser($query->userId, $query->limit, $query->offset, $query->media);
        $total    = $this->journal->countByUser($query->userId, $query->media);

        return [
            'entries' => array_map(static fn (JournalEntry $e): array => $e->toArray(), $entradas),
            'total'   => $total,
            'hasMore' => ($query->offset + count($entradas)) < $total,
        ];
    }
}
