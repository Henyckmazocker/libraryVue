<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Queries\GetMyClubsQuery;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * No comprueba pertenencia porque su filtro ES la pertenencia: la consulta sale
 * de `club_member` por `idx_club_member_user`, así que un club del que no eres
 * miembro sencillamente no aparece. Es el mismo criterio que `get_my_lists`.
 */
class GetMyClubsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface       $clubRepository,
        private readonly ClubMemberRepositoryInterface $memberRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetMyClubs'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetMyClubsQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetMyClubsQuery');
        }

        $clubs = $this->clubRepository->findForUser($query->userId);

        return [
            'clubs' => array_map(fn ($club) => $club->toArray() + [
                'is_owner'     => $club->isOwnedBy($query->userId),
                'member_count' => $this->memberRepository->countMembers((int) $club->getId()),
            ], $clubs),
        ];
    }
}
