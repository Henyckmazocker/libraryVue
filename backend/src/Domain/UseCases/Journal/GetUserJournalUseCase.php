<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Journal;

use App\Domain\DTO\Queries\GetUserJournalQuery;
use App\Domain\Model\JournalEntry;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\Social\PrivacySettingsRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * El diario de OTRO usuario, para la sección de su perfil público.
 *
 * Tres puertas antes de llegar al diario —que el usuario exista, que la amistad
 * esté aceptada, que él haya encendido `show_journal`— y **ninguna de ellas
 * devuelve error**: todas devuelven la lista vacía. Un 403, o un «usuario no
 * encontrado», confirmarían que hay un diario detrás; la lista vacía no
 * distingue «no lo enseña» de «no tiene nada». Por eso este caso de uso NO
 * reutiliza `GetPublicProfileUseCase`, que sí lanza `RuntimeException` cuando el
 * username no existe.
 *
 * Y el filtro es del servidor, no del cliente: si alguna puerta se cierra, el
 * repositorio del diario ni se consulta.
 */
class GetUserJournalUseCase extends AbstractUseCase
{
    private const EMPTY = ['entries' => [], 'total' => 0, 'hasMore' => false];

    public function __construct(
        private readonly UserRepositoryInterface            $users,
        private readonly FriendshipRepositoryInterface      $friendships,
        private readonly PrivacySettingsRepositoryInterface $privacySettings,
        private readonly JournalRepositoryInterface         $journal,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetUserJournal'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetUserJournalQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetUserJournalQuery');
        }

        if ($query->username === '') {
            return self::EMPTY;
        }

        $owner = $this->users->findByUsername($query->username);
        if ($owner === null) {
            return self::EMPTY;
        }

        $ownerId = $owner->getId();

        // La amistad la dice `friendships`; el permiso, el interruptor. Son dos
        // preguntas distintas y las dos tienen que decir que sí. Nadie es amigo
        // de sí mismo, así que tu propio perfil público tampoco enseña tu
        // diario: para eso está `get_journal`.
        $friendship = $this->friendships->findByUsers($query->viewerUserId, $ownerId);
        if ($friendship === null || !$friendship->isAccepted()) {
            return self::EMPTY;
        }

        if (!$this->privacySettings->findByUserId($ownerId)->showJournal()) {
            return self::EMPTY;
        }

        $entradas = $this->journal->findByUser($ownerId, $query->limit, $query->offset);
        $total    = $this->journal->countByUser($ownerId);

        return [
            'entries' => array_map(static fn (JournalEntry $e): array => $e->toArray(), $entradas),
            'total'   => $total,
            'hasMore' => ($query->offset + count($entradas)) < $total,
        ];
    }
}
