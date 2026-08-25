<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\DTO\Commands\AddAlbumNoteCommand;
use App\Domain\Repository\Album\AlbumNoteRepositoryInterface;
use App\Domain\Repository\Album\AlbumRepositoryInterface;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Añadir una nota a un album.
 *
 * **Nuevo el 2026-08-25.** Hasta entonces `albums` era una de las dos entidades
 * que iban del controlador **directamente al repositorio**
 * (`AlbumController::addAlbumNote`), sin use case por medio. Eso no era solo una
 * incoherencia de estilo: sin use case no había dónde poner la guarda de
 * privacidad del feed sin ensuciar el controlador, así que dos de los cinco
 * medios se habrían quedado fuera del plan de eventos de notas.
 *
 * Calcado de `Movies/AddMovieNoteUseCase`, incluida la comprobación de que el
 * album está en la biblioteca del usuario antes de dejar escribir sobre él.
 */
class AddAlbumNoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly AlbumNoteRepositoryInterface $albumNoteRepository,
        private readonly UserAlbumRepositoryInterface $userAlbumRepository,
        // El repositorio de la entidad es para el evento del feed: necesita
        // título y portada, y el repositorio de usuario no los da.
        private readonly AlbumRepositoryInterface $albumRepository,
        private readonly FeedEventService $feedEvents,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * @param AddAlbumNoteCommand $command
     * @return array<string,mixed> los datos de la nota creada
     */
    protected function doExecute($command): array
    {
        if (!$command instanceof AddAlbumNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddAlbumNoteCommand');
        }

        if (!$this->userAlbumRepository->hasAlbum($command->userId, $command->albumId)) {
            throw new InvalidArgumentException('Album not found in your library');
        }

        $noteId = $this->albumNoteRepository->add(
            $command->userId,
            $command->albumId,
            $command->noteText,
            $command->noteType,
            $command->isPrivate
        );

        $this->logger->info('Album note added', [
            'note_id'   => $noteId,
            'album_id'   => $command->albumId,
            'note_type' => $command->noteType,
        ]);

        $this->publishToFeed($command);

        return [
            'id'         => $noteId,
            'note_text'  => $command->noteText,
            'note_type'  => $command->noteType,
            'is_private' => $command->isPrivate ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Publica la nota en el feed, **solo si es pública**.
     *
     * La guarda vive aquí y no en `FeedEventService`: ese servicio se traga sus
     * propios errores por diseño —un fallo del feed no puede tumbar el guardado
     * de una nota— y esconder ahí una regla de privacidad convertiría un fallo
     * silencioso en un **escape de privacidad silencioso**.
     */
    private function publishToFeed(AddAlbumNoteCommand $command): void
    {
        if ($command->isPrivate) {
            return;
        }

        $album = $this->albumRepository->findById($command->albumId);

        $this->feedEvents->recordNotesUpdated(
            userId:     $command->userId,
            entityType: 'album',
            entityId:   (string) $command->albumId,
            title:      $album?->getTitle() ?? '',
            cover:      $album?->getCoverUrl(),
            noteText:   $command->noteText,
            noteType:   $command->noteType
        );
    }

    protected function getLogContext(): string
    {
        return 'AddAlbumNoteUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Album note added successfully';
    }
}
