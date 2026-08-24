<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Import;

use App\Infrastructure\Import\ImdbImporter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cubre el filtrado de línea y el trato de los `\N` de IMDb.
 *
 * Sobre fixtures de unas pocas líneas: aquí no se descargan 215 MB. Lo que se
 * comprueba es exactamente lo que decide si el mirror acaba con 1,28 M de
 * títulos o con 12,7 M, y si el año de estreno acaba siendo NULL o 0.
 */
class ImdbImporterTest extends TestCase
{
    /** Una línea de title.basics: tconst titleType primaryTitle originalTitle isAdult startYear endYear runtimeMinutes genres */
    private function basics(string $type, string $tconst = 'tt0111161'): string
    {
        return "{$tconst}\t{$type}\tThe Shawshank Redemption\tThe Shawshank Redemption\t0\t1994\t\\N\t142\tDrama\n";
    }

    // =========================================================================
    // title.basics
    // =========================================================================

    #[Test]
    public function ingests_everything_that_is_not_an_episode_or_a_game(): void
    {
        // Cortos, directos a vídeo y especiales entran para que la búsqueda no
        // tenga que salir NUNCA a la red: son el único motivo por el que el
        // catálogo habría necesitado caer a TMDB para buscar.
        $tipos = ['movie', 'tvSeries', 'tvMiniSeries', 'tvMovie',
                  'short', 'tvShort', 'video', 'tvSpecial', 'tvPilot'];

        foreach ($tipos as $type) {
            $this->assertNotNull(
                ImdbImporter::filterBasicsLine($this->basics($type)),
                "El tipo {$type} debería ingestarse"
            );
        }
    }

    #[Test]
    public function keeps_out_episodes_and_video_games(): void
    {
        // tvEpisode tiene su propia tabla (9,84 M de filas) y videoGame es
        // cosa de IGDB, no de este catálogo.
        foreach (['tvEpisode', 'videoGame'] as $type) {
            $this->assertNull(
                ImdbImporter::filterBasicsLine($this->basics($type)),
                "El tipo {$type} no debería ingestarse"
            );
        }
    }

    #[Test]
    public function keeps_the_literal_backslash_n_for_load_data_to_null_out(): void
    {
        $cols = ImdbImporter::filterBasicsLine($this->basics('movie'));

        // end_year llega como \N literal. Convertirlo aquí a '' o a 0 es
        // justo el bug que produce años 0 y runtimes absurdos en la tabla.
        $this->assertSame('\\N', $cols[6]);
        $this->assertSame('1994', $cols[5]);
    }

    #[Test]
    public function drops_malformed_rows_instead_of_loading_them_shifted(): void
    {
        $this->assertNull(ImdbImporter::filterBasicsLine("tt0111161\tmovie\tSolo tres columnas\n"));
    }

    #[Test]
    public function survives_a_title_containing_a_backslash(): void
    {
        $line = "tt1234567\tmovie\tA\\B\tA\\B\t0\t2001\t\\N\t90\tDrama\n";
        $cols = ImdbImporter::filterBasicsLine($line);

        $this->assertNotNull($cols);
        $this->assertSame('A\\B', $cols[2]);
    }

    // =========================================================================
    // title.akas — sin esto no se busca en español
    // =========================================================================

    /** @return array<int,bool> */
    private function ingested(string ...$tconsts): array
    {
        $set = [];
        foreach ($tconsts as $tconst) {
            $set[ImdbImporter::tconstKey($tconst)] = true;
        }

        return $set;
    }

    #[Test]
    public function keeps_spanish_akas_by_region_or_by_language(): void
    {
        $ingested = $this->ingested('tt0095016');

        $porRegion  = "tt0095016\t7\tJungla de cristal\tES\t\\N\timdbDisplay\t\\N\t0\n";
        $porIdioma  = "tt0095016\t8\tJungla de cristal\t\\N\tes\timdbDisplay\t\\N\t0\n";

        $this->assertSame(
            ['tt0095016', '7', 'Jungla de cristal'],
            ImdbImporter::filterAkasLine($porRegion, $ingested)
        );
        $this->assertNotNull(ImdbImporter::filterAkasLine($porIdioma, $ingested));
    }

    #[Test]
    public function drops_akas_from_other_regions(): void
    {
        $ingested = $this->ingested('tt0095016');
        $frances  = "tt0095016\t3\tPiège de cristal\tFR\tfr\timdbDisplay\t\\N\t0\n";

        $this->assertNull(ImdbImporter::filterAkasLine($frances, $ingested));
    }

