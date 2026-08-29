<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Clubs;

use RuntimeException;

/**
 * «Ya hay un ítem activo» tiene que salir como **409**, no como el 400 genérico
 * de los demás `RuntimeException` del controller: no es una petición mal
 * formada sino un conflicto con el estado actual, y el frontend necesita
 * distinguirlo para ofrecer «termina el actual primero» en vez de un error.
 *
 * Es la razón de que exista una excepción propia en vez de mirar el mensaje:
 * comparar cadenas para decidir un código HTTP se rompe al traducir el texto.
 */
class ClubPickConflictException extends RuntimeException
{
}
