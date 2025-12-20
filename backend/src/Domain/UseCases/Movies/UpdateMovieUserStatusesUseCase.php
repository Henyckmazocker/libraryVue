<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateMovieStatusesCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateMovieUserStatusesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserMovieRepositoryInterface $userMovieRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof UpdateMovieStatusesCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateMovieStatusesCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if user has this movie in their library
        if (!$this->userMovieRepository->hasMovie($command->userId, $command->id->toString())) {
            throw new InvalidArgumentException('Movie not found in your library.');
        }

        // Update the user's statuses for this movie
        $this->userMovieRepository->updateStatuses($command->userId, $command->id->toString(), $command->statuses);
        
        return true;
    }

    protected function getLogContext(): string
    {
        return 'UpdateMovieUserStatusesUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Movie statuses updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update movie statuses';
    }
}
