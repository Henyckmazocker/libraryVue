<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\DTO\Commands\AddJournalEntryCommand;
use App\Domain\DTO\Commands\DeleteJournalEntryCommand;
use App\Domain\DTO\Commands\UpdateJournalEntryCommand;
use App\Domain\DTO\Queries\GetJournalQuery;
use App\Domain\DTO\Queries\GetUserJournalQuery;
use App\Domain\UseCases\Journal\AddJournalEntryUseCase;
use App\Domain\UseCases\Journal\DeleteJournalEntryUseCase;
use App\Domain\UseCases\Journal\GetJournalUseCase;
use App\Domain\UseCases\Journal\GetUserJournalUseCase;
use App\Domain\UseCases\Journal\UpdateJournalEntryUseCase;
use RuntimeException;

class JournalController extends BaseController
{
    public function __construct(
        private readonly GetJournalUseCase         $getJournalUseCase,
        private readonly GetUserJournalUseCase     $getUserJournalUseCase,
        private readonly AddJournalEntryUseCase    $addJournalEntryUseCase,
        private readonly UpdateJournalEntryUseCase $updateJournalEntryUseCase,
        private readonly DeleteJournalEntryUseCase $deleteJournalEntryUseCase
    ) {}

    public function getJournal(GetJournalQuery $query): array
    {
        $result = $this->getJournalUseCase->execute($query);
        return $this->successResponse('Journal retrieved', $result);
    }

    /**
     * El diario de otro, para su perfil público. Sin amistad aceptada o con el
     * interruptor apagado devuelve la lista vacía y un 200, no un 403: la
     * decisión está razonada en `GetUserJournalUseCase`.
     */
    public function getUserJournal(GetUserJournalQuery $query): array
    {
        $result = $this->getUserJournalUseCase->execute($query);
        return $this->successResponse('Journal retrieved', $result);
    }

    public function addEntry(AddJournalEntryCommand $command): array
    {
        $result = $this->addJournalEntryUseCase->execute($command);
        return $this->successResponse('Journal entry added', $result);
    }

    /**
     * El `RuntimeException` de «no existe o no es tuya» se traduce a **404**
     * aquí, como en `SocialController.php:61-89`. Sin esta captura sube al
     * `catch (\Exception)` de `ActionRouter.php:230`, que devuelve un 500 con
     * «An unexpected error occurred.»: el cliente no puede distinguir una
     * entrada que ya no está de un backend roto.
     */
    public function updateEntry(UpdateJournalEntryCommand $command): array
    {
        try {
            $result = $this->updateJournalEntryUseCase->execute($command);
            return $this->successResponse('Journal entry updated', $result);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function deleteEntry(DeleteJournalEntryCommand $command): array
    {
        try {
            $this->deleteJournalEntryUseCase->execute($command);
            return $this->successResponse('Journal entry deleted');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
