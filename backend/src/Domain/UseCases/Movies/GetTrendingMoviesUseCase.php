<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetTrendingMoviesQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

/**
 * Use case for getting trending movies across all users
 */
class GetTrendingMoviesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserMovieRepositoryInterface $userMovieRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetTrendingMoviesQuery
     * Get trending movies based on user activity and ratings
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetTrendingMoviesQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetTrendingMoviesQuery');
        }

        $this->logger->info('Getting trending movies', [
            'limit' => $command->limit,
            'daysWindow' => $command->daysWindow
        ]);

        // Get trending movies from repository
        $trendingMovies = $this->userMovieRepository->getTrendingMovies(
            $command->limit,
            $command->daysWindow,
            $command->userId
        );

        $this->logger->info('Trending movies retrieved', [
            'count' => count($trendingMovies)
        ]);

        return $trendingMovies;
    }

    /**
     * Get log context for this use case
     */
    protected function getLogContext(): string
    {
        return 'GetTrendingMoviesUseCase';
    }
}
