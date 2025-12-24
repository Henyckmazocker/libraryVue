<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetMoviesByUserQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetMoviesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserMovieRepositoryInterface $userMovieRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute with GetMoviesByUserQuery
     * Get movies for a specific user with optional filters
     */
    protected function doExecute($command): array
    {
        // Validate command
        if (!$command instanceof GetMoviesByUserQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetMoviesByUserQuery');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Get movies for this specific user
        $movies = $this->userMovieRepository->findByUser($command->userId, $command->filters);
        
        // Convert to array format if needed
        return array_map(function($movie) {
            return is_object($movie) && method_exists($movie, 'toArray') ? $movie->toArray() : $movie;
        }, $movies);
    }

    /**
     * Get log context for this use case
     */
    protected function getLogContext(): string
    {
        return 'GetMoviesUseCase';
    }
}
