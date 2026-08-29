<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use App\Domain\DTO\Queries\GetClubNotesQuery;
use App\Domain\Model\ClubPick;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubNoteRepositoryInterface;
use App\Domain\Repository\Club\ClubPickRepositoryInterface;
use App\Domain\Repository\Club\ClubProgressRepositoryInterface;
use App\Domain\Services\SpoilerRule;
use App\Domain\UseCases\AbstractUseCase;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Las notas públicas del club sobre el ítem activo, **ya marcadas**.
 *
 * Aquí está lo único irreversible de todo el plan: destripar. De ahí las dos
 * reglas que gobiernan este use case y que no se pueden relajar:
 *
 *  1. **La decisión se toma en el servidor**, con `SpoilerRule`. El frontend no
 *     recibe mi progreso ni el punto de cada nota para compararlos: recibe el
 *     veredicto.
 *  2. **Con `isSpoiler: true`, `text` viaja como `null`.** No como cadena
 *     vacía, no difuminado con CSS: ausente. Un texto que está en el DOM está
 *     enseñado, por mucho que no se pinte — se lee con «inspeccionar elemento»
 *     o con un lector de pantalla, y ninguna prueba visual lo detecta.
 *
 * `atPoint` **sí viaja** aunque la nota esté oculta, y es deliberado: es lo que
 * permite decir «hay una nota en la página 180» sin contar qué dice.
 */
class GetClubNotesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ClubMemberRepositoryInterface   $memberRepository,
        private readonly ClubPickRepositoryInterface     $pickRepository,
        private readonly ClubProgressRepositoryInterface $progressRepository,
        private readonly ClubNoteRepositoryInterface     $noteRepository,
        private readonly SpoilerRule                     $spoilerRule,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'GetClubNotes'; }

    protected function doExecute($query): array
    {
        if (!$query instanceof GetClubNotesQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetClubNotesQuery');
        }

        if (!$this->memberRepository->isMember($query->clubId, $query->userId)) {
            throw new DomainException('You are not a member of this club');
        }

        $activo = $this->pickRepository->findActive($query->clubId);

        // Sin ítem activo no hay notas que filtrar, y no es un error: es el
        // estado normal entre un ítem y el siguiente. Misma forma que
        // `get_club_progress` para que la pantalla no maneje dos contratos.
        if ($activo === null) {
            return ['axis' => null, 'notes' => []];
        }

        $eje = $this->progressRepository->axisFor($activo->getEntityType(), $activo->getEntityId());
        [$miPunto, $heCompletado] = $this->miProgreso($query->clubId, $query->userId, $activo);

        $notas = $this->noteRepository->findPublicForPick(
            $query->clubId,
            $activo->getEntityType(),
            $activo->getEntityId()
        );

        return [
            'axis'  => $eje,
            'notes' => array_map(function (array $nota) use ($eje, $miPunto, $heCompletado, $query) {
                $esMia     = $nota['user_id'] === $query->userId;
                $esSpoiler = $this->spoilerRule->isSpoiler(
                    $nota['point'],
                    $eje,
                    $miPunto,
                    $heCompletado,
                    $esMia
                );

                return [
                    'noteId'    => $nota['note_id'],
                    'userId'    => $nota['user_id'],
                    'author'    => $nota['username'],
                    'isMine'    => $esMia,
                    'isSpoiler' => $esSpoiler,
                    // El texto de una nota marcada NO viaja. Es el punto entero
                    // de este use case.
                    'text'      => $esSpoiler ? null : $nota['text'],
                    'atPoint'   => $nota['point'],
                    'createdAt' => $nota['created_at'],
                ];
            }, $notas),
        ];
    }

    /**
     * Mi fila del progreso, que ya calcula `findProgress` para todos los
     * miembros. Se reutiliza en vez de escribir una consulta para uno solo: son
     * los mismos índices y una copia menos de las tres formas del M0.
     *
     * El pick llega por parámetro y no se vuelve a pedir: `findActive` ya se
     * llamó arriba, y repetirlo sería una consulta de más en la acción que la
     * pantalla del club refresca sola.
     *
     * **No encontrarme en la lista devuelve «no he empezado», no un error.**
     * Pasa si salgo del club entre las dos consultas, y lo prudente ahí es
     * ocultar de más, no de menos.
     *
     * @return array{0: ?int, 1: bool}
     */
    private function miProgreso(int $clubId, int $userId, ClubPick $activo): array
    {
        $miembros = $this->progressRepository->findProgress(
            $clubId,
            $activo->getEntityType(),
            $activo->getEntityId()
        );

        foreach ($miembros as $miembro) {
            if ($miembro['user_id'] === $userId) {
                return [$miembro['point'], $miembro['completed']];
            }
        }

        return [null, false];
    }
}
