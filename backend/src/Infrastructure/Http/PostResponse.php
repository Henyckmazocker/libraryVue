<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Throwable;

/**
 * Trabajo que corre DESPUÉS de haber respondido al cliente.
 *
 * El proyecto no tiene colas ni workers: el backend es PHP sobre Apache,
 * petición-respuesta. Esto es lo más parecido a una cola que se puede tener sin
 * meter infraestructura nueva, y existe para una cosa muy concreta: que bajar
 * una portada no convierta un guardado de 20 ms en uno de varios segundos.
 *
 * Cómo funciona aquí, medido y no supuesto (spike M0 del Plan - Portadas
 * Locales, 2026-08-23):
 *
 *  - `fastcgi_finish_request()` NO existe bajo `mod_php` / `apache2handler`,
 *    que es lo que corre este proyecto (`Dockerfile.backend.dev`). Se comprueba
 *    igualmente porque si algún día se pasa a PHP-FPM es la vía buena.
 *  - Sin él hay que cerrar la conexión a mano, y para eso hace falta un buffer
 *    de salida: `Application::sendResponse()` hace un `echo` pelado sin
 *    `Content-Length` y el contenedor no activa `output_buffering`, así que sin
 *    el `ob_start()` de aquí no hay nada que medir ni que cerrar y el cliente se
 *    come la espera entera.
 *
 * Por eso `defer()` tiene que llamarse ANTES de que se emita la respuesta.
 * Medido: respuesta en 59 ms con 3 s de trabajo detrás.
 */
final class PostResponse
{
    /** @var list<callable():void> */
    private static array $work = [];

    private static bool $armed = false;

    /**
     * Encola trabajo para después de la respuesta.
     *
     * Llamar varias veces en la misma petición acumula: el buffer se abre una
     * sola vez y todo corre en la misma función de apagado.
     */
    public static function defer(callable $work): void
    {
        self::$work[] = $work;

        if (self::$armed) {
            return;
        }
        self::$armed = true;

        // En CLI y en los tests no hay conexión que cerrar ni respuesta que
        // esperar: el trabajo se ejecuta en el apagado y punto.
        if (PHP_SAPI !== 'cli') {
            ob_start();
        }

        register_shutdown_function([self::class, 'run']);
    }

    /**
     * Cierra la conexión y ejecuta lo encolado. Solo la llama PHP al apagarse.
     *
     * @internal
     */
    public static function run(): void
    {
        // Que el cliente cuelgue no puede abortar el trabajo: es justo el caso
        // en el que corre, con la conexión ya cerrada.
        ignore_user_abort(true);

        if (PHP_SAPI !== 'cli') {
            self::closeConnection();
        }

        foreach (self::$work as $work) {
            try {
                $work();
            } catch (Throwable $e) {
                // Nadie escucha ya: el cliente tiene su respuesta desde hace
                // rato. Al log y a por el siguiente.
                error_log('PostResponse: ' . $e->getMessage());
            }
        }

        self::$work  = [];
        self::$armed = false;
    }

    private static function closeConnection(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        if (!headers_sent()) {
            header('Connection: close');
            header('Content-Length: ' . (string) ob_get_length());
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        flush();
    }

    /** Solo para los tests: deja la clase como recién cargada. */
    public static function reset(): void
    {
        self::$work  = [];
        self::$armed = false;
    }
}
