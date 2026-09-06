<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\Book\ReadingProgressRepositoryInterface;
use App\Domain\Repository\Book\ReadingSessionRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Model\JournalEntry;
use App\Domain\Services\JournalService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateReadingProgressCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * UseCase para actualizar el progreso de lectura con lógica de negocio completa
 *
 * Funcionalidad:
 * 1. Si NO hay sesión activa → crea una nueva automáticamente
 * 2. Si el estado NO es 'reading' o 're-reading' → lo cambia automáticamente
 * 3. Al llegar al 100% → cambia a 'read', quita 'reading'/'re-reading', finaliza sesión
 */
class UpdateReadingProgressUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ReadingProgressRepositoryInterface $progressRepository,
        private readonly ReadingSessionRepositoryInterface $sessionRepository,
        private readonly UserBookEditionRepositoryInterface $userBookEditionRepository,
        private readonly EditionRepositoryInterface $editionRepository,
        private readonly JournalService $journalService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): array
    {
        if (!$command instanceof UpdateReadingProgressCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateReadingProgressCommand');
        }

        $userId = $command->userId;
        $isbn = $command->isbn;
        $currentPage = $command->currentPage;

        // 1. Obtener editionId desde ISBN
        $edition = $this->editionRepository->findByIsbn($isbn);
        if (!$edition) {
            throw new RuntimeException("Edition not found for ISBN: {$isbn}");
        }
        $editionId = $edition->getEditionId();

        // 2. Obtener user_book_edition
        $userBookEdition = $this->userBookEditionRepository->findByUserAndEdition($userId, $editionId);
        if (!$userBookEdition) {
            throw new RuntimeException("User book edition not found");
        }

        // 3. Obtener páginas totales
        $totalPages = $edition->getPages() ?? 0;
        if ($totalPages === 0) {
            // No page count: save progress + history but skip session logic
            $this->progressRepository->updateWithSession($userId, $isbn, $currentPage, 'advance', null);
            return [
                'currentPage' => $currentPage,
                'totalPages' => 0,
                'percentage' => 0,
                'isComplete' => false,
                'sessionId' => null,
                'updatedStatuses' => $this->userBookEditionRepository->getStatusesForEdition($userId, $editionId)
            ];
        }

        // 4. Verificar si hay sesión activa
        $activeSession = $this->sessionRepository->getActive($userId, $isbn);
        $sessionId = null;

        if (!$activeSession) {
            // NO hay sesión activa → crear una nueva
            $this->logger->info('No active session found, creating new one', [
                'userId' => $userId,
                'isbn' => $isbn
            ]);

            $sessionId = $this->sessionRepository->create(
                $userId,
                $isbn,
                null, // session_number (auto-generated)
                $currentPage // start_page
            );

            // ✅ Actualizar estados basados en la nueva sesión activa
            $this->sessionRepository->updateBookStatusesBasedOnSessions($userId, $isbn);

            $activeSession = $this->sessionRepository->getActive($userId, $isbn);
        } else {
            $sessionId = $activeSession['id'];
        }

        // 5. Actualizar el progreso
        $this->progressRepository->updateWithSession(
            $userId,
            $isbn,
            $currentPage,
            'advance',
            null
        );

        // 6. Verificar si llegó al 100%
        $percentage = ($currentPage / $totalPages) * 100;
        $isComplete = $percentage >= 100;

        if ($isComplete) {
            $this->logger->info('Book completed at 100%, finalizing', [
                'userId' => $userId,
                'isbn' => $isbn,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages
            ]);

            // Completar la sesión de lectura
            if ($sessionId) {
                $this->sessionRepository->complete(
                    $sessionId,
                    $currentPage // end_page
                );

                // Y apuntarla en el diario. El `source_id` es el id de la
                // sesión, no la fecha: así, cerrar dos veces la misma sesión —o
                // editarle la fecha después— MUEVE su entrada en vez de crear
                // otra. Va aquí y no en el cambio de estado a `read` para que
                // terminar un libro no apunte dos entradas el mismo día: la
                // clave del origen `status` es distinta y no las deduplicaría.
                $edicion = $this->editionRepository->findByIsbn($isbn);

                if ($edicion) {
                    $this->journalService->record(
                        userId:    $userId,
                        media:     JournalEntry::MEDIA_BOOK,
                        entityId:  $isbn,
                        title:     $edicion->getTitle(),
                        cover:     $edicion->getCoverUrlMedium(),
                        entryDate: date('Y-m-d'),
                        rating:    null,
                        source:    JournalEntry::SOURCE_READING_SESSION,
                        sourceId:  (string) $sessionId
                    );
                }
            }

            // ✅ Actualizar estados basados en la sesión completada
            // Esto añadirá 'read' y quitará 'reading'/'re-reading' automáticamente
            $this->sessionRepository->updateBookStatusesBasedOnSessions($userId, $isbn);
        }

        // Obtener estados actualizados para devolverlos en la respuesta
        $updatedStatuses = $this->userBookEditionRepository->getStatusesForEdition($userId, $editionId);

        return [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'percentage' => round($percentage, 2),
            'isComplete' => $isComplete,
            'sessionId' => $sessionId,
            'updatedStatuses' => $updatedStatuses // ✅ Incluir estados actualizados
        ];
    }

    protected function getLogContext(): string
    {
        return 'UpdateReadingProgressUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Reading progress updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update reading progress';
    }
}
