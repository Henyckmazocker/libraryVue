<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * El `Hecho cuando` del M1: la infraestructura arranca.
 *
 * No prueba nada del dominio a propósito — si esto falla, el problema está en
 * el contenedor de MySQL, en el sembrado o en el bootstrap, no en el código.
 */
class SmokeTest extends IntegrationTestCase
{
    #[Test]
    public function the_disposable_database_answers(): void
    {
        $this->assertSame(1, (int) $this->pdo()->query('SELECT 1')->fetchColumn());
    }

    #[Test]
    public function it_is_the_seeded_schema_and_not_an_empty_database(): void
    {
        // Si el sembrado no corrió, esto es 0 y el resto de la suite daría
        // errores de «tabla no existe» difíciles de leer.
        $tablas = (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
        )->fetchColumn();

        $this->assertGreaterThan(40, $tablas, 'El sembrado deja 52 tablas');
    }

    #[Test]
    public function the_migrations_ran_and_not_just_init_sql(): void
    {
        // `users.username` y las tablas sociales solo existen tras las
        // migraciones: es lo que distingue el esquema real del de init.sql.
        $columnas = $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_admin'"
        )->fetchColumn();

        $this->assertSame(1, (int) $columnas, 'users.is_admin la añade una migración, no init.sql');

        $feed = $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feed_events'"
        )->fetchColumn();

        $this->assertSame(1, (int) $feed);
    }

    #[Test]
    public function it_is_not_the_development_database(): void
    {
        // La red de seguridad más importante de toda la suite: si esto apuntara
        // al MySQL de desarrollo, cada test estaría revirtiendo transacciones
        // sobre la biblioteca real del usuario — y peor, el bootstrap habría
        // ejecutado el `DROP DATABASE` con el que abre init.sql.
        //
        // Se comprueba sobre la CONFIGURACIÓN y no sobre `@@hostname`: en
        // Docker ese valor es el id del contenedor (un hash), así que compararlo
        // con la cadena 'mysql' pasaría en verde aun estando conectado a dev.
        // Comprobado el 2026-08-25.
        $this->assertNotSame(
            getenv('DB_HOST') ?: 'mysql',
            getenv('DB_TEST_HOST') ?: 'mysql-test',
            'DB_TEST_HOST no puede ser el mismo host que DB_HOST'
        );

        // Y una corroboración con datos: la base sembrada está recién creada y
        // no tiene usuarios; la de desarrollo tiene la cuenta de David.
        $this->assertSame(
            0,
            (int) $this->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'La base de test se siembra vacía: si hay usuarios, no es la de test'
        );
    }
}
