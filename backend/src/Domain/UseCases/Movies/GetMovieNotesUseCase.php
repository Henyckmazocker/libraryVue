<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\DTO\Queries\GetMovieNotesQuery;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Use case for retrieving all notes for a movie
 */
class GetMovieNotesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MovieNoteRepositoryInterface $movieNoteRepository,
        private readonly UserMovieRepositoryInterface $userMovieRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute the use case
     *
     * @param GetMovieNotesQuery $query
     * @return array Array of notes
     */
    protected function doExecute($query): array
    {
        if (!$query instanceof GetMovieNotesQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetMovieNotesQuery');
        }

        // Verify user has the movie in their library
        if (!$this->userMovieRepository->hasMovie($query->userId, $query->movieIsbn)) {
            throw new InvalidArgumentException('Movie not found in your library');
        }

        // Get notes
        $notes = $this->movieNoteRepository->getByPage($query->userId, $query->movieIsbn);

        // Filter by note type if specified
        if ($query->noteType !== null) {
            $notes = array_filter($notes, function($note) use ($query) {
                return ($note['note_type'] ?? $note['noteType']) === $query->noteType;
            });
            $notes = array_values($notes); // Re-index array
        }

        $this->logger->debug('Retrieved movie notes', [
            'movie_isbn' => $query->movieIsbn,
            'count' => count($notes)
        ]);

        return $notes;
    }

    protected function getLogContext(): string
    {
        return 'GetMovieNotesUseCase';
    }
}
