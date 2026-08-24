<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Catalog;

/**
 * Turns what the user typed into a MySQL FULLTEXT BOOLEAN MODE expression
 *
 * BOOLEAN and not NATURAL LANGUAGE, and it is the difference between finding
 * the film and not finding it. Measured on the real mirror: "el laberinto del
 * fauno" in NATURAL LANGUAGE MODE returns the right film in **5th place**,
 * behind three "Il Fauno di marmo" — relevance rewards short titles, and
 * `el`/`del` are dropped anyway by innodb_ft_min_token_size. The same words as
 * `+laberinto +fauno` return a single, correct result.
 */
final class BooleanQueryBuilder
{
    /**
     * InnoDB will not index a token shorter than this, so requiring one with
     * '+' guarantees zero rows. Checked on the dev server: it is 3.
     */
    private const MIN_TOKEN_LENGTH = 3;

    /** Characters that mean something to the BOOLEAN parser and must not reach it */
    private const OPERATORS = ['+', '-', '>', '<', '(', ')', '~', '*', '"', '@'];

    /**
     * Build the AGAINST expression
     *
     * @return string Empty when nothing usable was typed. The caller must treat
     *         that as "no results" and not run the query: a MATCH against an
     *         empty string is a full scan that finds nothing.
     */
    public static function build(string $query): string
    {
        $tokens = [];

        foreach (preg_split('/\s+/u', trim($query)) ?: [] as $word) {
            $word = str_replace(self::OPERATORS, '', $word);

            // mb_strlen, not strlen: "años" is 4 characters and 5 bytes, and
            // dropping it because of its ñ would be a bug nobody would find.
            if (mb_strlen($word) < self::MIN_TOKEN_LENGTH) {
                continue;
            }

            $tokens[] = '+' . $word;
        }

        return implode(' ', $tokens);
    }
}
