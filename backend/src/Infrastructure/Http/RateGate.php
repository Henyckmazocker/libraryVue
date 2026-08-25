<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Puerta de cadencia que cruza procesos.
 *
 * Existe por MusicBrainz, que exige **1 petición por segundo** y degrada o
 * bloquea a quien se pasa. Un contador en memoria no sirve aquí: el backend es
 * PHP sobre Apache y cada petición es un proceso distinto, así que dos
 * peticiones simultáneas tendrían cada una su propio «hace un segundo que no
 * llamo». El estado tiene que vivir en disco, igual que el rate limiting
 * entrante lo guarda en `storage/ratelimit`.
 *
 * Tres cosas que no son evidentes y que son la razón de que esto sea una clase
 * y no tres líneas sueltas:
 *
 *  1. **Se coge el lock, se escribe y se SUELTA antes de dormir.** Dormir con
 *     el lock cogido serializa a los procesos en cadena: el segundo esperaría
 *     su turno del lock *y encima* su intervalo, el tercero los dos anteriores,
 *     y la espera crecería sin techo. Reservando el hueco primero, cada proceso
 *     duerme solo lo suyo y en paralelo.
 *  2. **Se reserva el turno futuro, no el instante actual.** Lo que se escribe
 *     en el fichero es *cuándo va a salir* esta llamada, no cuándo se pidió
 *     permiso. Así dos procesos que entren a la vez se reparten dos huecos
 *     consecutivos en vez de darse el mismo.
 *  3. **La espera tiene techo.** Un reloj que salte hacia atrás, o un fichero
 *     corrupto, no pueden dejar una petición dormida indefinidamente.
 */
final class RateGate
{
    /** Nadie espera más que esto por la puerta, pase lo que pase con el fichero. */
    private const MAX_WAIT_SECONDS = 5.0;

    private readonly string $file;

    public function __construct(
        string $name,
        private readonly float $minInterval,
        string $dir = '/var/www/html/storage/ratelimit'
    ) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);

        $this->file = $dir . '/gate_' . $safe . '.txt';
    }

    /**
     * Bloquea lo justo para no pasarse de `minInterval` desde la llamada anterior.
     *
     * @return float segundos que se ha dormido; 0.0 si no hizo falta esperar
     */
    public function wait(): float
    {
        $espera = $this->reserve();

        if ($espera > 0.0) {
            usleep((int) round($espera * 1_000_000));
        }

        return $espera;
    }

    /**
     * Reserva el siguiente hueco y devuelve cuánto hay que dormir para ocuparlo.
     *
     * Separado de `wait()` para que el test pueda comprobar el reparto de
     * turnos sin gastar segundos de reloj durmiendo.
     */
    public function reserve(): float
    {
        $handle = @fopen($this->file, 'c+');

        // Sin fichero no hay puerta, pero tampoco hay motivo para tumbar la
        // llamada: se deja pasar y que el proveedor decida.
        if ($handle === false) {
            return 0.0;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return 0.0;
            }

            $ahora = microtime(true);
            $crudo = stream_get_contents($handle);
            $ultima = is_string($crudo) && $crudo !== '' ? (float) $crudo : 0.0;

            // El turno es el siguiente hueco libre; si ya pasó, es ahora mismo.
            $turno = max($ahora, $ultima + $this->minInterval);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $turno);
            fflush($handle);

            // Suelta ANTES de dormir. Ver la nota 1 de la cabecera.
            flock($handle, LOCK_UN);

            return min($turno - $ahora, self::MAX_WAIT_SECONDS);
        } finally {
            fclose($handle);
        }
    }
}
