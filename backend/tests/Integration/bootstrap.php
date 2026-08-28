<?php

declare(strict_types=1);

/**
 * Arranque de la suite de integración.
 *
 * Espera a que el MySQL desechable acepte conexiones y, si la base está vacía,
 * la siembra con `docker/database/init.sql` **más todas las migraciones**. Las
 * dos cosas, y en ese orden: `init.sql` por sí solo **no es el esquema actual**
 * —sigue creando `user_follows`, que la migración de mayo elimina, y no crea
 * las tablas sociales ni `users.username`—, así que sembrar solo con él daría
 * una suite que valida un esquema que no usa nadie y que pasa en verde mientras
 * la app real falla.
 *
 * ## Las tres trampas que costaron el spike del M0 (2026-08-25)
 *
 *  1. **La conexión tiene que ser utf8mb4.** Canalizando `init.sql` sin fijar
 *     el charset del cliente, MySQL lee sus bytes UTF-8 como latin1 y **toda la
 *     base queda con mojibake** (`completÃ³` en vez de `completó`). Se detectó
 *     porque cinco tablas parecían divergir del esquema de dev y las cinco eran
 *     esto. Si pasa con los comentarios, pasa con los datos.
 *  2. **`init.sql` no es parametrizable y empieza con `DROP DATABASE`.** Sus
 *     tres primeras líneas son `DROP DATABASE IF EXISTS library_db;
 *     CREATE DATABASE library_db; USE library_db;`, así que ignora cualquier
 *     `MYSQL_DATABASE` del contenedor. De ahí la guarda de abajo: apuntar esto
 *     al MySQL de desarrollo **destruiría la biblioteca del usuario**.
 *  3. **`mysql:8` ya no vale.** Hoy resuelve a 8.4, que retiró
 *     `default-authentication-plugin` y ni siquiera arranca con la
 *     configuración de este proyecto. El servicio usa `mysql:8.0`, la misma
 *     imagen que dev: un esquema validado en otra versión no prueba lo que
 *     corre de verdad.
 */

$raizBackend = dirname(__DIR__, 2);

require_once $raizBackend . '/vendor/autoload.php';

// Mismo bucle del .env que `bin/mirror` y `cover.php`: en CLI no pasa nadie por
// `bootstrap.php`, y `config/container.php` lee de `$_ENV`, no de `getenv()`.
$envFile = $raizBackend . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        if (strpos(trim($linea), '#') === 0 || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($clave, $_ENV) || $_ENV[$clave] === '') {
            $_ENV[$clave] = $valor;
        }
    }
}

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'development';

// La trampa que documentan `bin/mirror` y `cover.php`: `container.php` llama a
// `LoggerFactory::createDatabaseLogger()` en directo y quien rellena su config
// es `LoggingService::getInstance()`, que a su vez necesita el helper `config()`.
// Sin estos dos require, el contenedor revienta al resolver LoggerInterface.
if (file_exists($raizBackend . '/config/helpers.php')) {
    require_once $raizBackend . '/config/helpers.php';
}
if (file_exists($raizBackend . '/src/Infrastructure/Logging/functions.php')) {
    require_once $raizBackend . '/src/Infrastructure/Logging/functions.php';
}

/** Hosts contra los que se permite sembrar. Cualquier otro aborta. */
const SEEDABLE_HOSTS = ['mysql-test', '127.0.0.1', 'localhost'];

/** Cuánto se espera a que el contenedor arranque. Medido: 8 s con tmpfs. */
const WAIT_SECONDS = 60;

/**
 * Conexión a la base de test, esperando a que el servidor responda.
 *
 * Se conecta primero sin base: si el contenedor es nuevo, `library_db` todavía
 * no existe y seleccionarla fallaría.
 */
function integrationPdo(bool $withDatabase = true): PDO
{
    $host = getenv('DB_TEST_HOST') ?: 'mysql-test';
    $port = getenv('DB_TEST_PORT') ?: '3306';
    $db   = getenv('DB_TEST_DATABASE') ?: 'library_db';
    $user = getenv('DB_TEST_USERNAME') ?: 'root';
    $pass = getenv('DB_TEST_PASSWORD') ?: 'test';

    $dsn = $withDatabase
        ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db)
        : sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

    $deadline = time() + WAIT_SECONDS;
    $ultimo   = null;

    do {
        try {
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Sin esto, `exec()` de un fichero .sql entero falla en la
                // segunda sentencia. Es lo que permite sembrar sin partir el
                // fichero por `;`, que es frágil.
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ]);
        } catch (PDOException $e) {
            $ultimo = $e;

            // Un fallo de resolución de nombre no se arregla esperando: el
            // servicio no existe, no es que esté arrancando. Insistir 60 s ahí
            // solo hace que `composer test` parezca colgado.
            if (str_contains($e->getMessage(), 'getaddrinfo')) {
                break;
            }

            usleep(500_000);
        }
    } while (time() < $deadline);

    fwrite(STDERR, sprintf(
        "\n[integración] No se pudo conectar a %s:%s.\n"
        . "¿Está levantado el servicio? →  docker compose --profile test up -d mysql-test\n"
        . "Último error: %s\n\n",
        $host,
        $port,
        $ultimo?->getMessage() ?? '(ninguno)'
    ));

    exit(1);
}

