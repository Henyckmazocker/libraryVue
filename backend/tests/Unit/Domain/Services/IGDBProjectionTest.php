<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La barrera de las proyecciones de IGDB.
 *
 * Es un test de cadena, y si, es feo. Existe porque **IGDB ignora en silencio los
 * campos que no conoce**: comprobado el 2026-09-01 pidiendole un campo literalmente
 * inventado, la respuesta fue `success` con el resto de datos correctos. No hay
 * error, no hay aviso y no hay log; el campo simplemente deja de venir y lo que lo
 * consume degrada a su rama por defecto.
 *
 * Eso ya costo dos defectos que vivieron meses sin que nada los detectara:
 *   - `websites.category`    → quince enlaces externos llamados todos igual.
 *   - `age_ratings.category` → el badge de clasificacion por edad, que no se pintaba.
 *
 * Ningun test de comportamiento puede cazarlo, porque el servicio se comporta bien:
 * pide lo que le dicen y devuelve lo que le dan. Lo unico que queda es fijar el texto
 * de la consulta.
 *
 * Se miran **solo las proyecciones**, no el fichero entero: los comentarios que
 * explican el arreglo nombran los campos retirados, y un `assertStringNotContains`
 * sobre la fuente completa se dispararia con ellos.
 */
class IGDBProjectionTest extends TestCase
{
    /** @var list<string> Las lineas `fields …;` de cada consulta a IGDB. */
    private array $proyecciones;

    protected function setUp(): void
    {
        $fuente = file_get_contents(
            __DIR__ . '/../../../../src/Domain/Services/IGDBService.php'
        );

        preg_match_all('/fields [^;]+;/', $fuente, $coincidencias);
        $this->proyecciones = $coincidencias[0];

        $this->assertNotEmpty(
            $this->proyecciones,
            'No se encontro ni una proyeccion: el patron de la consulta ha cambiado.'
        );
    }

    private function todas(): string
    {
        return implode("\n", $this->proyecciones);
    }

    /** @return array<string, array{string}> */
    public static function camposRetirados(): array
    {
        return [
            'websites.category'    => ['websites.category'],
            'age_ratings.category' => ['age_ratings.category'],
            'age_ratings.rating'   => ['age_ratings.rating,'],
        ];
    }

    #[DataProvider('camposRetirados')]
    public function testNingunaProyeccionPideUnCampoRetirado(string $campo): void
    {
        $this->assertStringNotContainsString(
            $campo,
            $this->todas(),
            "La proyeccion pide `{$campo}`, que IGDB ya no sirve. No dara error: "
            . 'el campo llegara vacio y lo que lo consuma degradara en silencio.'
        );
    }

    /** @return array<string, array{string}> */
    public static function camposVigentes(): array
    {
        return [
            'el tipo de enlace externo'     => ['websites.type.type'],
            'el organismo que clasifica'    => ['age_ratings.organization.name'],
            'el valor de la clasificacion'  => ['age_ratings.rating_category.rating'],
        ];
    }

    #[DataProvider('camposVigentes')]
    public function testLasProyeccionesPidenLosCamposVigentes(string $campo): void
    {
        $this->assertStringContainsString($campo, $this->todas());
    }

    /**
     * Las dos consultas de juego piden `websites`, y las dos tienen que pedirlo bien:
     * `getGameDetails()` alimenta la ficha y `getGameById()` la busqueda. Arreglar
     * solo una deja el defecto vivo por el otro camino, que es justo lo que estuvo a
     * punto de pasar.
     */
    public function testLasDosConsultasDeJuegoPidenElTipoDeEnlace(): void
    {
        $conWebsites = array_filter(
            $this->proyecciones,
            static fn (string $p): bool => str_contains($p, 'websites.')
        );

        $this->assertCount(2, $conWebsites, 'Deberian ser getGameById y getGameDetails.');

        foreach ($conWebsites as $proyeccion) {
            $this->assertStringContainsString('websites.type.type', $proyeccion);
        }
    }
}
