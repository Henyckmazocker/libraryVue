<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Catalog;

use App\Infrastructure\Persistence\Catalog\BooleanQueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El constructor de la consulta FULLTEXT.
 *
 * Es poco código y decide si el mirror encuentra la película o no: medido, en
 * NATURAL LANGUAGE MODE «el laberinto del fauno» devuelve la correcta en 5ª
 * posición, y como `+laberinto +fauno` devuelve un único resultado, el bueno.
 */
class BooleanQueryBuilderTest extends TestCase
{
    #[Test]
    public function requires_every_token_with_a_plus(): void
    {
        $this->assertSame('+laberinto +fauno', BooleanQueryBuilder::build('laberinto fauno'));
    }

    #[Test]
    public function drops_tokens_shorter_than_the_innodb_minimum(): void
    {
        // innodb_ft_min_token_size = 3: un token de 2 caracteres no está
        // indexado, y exigirlo con '+' garantizaría cero resultados.
        // 'del' tiene exactamente 3 y SÍ está indexado, así que se queda —
        // el plan afirmaba que caía, y es falso.
        $this->assertSame('+laberinto +del +fauno', BooleanQueryBuilder::build('el laberinto del fauno'));
        $this->assertSame('+cristal', BooleanQueryBuilder::build('la de cristal'));
    }

    #[Test]
    public function counts_characters_and_not_bytes(): void
    {
        // 'años' son 4 caracteres y 5 bytes. Con strlen colaría igual, pero
        // 'año' (3 caracteres, 4 bytes) es el caso que se caería.
        $this->assertSame('+año', BooleanQueryBuilder::build('año'));
    }

    #[Test]
    public function strips_the_operators_of_the_boolean_parser(): void
    {
        // Sin esto, buscar «(500) días» o «star -wars» cambia el significado de
        // la consulta en vez de buscar esos caracteres.
        $this->assertSame('+500 +días', BooleanQueryBuilder::build('(500) días'));
        $this->assertSame('+star +wars', BooleanQueryBuilder::build('star -wars'));
        $this->assertSame('+matrix', BooleanQueryBuilder::build('"matrix*"'));
    }

    #[Test]
    public function returns_an_empty_expression_when_nothing_is_usable(): void
    {
        // El catálogo tiene que leer esto como «sin resultados» y no llegar a
        // consultar: un MATCH contra cadena vacía es un escaneo que no encuentra nada.
        $this->assertSame('', BooleanQueryBuilder::build(''));
        $this->assertSame('', BooleanQueryBuilder::build('   '));
        $this->assertSame('', BooleanQueryBuilder::build('el de la'));
        $this->assertSame('', BooleanQueryBuilder::build('+++'));
    }

    #[Test]
    public function collapses_repeated_whitespace(): void
    {
        $this->assertSame('+jungla +cristal', BooleanQueryBuilder::build("  jungla \t  de   cristal "));
    }
}