/**
 * Siembra la base si está vacía. No hace nada si ya tiene tablas.
 *
 * Idempotente a propósito: la suite se lanza muchas veces contra el mismo
 * contenedor y resembrar cada vez costaría segundos por nada.
 */
function seedIntegrationDatabase(): void
{
    $host = getenv('DB_TEST_HOST') ?: 'mysql-test';

    // LA guarda. `init.sql` abre con DROP DATABASE IF EXISTS library_db, así
    // que ejecutarlo contra el MySQL de desarrollo se llevaría por delante la
    // biblioteca entera del usuario. No es paranoia: el nombre de la base es
    // EL MISMO en los dos sitios, y una variable de entorno mal puesta basta.
    if (!in_array($host, SEEDABLE_HOSTS, true)) {
        fwrite(STDERR, sprintf(
            "\n[integración] ABORTADO: DB_TEST_HOST es '%s', que no está en la lista de hosts\n"
            . "sembrables (%s). init.sql empieza con DROP DATABASE y esto habría\n"
            . "destruido esa base de datos.\n\n",
            $host,
            implode(', ', SEEDABLE_HOSTS)
        ));

        exit(1);
    }

    $pdo = integrationPdo(false);

    $db = getenv('DB_TEST_DATABASE') ?: 'library_db';

    // `docker/database/` vive FUERA de ./backend, que es lo único que el
    // contenedor monta como /var/www/html. Llega por un bind aparte, en /opt
    // para que Apache no pueda servir el esquema en texto plano.
    $esquema = getenv('DB_SCHEMA_PATH') ?: '/opt/db-schema';

    if (!is_file($esquema . '/init.sql')) {
        fwrite(STDERR, sprintf(
            "\n[integración] No encuentro init.sql en %s.\n"
            . "El contenedor `backend` monta ./docker/database ahí; revisa docker-compose.yml.\n\n",
            $esquema
        ));

        exit(1);
    }

    $existentes = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($db)
    )->fetchColumn();

    // Hasta el 2026-08-27 esto era `if ($existentes > 0) return;`, y esa línea
    // significaba que **una migración nueva no llegaba nunca a la base de
    // test**: quien añadía una la veía aplicada en dev y sus tests fallaban con
    // «Table … doesn't exist» teniendo el SQL bien. Ahora se aplican las que
    // falten, con el mismo registro que usa `run_migrations.sh` en dev y prod.
    $registradas = [];

    if ($existentes > 0) {
        $pdo->exec('USE ' . $db);

        $tieneRegistro = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES"
            . " WHERE TABLE_SCHEMA = " . $pdo->quote($db) . " AND TABLE_NAME = 'schema_migrations'"
        )->fetchColumn();

        if ($tieneRegistro === 0) {
            // Base sembrada por la versión vieja de esta función: sus
            // migraciones están aplicadas pero no registradas, y no todas son
            // idempotentes, así que re-aplicarlas a ciegas rompería. Es una
            // base desechable y sembrarla cuesta segundos: se recrea entera.
            fwrite(STDOUT, "[integración] Base de test sin registro de migraciones: se recrea desde cero.\n");
            $pdo->exec('DROP DATABASE IF EXISTS ' . $db);
            $existentes = 0;
        } else {
            $registradas = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    if ($existentes === 0) {
        $pdo->exec(file_get_contents($esquema . '/init.sql'));
    }

    $pdo->exec('USE ' . $db);

    // El mismo DDL que `run_migrations.sh:127-134`, para que el registro
    // signifique lo mismo en test que en dev y en producción.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations ('
        . ' id INT AUTO_INCREMENT PRIMARY KEY,'
        . ' filename VARCHAR(255) NOT NULL UNIQUE,'
        . ' applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
        . ' checksum VARCHAR(64) NOT NULL,'
        . ' INDEX idx_filename (filename)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // Las migraciones, en orden de fecha. El nombre del fichero ES el orden:
    // `20260513_120000_…` antes que `20260818_163500_…`.
    $migraciones = glob($esquema . '/migrations/*.sql') ?: [];
    sort($migraciones);

    $registrar = $pdo->prepare(
        'INSERT INTO schema_migrations (filename, checksum) VALUES (:filename, :checksum)'
    );
    $aplicadas = 0;

    foreach ($migraciones as $migracion) {
        $nombre = basename($migracion);

        if (in_array($nombre, $registradas, true)) {
            continue;
        }

        $pdo->exec(file_get_contents($migracion));
        // sha256 del fichero, como `run_migrations.sh:219`.
        $registrar->execute([':filename' => $nombre, ':checksum' => hash_file('sha256', $migracion)]);
        $aplicadas++;
    }

    if ($existentes === 0) {
        fwrite(STDOUT, sprintf("[integración] Base sembrada: init.sql + %d migraciones.\n", $aplicadas));
    } elseif ($aplicadas > 0) {
        fwrite(STDOUT, sprintf("[integración] %d migración(es) nueva(s) aplicada(s) a la base de test.\n", $aplicadas));
    }
}

// NO se siembra al cargar el fichero, y es deliberado: desde que la suite
// `Integration` está en `phpunit.xml`, su `bootstrap` es el global
// (`vendor/autoload.php`) y este fichero lo requiere `IntegrationTestCase`.
// Sembrar aquí obligaría a tener `mysql-test` levantado para correr los 1180
// unitarios, que es justo lo que `composer test:unit` promete no necesitar.
// Lo dispara `IntegrationTestCase::setUpBeforeClass()`.
