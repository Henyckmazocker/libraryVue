<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Social;

use App\Domain\DTO\Commands\CreateFeedEventCommand;
use App\Domain\Model\FeedEvent;
use App\Domain\Repository\Social\FeedEventRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class CreateFeedEventUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly FeedEventRepositoryInterface $feedEventRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'CreateFeedEvent'; }

    protected function doExecute($command): FeedEvent
    {
        if (!$command instanceof CreateFeedEventCommand) {
            throw new InvalidArgumentException('Command must be an instance of CreateFeedEventCommand');
        }

        $event = new FeedEvent(
            id:          null,
            userId:      $command->userId,
            eventType:   $command->eventType,
            entityType:  $command->entityType,
            entityId:    $command->entityId,
            entityTitle: $command->entityTitle,
            entityCover: $command->entityCover,
            metadata:    $command->metadata
        );

        return $this->feedEventRepository->save($event);
    }
}
