<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Model\Movie;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\AddMovieCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class AddMovieUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MovieRepositoryInterface $movieRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserMovieRepositoryInterface $userMovieRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): Movie
    {
        if (!$command instanceof AddMovieCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddMovieCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Check if user already has this movie
        if ($this->userMovieRepository->hasMovie($command->userId, $command->id->toString())) {
            throw new InvalidArgumentException('You already have this movie in your library.');
        }

        // Check if movie exists in the system
        $existingMovie = $this->movieRepository->findById($command->id->toString());
        
        if (!$existingMovie) {
            // Movie doesn't exist, create it first
            $movie = Movie::fromArray($command->toArray());
            $this->movieRepository->save($movie);
        } else {
            // Movie exists, use existing movie data
            $movie = $existingMovie;
        }

        // Add the movie to user's library with their specific statuses
        $this->userMovieRepository->add(
            $command->userId, 
            $command->id->toString(), 
            $command->statuses,
            $command->userRating?->toFloat(),
            null, // personalNotes - not provided in AddMovieCommand
            null  // consumedAt - not provided in AddMovieCommand
        );
        
        // Note: Rating is already handled in the add() method above
        // No need for separate updateRating call
        
        return $movie;
    }

    protected function getLogContext(): string
    {
        return 'AddMovieUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Movie added successfully to user library';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to add movie to user library';
    }
}
