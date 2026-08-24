<?php

declare(strict_types=1);

namespace App\Infrastructure\Covers;

use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Registra en cover_file las portadas de lo que YA estaba en la biblioteca.
 *
 * Sin esto, las portadas locales solo funcionarían para lo añadido a partir de
 * ahora: `CoverStore::register()` corre en el flujo de guardado, y
 * `fetchPending()` solo baja filas que ya existen. Un ítem guardado en 2025 no
 * tiene fila, y el endpoint le devuelve 404 —ni siquiera el 302, porque sin
 * fila no hay `source_url` al que redirigir—.
 *
 * Es idempotente: `register()` es un upsert, así que sembrar dos veces no
 * duplica nada ni resetea los intentos de una URL rota.
 *
 * Las claves de aquí tienen que ser LAS MISMAS que el frontend pide en
 * `?cover=<media_type>/<clave>`, que son las de `idOf` de cada bloque
 * `libraryItem` de `config/mediaRegistry.js`. Si una deja de casar, la portada
 * no se rompe pero deja de servirse local, que es peor de detectar.
 */
class CoverSeeder
{
    /**
     * Una consulta por medio. Solo lo que está en la biblioteca de alguien: el
     * catálogo entero son millones de imágenes que nadie va a mirar.
     *
     * @var array<string, string>
     */
    private const QUERIES = [
        // media_type 'movie' cubre también las series: en el backend son la
        // misma entidad, y en la biblioteca las pinta el mismo bloque del
        // registry (`series` solo tiene bloque `detail`).
        'movie' => "SELECT DISTINCT m.isbn AS entity_key, m.coverUrl AS source_url
                      FROM movie m
                      JOIN user_movies um ON um.movie_isbn = m.isbn
                     WHERE m.coverUrl IS NOT NULL AND m.coverUrl <> ''",

        'game'  => "SELECT DISTINCT CAST(g.id AS CHAR) AS entity_key, g.coverUrl AS source_url
                      FROM games g
                      JOIN user_games ug ON ug.game_id = g.id
                     WHERE g.coverUrl IS NOT NULL AND g.coverUrl <> ''",

        'album' => "SELECT DISTINCT CAST(a.id AS CHAR) AS entity_key, a.cover_url AS source_url
                      FROM albums a
                      JOIN user_albums ua ON ua.album_id = a.id
                     WHERE a.cover_url IS NOT NULL AND a.cover_url <> ''",

        'video' => "SELECT DISTINCT v.youtube_id AS entity_key, v.cover_url AS source_url
                      FROM videos v
                      JOIN user_videos uv ON uv.video_id = v.id
                     WHERE v.cover_url IS NOT NULL AND v.cover_url <> ''",

        // El mismo orden de preferencia que Edition::toLegacyFormat():
        // medium primero, que es el tamaño que pinta la biblioteca.
        'book'  => "SELECT DISTINCT COALESCE(e.isbn_13, e.isbn_10, e.openlibrary_edition_key) AS entity_key,
                           COALESCE(e.cover_url_medium, e.cover_url_large, e.cover_url_small) AS source_url
                      FROM book_editions e
                      JOIN user_book_editions ube ON ube.edition_id = e.edition_id
                     WHERE COALESCE(e.cover_url_medium, e.cover_url_large, e.cover_url_small) IS NOT NULL
                       AND COALESCE(e.cover_url_medium, e.cover_url_large, e.cover_url_small) <> ''",
    ];

    public function __construct(
        private readonly PDO             $library,
        private readonly CoverStore      $covers,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Siembra todos los medios.
     *
     * @return array<string, int> cuántas portadas se registraron por medio
     */
    public function seed(): array
    {
        $counts = [];

        foreach (self::QUERIES as $mediaType => $sql) {
            $counts[$mediaType] = $this->seedOne($mediaType, $sql);
        }

        return $counts;
    }

    private function seedOne(string $mediaType, string $sql): int
    {
        try {
            $rows = $this->library->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            // Una tabla que falte no puede tumbar la siembra de los otros
            // medios: el esquema de dev y el de prod no siempre van a la par.
            $this->logger->warning('CoverSeeder: no se pudo leer la biblioteca', [
                'media_type' => $mediaType,
                'error'      => $e->getMessage(),
            ]);
            return 0;
        }

        $seeded = 0;
        foreach ($rows as $row) {
            $key = (string) ($row['entity_key'] ?? '');
            $url = (string) ($row['source_url'] ?? '');
            if ($key === '' || $url === '') {
                continue;
            }

            $this->covers->register($mediaType, $key, $url);
            $seeded++;
        }

        return $seeded;
    }
}
