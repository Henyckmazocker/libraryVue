<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use RuntimeException;

/**
 * La ronda no está en la fase que esa acción necesita: proponer con el voto ya
 * abierto, votar mientras se propone, o abrir un voto que ya está abierto.
 *
 * Sale como **409** por el mismo motivo que `ClubPickConflictException`: no es
 * una petición mal formada sino un conflicto con el estado, y el frontend lo
 * necesita distinguido para recargar la pantalla en vez de pintar un error. Es
 * una clase y no una comparación de mensajes porque comparar cadenas para
 * decidir un código HTTP se rompe al traducir el texto.
 */
class ClubRoundConflictException extends RuntimeException
{
}