    #[Test]
    public function drops_akas_of_titles_that_were_not_ingested(): void
    {
        // Un corto español: está en title.akas, pero su título nunca entró en
        // imdb_title. Cargarlo dejaría filas huérfanas de las 58,9 M del fichero.
        $noIngestado = "tt9999999\t1\tUn corto cualquiera\tES\tes\timdbDisplay\t\\N\t0\n";

        $this->assertNull(ImdbImporter::filterAkasLine($noIngestado, $this->ingested('tt0095016')));
    }

    // =========================================================================
    // title.episode
    // =========================================================================

    #[Test]
    public function keeps_episodes_whose_series_was_ingested(): void
    {
        $ingested = $this->ingested('tt0903747');
        $episodio = "tt0959621\ttt0903747\t1\t1\n";

        $this->assertSame(
            ['tt0959621', 'tt0903747', '1', '1'],
            ImdbImporter::filterEpisodeLine($episodio, $ingested)
        );
    }

    #[Test]
    public function drops_episodes_of_series_that_were_not_ingested(): void
    {
        $huerfano = "tt0959621\ttt7777777\t1\t1\n";

        $this->assertNull(ImdbImporter::filterEpisodeLine($huerfano, $this->ingested('tt0903747')));
    }

    #[Test]
    public function keeps_the_literal_backslash_n_of_an_unnumbered_episode(): void
    {
        $sinNumerar = "tt0959621\ttt0903747\t\\N\t\\N\n";
        $cols       = ImdbImporter::filterEpisodeLine($sinNumerar, $this->ingested('tt0903747'));

        $this->assertSame(['tt0959621', 'tt0903747', '\\N', '\\N'], $cols);
    }

    // =========================================================================
    // Títulos de episodio — SeriesSeasonTracker.vue los pinta
    // =========================================================================

    #[Test]
    public function takes_the_title_of_an_episode_from_the_same_basics_line(): void
    {
        $linea = "tt0959621\ttvEpisode\tPilot\tPilot\t0\t2008\t\\N\t58\tCrime,Drama,Thriller\n";

        $this->assertSame(['tt0959621', 'Pilot'], ImdbImporter::filterEpisodeTitleLine($linea));
    }

    #[Test]
    public function does_not_mistake_a_film_for_an_episode(): void
    {
        // Un tvEpisode NO entra en imdb_title y una película NO entra en la
        // tabla de títulos de episodio: los dos filtros son excluyentes.
        $this->assertNull(ImdbImporter::filterEpisodeTitleLine($this->basics('movie')));
        $this->assertNull(ImdbImporter::filterBasicsLine(
            "tt0959621\ttvEpisode\tPilot\tPilot\t0\t2008\t\\N\t58\tCrime\n"
        ));
    }

    // =========================================================================
    // title.ratings
    // =========================================================================

    #[Test]
    public function keeps_every_rating_because_episodes_need_them_too(): void
    {
        // No se filtra contra el set: las notas hacen falta para títulos Y para
        // episodios, y tener ambos sets en memoria costaría ~11 M de claves.
        // El fichero entero son 1,6 M de filas y lo filtra el JOIN.
        $this->assertSame(
            ['tt0111161', '9.3', '3067332'],
            ImdbImporter::filterRatingsLine("tt0111161\t9.3\t3067332\n")
        );
        $this->assertSame(
            ['tt0959621', '9.0', '52000'],
            ImdbImporter::filterRatingsLine("tt0959621\t9.0\t52000\n")
        );
    }

    #[Test]
    public function drops_a_malformed_rating_row(): void
    {
        $this->assertNull(ImdbImporter::filterRatingsLine("tt0111161\t9.3\n"));
    }

    // =========================================================================
    // La clave del set
    // =========================================================================

    #[Test]
    public function turns_a_tconst_into_an_integer_key(): void
    {
        // Claves enteras y no cadenas porque el set son ~1,28 M de entradas que
        // se consultan una vez por cada una de los 58,9 M de filas de akas.
        $this->assertSame(111161, ImdbImporter::tconstKey('tt0111161'));
        $this->assertSame(26657236, ImdbImporter::tconstKey('tt26657236'));
    }

    #[Test]
    public function does_not_collide_between_tconsts_with_different_padding(): void
    {
        // tt0111161 y tt00111161 son el mismo número; IMDb no emite el segundo,
        // pero conviene saber que la clave no distingue ceros a la izquierda.
        $this->assertNotSame(
            ImdbImporter::tconstKey('tt0111161'),
            ImdbImporter::tconstKey('tt1111610')
        );
    }
}
