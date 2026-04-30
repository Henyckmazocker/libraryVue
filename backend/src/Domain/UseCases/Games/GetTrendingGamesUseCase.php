<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Games;

use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetTrendingGamesQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

/**
 * Use case for getting trending games across all users
 */
class GetTrendingGamesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserGameRepositoryInterface $userGameRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetTrendingGamesQuery
     * Get trending games based on user activity and ratings
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetTrendingGamesQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetTrendingGamesQuery');
        }

        $this->logger->info('Getting trending games', [
            'limit' => $command->limit,
            'daysWindow' => $command->daysWindow
        ]);

        // Get trending games from repository
        $trendingGames = $this->userGameRepository->getTrendingGames(
            $command->limit,
            $command->daysWindow,
            $command->userId
        );

        $this->logger->info('Trending games retrieved', [
            'count' => count($trendingGames)
        ]);

        return $trendingGames;
    }

    /**
     * Get log context for this use case
     */
    protected function getLogContext(): string
    {
        return 'GetTrendingGamesUseCase';
    }
}
