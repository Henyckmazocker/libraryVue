<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Logging\LoggingService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Base de los tests de integración: contenedor real, base de datos real.
 *
 * Se entra **desde `ActionRouter` hacia abajo**, no por HTTP. Es la decisión con
 * mejor relación coste/valor: cubre `config/routes.php` + la pila de middleware
 * + el `match`/`getController` de `ActionRouter` + los use cases + los
 * repositorios + el SQL contra el esquema de verdad. Es decir, **los tres
 * sitios que hay que tocar para añadir un endpoint**, que es justo el fallo
 * típico de este repo y el que ningún mock de PDO puede detectar. Lo único que
 * queda fuera es Apache, `.htaccess` y CORS, que no cambian casi nunca y cuyo
 * fallo se ve al primer clic.
 *
 * **El aislamiento es truncando, no por transacción**, y eso es una enmienda al
 * plan del 2026-08-25. La idea original era abrir una transacción en el `setUp`
 * y revertirla en el `tearDown` —más rápido y sin estado entre tests—, pero
 * **16 ficheros de `src/` abren su propia transacción** (los cinco repositorios
 * de usuario, los de tags, `StatusManagementTrait`…), y PDO no las anida:
 * `add_book` moría con `PDOException: There is already an active transaction`
 * en `MySqlUserBookEditionRepository:162`. La excepción que el plan contemplaba
 * habría sido la norma.
 *
 * El coste es el que el propio plan estimaba para truncar: con `tmpfs`,
 * milisegundos. La suite entera tarda 0,04 s.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?ContainerInterface $container = null;

    protected static ?PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        if (self::$container !== null) {
            return;
        }

        // El bootstrap de la suite: define `integrationPdo()` y
        // `seedIntegrationDatabase()`, carga el `.env` y prepara el logging.
        // Se requiere DESDE AQUÍ y no desde `phpunit.xml` para que los 1180
        // unitarios no necesiten `mysql-test` levantado.
        require_once __DIR__ . '/bootstrap.php';

        seedIntegrationDatabase();

        self::$pdo = integrationPdo();

        // El contenedor real llama a `LoggerFactory::createDatabaseLogger()` en
        // directo, y quien rellena su config es LoggingService::getInstance().
        // Es la misma trampa que obliga a `bin/mirror` y a `cover.php` a
        // llamarlo antes de construir el contenedor: sin esto revienta al
        // resolver LoggerInterface (maxFiles null).
        LoggingService::getInstance();

        // El contenedor apunta a la base de dev por sus variables de entorno.
        // Aquí se sustituye por la desechable ANTES de construir nada.
        $_ENV['DB_HOST']     = getenv('DB_TEST_HOST') ?: 'mysql-test';
        $_ENV['DB_PORT']     = getenv('DB_TEST_PORT') ?: '3306';
        $_ENV['DB_DATABASE'] = getenv('DB_TEST_DATABASE') ?: 'library_db';
        $_ENV['DB_USERNAME'] = getenv('DB_TEST_USERNAME') ?: 'root';
        $_ENV['DB_PASSWORD'] = getenv('DB_TEST_PASSWORD') ?: 'test';

        $factory = require dirname(__DIR__, 2) . '/config/container.php';
        self::$container = $factory();

        // **La misma conexión para el test y para la app.** Sin esto son dos
        // conexiones distintas, y una transacción abierta en la del test es
        // invisible para la del contenedor: los datos que siembra un test no
        // existen para el código bajo prueba. Descubierto el 2026-08-25 con dos
        // tests que fallaban por «el usuario no existe» justo después de
        // haberlo insertado.
        //
        // `set()` sobre el contenedor ya construido reemplaza la definición.
        // Va también el alias 'db', que usan los repositorios que reciben el
        // parámetro con ese nombre (`container.php:32`).
        self::$container->set(PDO::class, self::$pdo);
        self::$container->set('db', self::$pdo);
    }

    protected function setUp(): void
    {
        // El rate limiting entra en el pipeline de TODA ruta que no declare el
        // suyo (`ActionRouter.php:149-150`) y guarda estado en fichero. Ocho
        // tests seguidos golpeando la misma acción lo dispararían y el fallo
        // sería un 429 desconcertante, no un error del código bajo prueba.
        $this->clearRateLimitState();

        $this->truncateDataTables();
    }

    protected function tearDown(): void
    {
        // En CLI `$_SESSION` es un array normal, así que se limpia a mano: no
        // hay `Application::bootstrap()` por medio que lo haga. Es una ventaja,
        // porque permite probar la rama de sesión y la de JWT por separado.
        $_SESSION = [];
    }

    /**
     * Deja la base como recién sembrada: sin datos de usuario, con los catálogos.
     *
     * Se truncan **las tablas de datos**, no todas: las de referencia
     * (`*_statuses`, `item_owned_formats`, `versions`) las rellena `init.sql`
     * con INSERTs y vaciarlas dejaría la app sin estados válidos, que es un
     * fallo que no tiene nada que ver con lo que cada test viene a probar.
     *
     * **Pero `user_*_statuses` NO son de referencia, son datos**, y hasta el
     * 2026-08-29 la exclusión por sufijo se las llevaba por delante: un
     * `str_ends_with('_statuses')` caza igual a `movie_statuses` (referencia,
     * cinco filas del seed) que a `user_movie_statuses` (lo que cada test
     * escribe). Las cinco no se truncaban **nunca**: `mysql-test` acumulaba
     * 124 filas con fechas de dos días atrás.
     *
     * Y no era una fuga inocua, porque `TRUNCATE` **reinicia el
     * `AUTO_INCREMENT`**: los usuarios de cada test vuelven a ser el id 1, 2,
     * 3 y **heredan los estados del test anterior**. Lo destapó el cierre
     * automático de los clubs, que es el primer código que depende de que esas
     * tablas estén limpias: un club de dos se auto-cerraba con un `viewed` que
     * ese test nunca escribió. Ningún test anterior lo notó porque ninguno
     * leía lo que otro había dejado ahí.
     */
    private function truncateDataTables(): void
    {
        $excluidas = ['versions', 'item_owned_formats', 'schema_migrations'];

        $tablas = self::$pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
        )->fetchAll(PDO::FETCH_COLUMN);

        // Las claves ajenas hacen que el orden importe, y averiguarlo tabla a
        // tabla no aporta nada aquí: se desactivan mientras dura la limpieza.
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tablas as $tabla) {
            // Las de referencia terminan en `_statuses` (`movie_statuses`,
            // `movie_has_statuses`…); las de datos empiezan además por
            // `user_`, y esas SÍ se truncan.
            $esReferencia = str_ends_with($tabla, '_statuses') && !str_starts_with($tabla, 'user_');

            if (in_array($tabla, $excluidas, true) || $esReferencia) {
                continue;
            }

            self::$pdo->exec('TRUNCATE TABLE `' . $tabla . '`');
        }

        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** El contenedor real, ya apuntado a la base desechable. */
    protected function container(): ContainerInterface
    {
        return self::$container;
    }

    protected function pdo(): PDO
    {
        return self::$pdo;
    }

    private function clearRateLimitState(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/ratelimit';

        foreach (glob($dir . '/*.json') ?: [] as $fichero) {
            @unlink($fichero);
        }
    }
}
