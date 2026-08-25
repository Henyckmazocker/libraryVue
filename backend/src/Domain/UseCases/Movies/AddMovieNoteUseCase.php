<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\DTO\Commands\AddMovieNoteCommand;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;
use App\Domain\Repository\Movie\MovieRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Use case for adding a note to a movie
 */
class AddMovieNoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MovieNoteRepositoryInterface $movieNoteRepository,
        private readonly UserMovieRepositoryInterface $userMovieRepository,
        // El repositorio de la entidad NO estaba antes: hace falta para el
        // evento del feed, que necesita título y portada. `UserMovieRepository`
        // no ofrece ningún «buscar una».
        private readonly MovieRepositoryInterface $movieRepository,
        private readonly FeedEventService $feedEvents,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Execute the use case
     *
     * @param AddMovieNoteCommand $command
     * @return array Note data
     */
    protected function doExecute($command): array
    {
        if (!$command instanceof AddMovieNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddMovieNoteCommand');
        }

        // Verify user has the movie in their library
        if (!$this->userMovieRepository->hasMovie($command->userId, $command->movieIsbn)) {
            throw new InvalidArgumentException('Movie not found in your library');
        }

        // Add note
        $noteId = $this->movieNoteRepository->add(
            userId: $command->userId,
            movieIsbn: $command->movieIsbn,
            noteText: $command->noteText,
            noteType: $command->noteType,
            isPrivate: $command->isPrivate
        );

        $this->logger->info('Movie note added', [
            'note_id' => $noteId,
            'movie_isbn' => $command->movieIsbn,
            'note_type' => $command->noteType
        ]);

        $this->publishToFeed($command);

        return [
            'id' => $noteId,
            'note_text' => $command->noteText,
            'note_type' => $command->noteType,
            'is_private' => $command->isPrivate ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Publica la nota en el feed, **solo si es pública**.
     *
     * La guarda vive aquí y no en `FeedEventService` a propósito: ese servicio
     * se traga sus propios errores por diseño —un fallo del feed no puede tumbar
     * el guardado de una nota— y esconder ahí una regla de privacidad
     * convertiría un fallo silencioso en un **escape de privacidad silencioso**.
     */
    private function publishToFeed(AddMovieNoteCommand $command): void
    {
        if ($command->isPrivate) {
            return;
        }

        $movie = $this->movieRepository->findById($command->movieIsbn);

        $this->feedEvents->recordNotesUpdated(
            userId: $command->userId,
            entityType: 'movie',
            entityId: $command->movieIsbn,
            title: $movie?->getTitle() ?? '',
            cover: $movie?->getCoverUrl(),
            noteText: $command->noteText,
            noteType: $command->noteType
        );
    }

    protected function getLogContext(): string
    {
        return 'AddMovieNoteUseCase';
    }
}
