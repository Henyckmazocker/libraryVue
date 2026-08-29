<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\DTO\Commands\AcceptClubInvitationCommand;
use App\Domain\DTO\Commands\AdvanceClubRoundCommand;
use App\Domain\DTO\Commands\FinishClubPickCommand;
use App\Domain\DTO\Commands\CreateClubCommand;
use App\Domain\DTO\Commands\InviteToClubCommand;
use App\Domain\DTO\Commands\LeaveClubCommand;
use App\Domain\DTO\Commands\ProposeClubItemCommand;
use App\Domain\DTO\Commands\SetClubPickCommand;
use App\Domain\DTO\Commands\VoteClubProposalCommand;
use App\Domain\DTO\Queries\GetClubNotesQuery;
use App\Domain\DTO\Queries\GetClubProgressQuery;
use App\Domain\DTO\Queries\GetClubQuery;
use App\Domain\DTO\Queries\GetMyClubsQuery;
use App\Domain\UseCases\Clubs\AcceptClubInvitationUseCase;
use App\Domain\UseCases\Clubs\ClubPickConflictException;
use App\Domain\UseCases\Clubs\CreateClubUseCase;
use App\Domain\UseCases\Clubs\FinishClubPickUseCase;
use App\Domain\UseCases\Clubs\GetClubNotesUseCase;
use App\Domain\UseCases\Clubs\GetClubProgressUseCase;
use App\Domain\UseCases\Clubs\GetClubUseCase;
use App\Domain\UseCases\Clubs\GetMyClubsUseCase;
use App\Domain\UseCases\Clubs\InviteToClubUseCase;
use App\Domain\UseCases\Clubs\CloseClubVoteUseCase;
use App\Domain\UseCases\Clubs\ClubRoundConflictException;
use App\Domain\UseCases\Clubs\OpenClubVoteUseCase;
use App\Domain\UseCases\Clubs\LeaveClubUseCase;
use App\Domain\UseCases\Clubs\ProposeClubItemUseCase;
use App\Domain\UseCases\Clubs\SetClubPickUseCase;
use App\Domain\UseCases\Clubs\VoteClubProposalUseCase;
use DomainException;
use RuntimeException;

/**
 * Los clubs tienen controller propio, igual que las listas y por el mismo
 * motivo: son cinco acciones hoy y nueve cuando lleguen el ítem activo y las
 * notas, y no caben en `SocialController` sin convertirlo en un cajón.
 *
 * `DomainException` significa **403** en todo este controller —«existe pero no
 * eres miembro», o «no eres el dueño»— y `RuntimeException` **404** o error de
 * negocio, igual que en `ListController` y en
 * `SocialController::resolveRecommendation`.
 *
 * La excepción a la excepción es `ClubPickConflictException`, que sale **409**:
 * «ya hay un ítem activo» no es una petición mal formada sino un conflicto con
 * el estado, y el frontend lo necesita distinguido para ofrecer «termina el
 * actual primero». Se captura ANTES que `RuntimeException`, de la que hereda —
 * al revés nunca entraría en su rama.
 */
class ClubController extends BaseController
{
    public function __construct(
        private readonly CreateClubUseCase           $createClubUseCase,
        private readonly GetMyClubsUseCase           $getMyClubsUseCase,
        private readonly GetClubUseCase              $getClubUseCase,
        private readonly InviteToClubUseCase         $inviteToClubUseCase,
        private readonly AcceptClubInvitationUseCase $acceptClubInvitationUseCase,
        private readonly LeaveClubUseCase            $leaveClubUseCase,
        private readonly SetClubPickUseCase          $setClubPickUseCase,
        private readonly FinishClubPickUseCase       $finishClubPickUseCase,
        private readonly GetClubProgressUseCase      $getClubProgressUseCase,
        private readonly GetClubNotesUseCase         $getClubNotesUseCase,
        private readonly ProposeClubItemUseCase      $proposeClubItemUseCase,
        private readonly VoteClubProposalUseCase     $voteClubProposalUseCase,
        private readonly OpenClubVoteUseCase         $openClubVoteUseCase,
        private readonly CloseClubVoteUseCase        $closeClubVoteUseCase
    ) {}

