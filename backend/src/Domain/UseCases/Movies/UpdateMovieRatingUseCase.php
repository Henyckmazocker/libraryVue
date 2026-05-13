<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateMovieRatingCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateMovieRatingUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserMovieRepositoryInterface $userMovieRepository,
        private readonly MovieRepositoryInterface $movieRepository,
        private readonly FeedEventService $feedEventService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        if (!$command instanceof UpdateMovieRatingCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateMovieRatingCommand');
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

        // Update the user's rating (Rating VO already validated in constructor)
        $this->userMovieRepository->updateRating(
            $command->userId, 
            $command->id->toString(), 
            $command->rating->toFloat()
        );

        $movie = $this->movieRepository->findById($command->id->toString());
        if ($movie) {
            $this->feedEventService->recordItemRated(
                $command->userId,
                'movie',
                $command->id->toString(),
                $movie->getTitle(),
                $movie->getCoverUrl(),
                $command->rating->toFloat()
            );
        }
        
        return true;
    }

    protected function getLogContext(): string
    {
        return 'UpdateMovieRatingUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Movie rating updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update movie rating';
    }
}
