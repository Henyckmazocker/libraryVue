<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Import;

use App\Infrastructure\Import\MusicBrainzImporter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cubre el empaquetado de fechas de MusicBrainz y su composición con precisión
 * variable.
 *
 * Una fecha en MusicBrainz son tres columnas (`date_year`, `date_month`,
 * `date_day`) y cualquiera de las tres puede ser nula. Dos cosas dependen de
 * tratarlas bien, y las dos se rompen en silencio:
 *
 *   - **Elegir la release más antigua de un release group.** El importador no
 *     recorre las releases en PHP —serían 5,7 M de filas—: empaqueta la fecha
 *     en un entero ordenable y deja que un `MIN()` de SQL elija. Que ese
 *     empaquetado ordene bien ES la elección, así que es lo que se prueba aquí.
 *   - **Componer 'YYYY', 'YYYY-MM' o 'YYYY-MM-DD'**, que es justo lo que
 *     `albums.release_date_precision` ya modela.
 */
class MusicBrainzImporterTest extends TestCase
{
    // =========================================================================
    // packDate — el orden es la elección
    // =========================================================================

    #[Test]
    public function una_fecha_anterior_empaqueta_a_un_entero_menor(): void
    {
        // Esto es lo que hace que el MIN() de SQL elija la release original y
        // no una reedición: si el orden se rompiera, el sello y el código de
        // barras del álbum saldrían de la edición equivocada.
        $original = MusicBrainzImporter::packDate(1973, 3, 1);
        $reedicion = MusicBrainzImporter::packDate(2011, 9, 26);

        $this->assertLessThan($reedicion, $original);
    }

    #[Test]
    public function ordena_bien_dentro_del_mismo_anyo_y_del_mismo_mes(): void
    {
        $enero = MusicBrainzImporter::packDate(1997, 1, 15);
        $junio = MusicBrainzImporter::packDate(1997, 6, 1);
        $junio_mas_tarde = MusicBrainzImporter::packDate(1997, 6, 20);

        $this->assertLessThan($junio, $enero);
        $this->assertLessThan($junio_mas_tarde, $junio);
    }

    #[Test]
    public function una_fecha_menos_precisa_del_mismo_anyo_va_primero(): void
    {
        // '1997' a secas empaqueta con mes y día a 0, así que gana a cualquier
        // fecha concreta de ese año. Es lo que se quiere: si no se sabe el mes,
        // lo más antiguo que puede ser es el 1 de enero.
        $solo_anyo = MusicBrainzImporter::packDate(1997, null, null);

        $this->assertLessThan(MusicBrainzImporter::packDate(1997, 1, 1), $solo_anyo);
    }

    #[Test]
    public function una_release_sin_anyo_se_va_al_final(): void
    {
        // 9999 y no 0: una release sin fecha NO debe ganar la elección de "la
        // más antigua", o el álbum entero se quedaría sin fecha de estreno
        // teniendo hermanas fechadas.
        $sin_fecha = MusicBrainzImporter::packDate(null, null, null);

        $this->assertGreaterThan(MusicBrainzImporter::packDate(2026, 12, 31), $sin_fecha);
        $this->assertSame(99990000, $sin_fecha);
    }

    #[Test]
    public function un_dia_sin_mes_no_desordena_el_anyo(): void
    {
        // MusicBrainz permite datos así de sucios. Lo que importa es que el
        // año siga mandando en el orden.
        $raro = MusicBrainzImporter::packDate(1980, null, 12);

        $this->assertLessThan(MusicBrainzImporter::packDate(1981, 1, 1), $raro);
        $this->assertGreaterThan(MusicBrainzImporter::packDate(1979, 12, 31), $raro);
    }

    // =========================================================================
    // formatDate — la precisión que albums ya modela
    // =========================================================================

    #[Test]
    public function compone_la_fecha_con_la_precision_que_haya(): void
    {
        $this->assertSame(
            '1997',
            MusicBrainzImporter::formatDate(MusicBrainzImporter::packDate(1997, null, null))
        );
        $this->assertSame(
            '1997-06',
            MusicBrainzImporter::formatDate(MusicBrainzImporter::packDate(1997, 6, null))
        );
        $this->assertSame(
            '1997-06-16',
            MusicBrainzImporter::formatDate(MusicBrainzImporter::packDate(1997, 6, 16))
        );
    }

    #[Test]
    public function rellena_con_ceros_a_la_izquierda(): void
    {
        // '1997-6-1' rompería cualquier comparación de cadenas y el ORDER BY
        // de la ficha.
        $this->assertSame(
            '1997-01-05',
            MusicBrainzImporter::formatDate(MusicBrainzImporter::packDate(1997, 1, 5))
        );
    }

    #[Test]
    public function sin_anyo_no_hay_fecha(): void
    {
        $this->assertNull(
            MusicBrainzImporter::formatDate(MusicBrainzImporter::packDate(null, null, null))
        );
    }

    #[Test]
    public function un_anyo_antiguo_conserva_sus_cuatro_digitos(): void
    {
        $this->assertSame(
            '0900',
            MusicBrainzImporter::formatDate(MusicBrainzImporter::packDate(900, null, null))
        );
    }

    // =========================================================================
    // datePrecision — tiene que decir lo mismo que formatDate
    // =========================================================================

    #[Test]
    public function la_precision_declarada_coincide_con_la_fecha_compuesta(): void
    {
        $casos = [
            [[1997, null, null], 'year',  '1997'],
            [[1997, 6, null],    'month', '1997-06'],
            [[1997, 6, 16],      'day',   '1997-06-16'],
        ];

        foreach ($casos as [$partes, $precision, $esperada]) {
            $packed = MusicBrainzImporter::packDate(...$partes);

            $this->assertSame($precision, MusicBrainzImporter::datePrecision($packed));
            $this->assertSame($esperada, MusicBrainzImporter::formatDate($packed));
        }
    }

    #[Test]
    public function sin_anyo_tampoco_hay_precision(): void
    {
        $this->assertNull(
            MusicBrainzImporter::datePrecision(MusicBrainzImporter::packDate(null, null, null))
        );
    }
}