    public function createClub(CreateClubCommand $command): array
    {
        $club = $this->createClubUseCase->execute($command);

        return $this->successResponse('Club created', ['clubId' => $club->getId()], 201);
    }

    public function getMyClubs(GetMyClubsQuery $query): array
    {
        return $this->successResponse('Clubs retrieved', $this->getMyClubsUseCase->execute($query));
    }

    public function getClub(GetClubQuery $query): array
    {
        try {
            return $this->successResponse('Club retrieved', $this->getClubUseCase->execute($query));
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function inviteToClub(InviteToClubCommand $command): array
    {
        try {
            $invitacion = $this->inviteToClubUseCase->execute($command);

            return $this->successResponse('Invitation sent', [
                'recommendationId' => $invitacion->getId(),
            ], 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function acceptClubInvitation(AcceptClubInvitationCommand $command): array
    {
        try {
            return $this->successResponse(
                'Invitation accepted',
                $this->acceptClubInvitationUseCase->execute($command)
            );
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function leaveClub(LeaveClubCommand $command): array
    {
        try {
            return $this->successResponse('Left the club', $this->leaveClubUseCase->execute($command));
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function setClubPick(SetClubPickCommand $command): array
    {
        try {
            $pick = $this->setClubPickUseCase->execute($command);

            return $this->successResponse('Club pick set', ['pickId' => $pick->getId()], 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (ClubPickConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function finishClubPick(FinishClubPickCommand $command): array
    {
        try {
            return $this->successResponse('Club pick finished', $this->finishClubPickUseCase->execute($command));
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function getClubProgress(GetClubProgressQuery $query): array
    {
        try {
            return $this->successResponse('Club progress retrieved', $this->getClubProgressUseCase->execute($query));
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * Proponer es de cualquier MIEMBRO, no del dueño, y sus tres negativas son
     * tres códigos distintos porque el frontend hace tres cosas distintas:
     * **403** (no eres miembro, o te toca rotar) esconde el botón con un aviso,
     * **409** (la ronda ya está votando) recarga porque la pantalla está
     * desfasada, y el **400** de «ya propusiste» sale del `InvalidArgumentException`
     * que el `ActionRouter` ya traduce, igual que los de `fromArray`.
     *
     * `ClubRoundConflictException` se captura ANTES que `RuntimeException`, de
     * la que hereda — al revés nunca entraría en su rama, exactamente como pasa
     * con `ClubPickConflictException` en `setClubPick`.
     */
    public function proposeClubItem(ProposeClubItemCommand $command): array
    {
        try {
            $propuesta = $this->proposeClubItemUseCase->execute($command);

            return $this->successResponse('Item proposed', ['proposalId' => $propuesta->getId()], 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (ClubRoundConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * Votar es de cualquier MIEMBRO, como proponer. El **404** cubre dos cosas
     * distintas que para el cliente son la misma —«esa propuesta no es de esta
     * ronda» y «quedó eliminada en el desempate»—: en las dos, lo que tiene que
     * hacer es recargar y votar otra cosa.
     */
    public function voteClubProposal(VoteClubProposalCommand $command): array
    {
        try {
            return $this->successResponse('Vote cast', $this->voteClubProposalUseCase->execute($command));
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (ClubRoundConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * Las dos válvulas del dueño. Comparten command —solo llevan el club— y
     * comparten traducción de errores; lo que cambia es la fase que cada una
     * exige, y eso vive en su use case.
     */
    public function openClubVote(AdvanceClubRoundCommand $command): array
    {
        try {
            return $this->successResponse('Vote opened', $this->openClubVoteUseCase->execute($command));
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (ClubRoundConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function closeClubVote(AdvanceClubRoundCommand $command): array
    {
        try {
            return $this->successResponse('Vote closed', $this->closeClubVoteUseCase->execute($command));
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (ClubRoundConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function getClubNotes(GetClubNotesQuery $query): array
    {
        try {
            return $this->successResponse('Club notes retrieved', $this->getClubNotesUseCase->execute($query));
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
