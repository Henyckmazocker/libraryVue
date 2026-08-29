<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Model\ClubPick;
use App\Domain\Repository\Club\ClubMemberRepositoryInterface;
use App\Domain\Repository\Club\ClubProgressRepositoryInterface;

/**
 * ¿Ha terminado el club con su ítem activo? La regla existe AQUÍ y en ningún
 * otro sitio, igual que `ListAccess` con la visibilidad de las listas.
 *
 * La alternativa era engancharla en los cinco `Update<Medio>UserStatusesUseCase`
 * para cerrar en el instante exacto en que el último termina. Se descartó: son
 * **cinco** copias de la misma regla y cinco use cases de medio pasando a
 * depender de los repositorios del club — y es justo la familia de ficheros que
 * el `CLAUDE.md` señala como la que estuvo rota meses sin que nadie lo notara,
 * porque nadie la llamaba. Aquí se evalúa **al leer el club**, que es donde la
 * consulta ya se hace de todas formas.
 *
 * ## La regla es estricta, y eso es deliberado
 *
 * Cierra solo si **TODOS** los miembros lo han completado. «Todos» es literal, y
 * arrastra dos consecuencias que se decidieron con los ojos abiertos:
 *
 *  - **Quien no tiene el ítem en su biblioteca no completa nunca**, así que
 *    congela el cierre automático de forma indefinida.
 *  - **Quien entra a mitad cuenta igual.** No se compara `joined_at` con
 *    `started_at`: invitar a alguien reinicia de hecho la cuenta atrás.
 *
 * De ahí que `finish_club_pick` —el cierre manual del dueño— **no sea la
 * excepción sino la vía habitual**. El cierre automático es la comodidad para
 * el club que va sobrado; el botón del dueño es el mecanismo principal, y la
 * interfaz tiene que tratarlo como tal.
 */
final class ClubCompletion
{
    public function __construct(
        private readonly ClubMemberRepositoryInterface   $members,
        private readonly ClubProgressRepositoryInterface $progress
    ) {
    }

    public function everyoneFinished(ClubPick $pick): bool
    {
        $clubId  = $pick->getClubId();
        $totales = $this->members->countMembers($clubId);

        // Un club sin miembros no es un club terminado: es un club roto. Sin
        // esta guarda, `0 >= 0` cerraría el ítem en la primera lectura.
        if ($totales === 0) {
            return false;
        }

        $completados = $this->progress->countCompleted(
            $clubId,
            $pick->getEntityType(),
            $pick->getEntityId()
        );

        return $completados >= $totales;
    }
}
