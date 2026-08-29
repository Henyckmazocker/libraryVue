<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Queries\GetClubQuery;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubPickRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\Services\ClubCompletion;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * La pantalla del club.
 *
 * **No hay visibilidad que consultar**: un club no es público, colaborativo ni
 * privado — o eres miembro o no lo eres. Por eso no existe un `ClubAccess`
 * equivalente a `ListAccess`: no habría tabla de verdad que escribir, solo un
 * `isMember`. La amistad tampoco entra: se es amigo para poder ser invitado,
 * pero lo que da acceso es haber aceptado.
 *
 * **Y aquí es donde el club avanza solo.** El proyecto no tiene cron ni
 * workers (`Infrastructure/Http/PostResponse.php:12`), así que el cierre
 * automático del ítem se evalúa al leer, que es cuando la consulta de progreso
 * se hace de todas formas. Eso significa que esta LECTURA ESCRIBE, con dos
 * reglas que no se pueden relajar (ver `cerrarSiTodosAcabaron`).
 */
class GetClubUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface       $clubRepository,
        private readonly ClubMemberRepositoryInterface $memberRepository,
        private readonly ClubPickRepositoryInterface   $pickRepository,
        private readonly ClubCompletion                $completion,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetClub'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetClubQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetClubQuery');
        }

        $club = $this->clubRepository->findById($query->clubId);
        if ($club === null) {
            throw new RuntimeException('Club not found');
        }

        // El controller lo traduce a 403, y se distingue de «no existe» (404) a
        // propósito, igual que en `GetListUseCase`.
        if (!$this->memberRepository->isMember($query->clubId, $query->userId)) {
            throw new DomainException('You are not a member of this club');
        }

        $activo = $this->pickRepository->findActive($query->clubId);
        if ($activo !== null && $this->cerrarSiTodosAcabaron($activo)) {
            // Se acaba de cerrar: se relee para que la respuesta lleve el
            // `finished_at` real de la base y no una fecha calculada aquí.
            $activo = null;
        }

        return [
            'club' => $club->toArray() + [
                // Renombrar, invitar, elegir ítem, cerrarlo y borrar son del
                // dueño. Se manda resuelto y no se recalcula en el cliente: la
                // regla vive en el servidor.
                'is_owner' => $club->isOwnedBy($query->userId),
            ],
            'members' => $this->memberRepository->findByClub($query->clubId),
            'pick'    => $activo?->toArray(),
            'history' => array_map(
                static fn ($pick) => $pick->toArray(),
                $this->pickRepository->findHistory($query->clubId)
            ),
        ];
    }

    /**
     * El cierre automático, con las dos guardas que lo hacen seguro:
     *
     *  - El `UPDATE` de `finish()` lleva `AND finished_at IS NULL`, así que dos
     *    lecturas simultáneas no pueden cerrar dos veces ni pisar una fecha ya
     *    puesta. La que pierde recibe `false`.
     *  - **Un fallo aquí no puede tumbar la lectura.** Un club que no se puede
     *    pintar es peor que un club que se cierra en la siguiente visita, así
     *    que se traga la excepción y se deja el ítem activo.
     */
    private function cerrarSiTodosAcabaron($pick): bool
    {
        try {
            if (!$this->completion->everyoneFinished($pick)) {
                return false;
            }

            return $this->pickRepository->finish((int) $pick->getId());
        } catch (Throwable $e) {
            $this->logger->warning('[GetClub] Auto-finish skipped', [
                'club_id' => $pick->getClubId(),
                'pick_id' => $pick->getId(),
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }
}
