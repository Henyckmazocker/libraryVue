<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\DeleteMovieCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class DeleteMovieUseCase extends AbstractUseCase
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
        if (!$command instanceof DeleteMovieCommand) {
            throw new InvalidArgumentException('Command must be an instance of DeleteMovieCommand');
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

        // Remove the movie from user's library
        return $this->userMovieRepository->remove($command->userId, $command->id->toString());
    }

    protected function getLogContext(): string
    {
        return 'DeleteMovieUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Movie removed successfully from user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to remove movie from user library';
    }
}
