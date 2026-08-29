<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Queries\GetClubProgressQuery;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubPickRepositoryInterface;
use App\Domain\Repository\Club\ClubProgressRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Por dónde va cada miembro con el ítem activo.
 *
 * **Va separada de `get_club` a propósito**: es de las dos que cambian mientras
 * la página está abierta, y la única que conviene poder refrescar sola sin
 * volver a traerse el club, los miembros y el historial entero.
 *
 * **Aquí no hay interruptor de privacidad, y es deliberado.**
 * `user_privacy_settings` tiene seis por tipo de evento del feed y **ninguno**
 * cubre «mi progreso dentro de un club»; usar `show_reading_sessions` sería
 * reutilizar mal un ajuste que significa otra cosa. La decisión escrita en el
 * plan es que **entrar en un club ES el consentimiento** de que sus miembros
 * vean tu progreso sobre el ítem activo. El club es voluntario y se puede
 * abandonar: ese es el control, y `leave_club` es donde vive.
 */
class GetClubProgressUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubMemberRepositoryInterface   $memberRepository,
        private readonly ClubPickRepositoryInterface     $pickRepository,
        private readonly ClubProgressRepositoryInterface $progressRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetClubProgress'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetClubProgressQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetClubProgressQuery');
        }

        if (!$this->memberRepository->isMember($query->clubId, $query->userId)) {
            throw new DomainException('You are not a member of this club');
        }

        $activo = $this->pickRepository->findActive($query->clubId);

        // Sin ítem activo no hay progreso que medir, y eso NO es un error: es
        // el estado normal del club entre un ítem y el siguiente. Se devuelve
        // la misma forma con la lista vacía para que la pantalla no tenga que
        // distinguir dos contratos.
        if ($activo === null) {
            return ['axis' => null, 'members' => []];
        }

        return [
            // El eje se manda resuelto porque solo el servidor sabe que este
            // `entity_type = 'movie'` es en realidad una serie: lo dice
            // `movie.media_type`, y el ENUM de `club_pick` no lo distingue.
            'axis'    => $this->progressRepository->axisFor($activo->getEntityType(), $activo->getEntityId()),
            'members' => $this->progressRepository->findProgress(
                $query->clubId,
                $activo->getEntityType(),
                $activo->getEntityId()
            ),
        ];
    }
}
