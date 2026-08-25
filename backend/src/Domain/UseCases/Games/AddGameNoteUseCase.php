<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Games;

use App\Domain\DTO\Commands\AddGameNoteCommand;
use App\Domain\Repository\Game\GameNoteRepositoryInterface;
use App\Domain\Repository\Game\GameRepositoryInterface;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Añadir una nota a un game.
 *
 * **Nuevo el 2026-08-25.** Hasta entonces `games` era una de las dos entidades
 * que iban del controlador **directamente al repositorio**
 * (`GameController::addGameNote`), sin use case por medio. Eso no era solo una
 * incoherencia de estilo: sin use case no había dónde poner la guarda de
 * privacidad del feed sin ensuciar el controlador, así que dos de los cinco
 * medios se habrían quedado fuera del plan de eventos de notas.
 *
 * Calcado de `Movies/AddMovieNoteUseCase`, incluida la comprobación de que el
 * game está en la biblioteca del usuario antes de dejar escribir sobre él.
 */
class AddGameNoteUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly GameNoteRepositoryInterface $gameNoteRepository,
        private readonly UserGameRepositoryInterface $userGameRepository,
        // El repositorio de la entidad es para el evento del feed: necesita
        // título y portada, y el repositorio de usuario no los da.
        private readonly GameRepositoryInterface $gameRepository,
        private readonly FeedEventService $feedEvents,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * @param AddGameNoteCommand $command
     * @return array<string,mixed> los datos de la nota creada
     */
    protected function doExecute($command): array
    {
        if (!$command instanceof AddGameNoteCommand) {
            throw new InvalidArgumentException('Command must be an instance of AddGameNoteCommand');
        }

        if (!$this->userGameRepository->hasGame($command->userId, $command->gameId)) {
            throw new InvalidArgumentException('Game not found in your library');
        }

        $noteId = $this->gameNoteRepository->add(
            $command->userId,
            $command->gameId,
            $command->noteText,
            $command->noteType,
            $command->isPrivate
        );

        $this->logger->info('Game note added', [
            'note_id'   => $noteId,
            'game_id'   => $command->gameId,
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
    private function publishToFeed(AddGameNoteCommand $command): void
    {
        if ($command->isPrivate) {
            return;
        }

        $game = $this->gameRepository->findById($command->gameId);

        $this->feedEvents->recordNotesUpdated(
            userId:     $command->userId,
            entityType: 'game',
            entityId:   (string) $command->gameId,
            title:      $game?->getTitle() ?? '',
            cover:      $game?->getCoverUrl(),
            noteText:   $command->noteText,
            noteType:   $command->noteType
        );
    }

    protected function getLogContext(): string
    {
        return 'AddGameNoteUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Game note added successfully';
    }
}
