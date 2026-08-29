<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Commands\CreateClubCommand;
use App\Domain\Model\Club;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * El único use case de clubs que NO comprueba pertenencia: no hay club todavía
 * sobre el que preguntar nada, y el dueño es quien lo crea.
 *
 * **El dueño se da de alta como miembro en el acto**, y eso no es redundante
 * con `club.owner_id`: es lo que permite que `findForUser` sea una sola
 * consulta sobre `club_member` en vez del `UNION` que necesitan las listas, y
 * lo que hace que el dueño aparezca en la pantalla del club con su progreso
 * como cualquier otro. Sin ello, el creador de un club no saldría en él.
 */
class CreateClubUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubRepositoryInterface       $clubRepository,
        private readonly ClubMemberRepositoryInterface $memberRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'CreateClub'; }

    protected function doExecute($command): Club
    {
        if (!$command instanceof CreateClubCommand) {
            throw new InvalidArgumentException('Command must be an instance of CreateClubCommand');
        }

        $club = $this->clubRepository->save(new Club(
            id:          null,
            ownerId:     $command->ownerId,
            name:        $command->name,
            description: $command->description
        ));

        $this->memberRepository->add((int) $club->getId(), $command->ownerId);

        return $club;
    }
}
