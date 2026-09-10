<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class UpdateBookRatingUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserBookEditionRepositoryInterface $userBookEditionRepository,
        private readonly EditionRepositoryInterface $editionRepository,
        private readonly FeedEventService $feedEventService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): bool
    {
        // Validate command
        if (!$command instanceof UpdateBookRatingCommand) {
            throw new InvalidArgumentException('Command must be an instance of UpdateBookRatingCommand');
        }

        // Validate user exists
        $user = $this->userRepository->findById($command->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$command->userId} not found");
        }

        // Find edition by ISBN
        $isbn = $command->isbn->toString();
        $edition = $this->editionRepository->findByIsbn($isbn);
        
        if (!$edition) {
            throw new InvalidArgumentException("Book with ISBN {$isbn} not found in database.");
        }

        // Check if user has this edition in their library
        if (!$this->userBookEditionRepository->hasEdition($command->userId, $edition->getEditionId())) {
            throw new InvalidArgumentException('Book not found in your library.');
        }

        // Se escribe `edition_rating`, NO `work_rating`: es la columna que la ficha
        // enseña —`UserBookEdition::toArray()` la publica como `user_rating`,
        // `personal_rating` y `rating` (`UserBookEdition.php:239-243`)— y la misma que
        // guarda el modal de edición por `edit_user_book`
        // (`MySqlUserBookRepository.php:85-87`). Hasta el 2026-09-09 esta acción
        // escribía `work_rating` y dejaba `edition_rating` a NULL, así que valorar
        // desde la ficha guardaba en una columna que nadie lee y borraba la que sí:
        // la estrella se pintaba y desaparecía al recargar.
        //
        // `work_rating` —la valoración de la OBRA, otro concepto del modelo
        // Work/Edition— se relee y se reescribe igual porque el UPDATE del
        // repositorio toca las dos columnas de una vez.
        //
        // `null` no es «no hay dato»: es «borra mi valoración», y tiene que llegar a
        // la columna tal cual.
        $actual = $this->userBookEditionRepository->findByUserAndEdition(
            $command->userId,
            $edition->getEditionId()
        );

        $this->userBookEditionRepository->updateRating(
            $command->userId,
            $edition->getEditionId(),
            $actual?->getWorkRating()?->toFloat(), // work_rating: se conserva
            $command->rating?->toFloat()           // edition_rating: lo que la ficha lee
        );

        // Borrar una valoración no es «ha valorado»: sin esta guarda el feed
        // pintaría una estrella que ya no existe.
        if ($command->rating !== null) {
            $this->feedEventService->recordItemRated(
                $command->userId,
                'book',
                $command->isbn->toString(),
                $edition->getTitle(),
                null,
                $command->rating->toFloat()
            );
        }
        
        return true;
    }

    protected function getLogContext(): string
    {
        return 'UpdateBookRatingUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Book rating updated successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to update book rating';
    }
} 