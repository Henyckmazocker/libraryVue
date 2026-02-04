<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book\Mappers;

use App\Domain\Model\Edition;
use App\Domain\Model\ValueObjects\ISBN;

/**
 * Maps Edition entity between Domain and Persistence layers
 */
final class EditionDataMapper
{
    /**
     * Convert database row to Edition domain entity
     */
    public function toDomain(array $dbRow): Edition
    {
        $edition = new Edition(
            (int) $dbRow['work_id'],
            $dbRow['openlibrary_edition_key'],
            $dbRow['title'],
            isset($dbRow['edition_id']) ? (int) $dbRow['edition_id'] : null
        );

        if (isset($dbRow['isbn_13']) && $dbRow['isbn_13']) {
            $edition->setIsbn13(ISBN::fromString($dbRow['isbn_13']));
        }

        if (isset($dbRow['isbn_10']) && $dbRow['isbn_10']) {
            $edition->setIsbn10(ISBN::fromString($dbRow['isbn_10']));
        }

        if (isset($dbRow['google_books_id'])) {
            $edition->setGoogleBooksId($dbRow['google_books_id']);
        }

        if (isset($dbRow['subtitle'])) {
            $edition->setSubtitle($dbRow['subtitle']);
        }

        if (isset($dbRow['publisher'])) {
            $edition->setPublisher($dbRow['publisher']);
        }

        $edition->setPublishInfo(
            $dbRow['publish_date'] ?? null,
            isset($dbRow['publish_year']) ? (int) $dbRow['publish_year'] : null,
            $dbRow['publish_place'] ?? null
        );

        if (isset($dbRow['format'])) {
            $edition->setFormat($dbRow['format']);
        }

        if (isset($dbRow['pages'])) {
            $edition->setPages((int) $dbRow['pages']);
        }

        if (isset($dbRow['description'])) {
            $edition->setDescription($dbRow['description']);
        }

        if (isset($dbRow['languages'])) {
            $languages = is_string($dbRow['languages'])
                ? json_decode($dbRow['languages'], true)
                : $dbRow['languages'];
            $edition->setLanguages($languages);
        }

        if (isset($dbRow['illustrators'])) {
            $illustrators = is_string($dbRow['illustrators'])
                ? json_decode($dbRow['illustrators'], true)
                : $dbRow['illustrators'];
            $edition->setIllustrators($illustrators);
        }

        if (isset($dbRow['translators'])) {
            $translators = is_string($dbRow['translators'])
                ? json_decode($dbRow['translators'], true)
                : $dbRow['translators'];
            $edition->setTranslators($translators);
        }

        $edition->setCoverUrls(
            $dbRow['cover_url_small'] ?? null,
            $dbRow['cover_url_medium'] ?? null,
            $dbRow['cover_url_large'] ?? null
        );

        if (isset($dbRow['covers'])) {
            $covers = is_string($dbRow['covers'])
                ? json_decode($dbRow['covers'], true)
                : $dbRow['covers'];
            $edition->setCovers($covers);
        }

        if (isset($dbRow['series'])) {
            $series = is_string($dbRow['series'])
                ? json_decode($dbRow['series'], true)
                : $dbRow['series'];
            $edition->setSeries($series, $dbRow['series_position'] ?? null);
        }

        $edition->setLinks(
            $dbRow['preview_link'] ?? null,
            $dbRow['info_link'] ?? null
        );

        return $edition;
    }

    /**
     * Convert Edition domain entity to database row
     */
    public function toDatabase(Edition $edition): array
    {
        return [
            'edition_id' => $edition->getEditionId(),
            'work_id' => $edition->getWorkId(),
            'openlibrary_edition_key' => $edition->getOpenlibraryEditionKey(),
            'isbn_13' => $edition->getIsbn13()?->toString(),
            'isbn_10' => $edition->getIsbn10()?->toString(),
            'google_books_id' => $edition->getGoogleBooksId(),
            'title' => $edition->getTitle(),
            'subtitle' => $edition->getSubtitle(),
            'publisher' => $edition->getPublisher(),
            'publish_date' => $edition->getPublishDate(),
            'publish_year' => $edition->getPublishYear(),
            'publish_place' => $edition->getPublishPlace(),
            'format' => $edition->getFormat(),
            'pages' => $edition->getPages(),
            'description' => $edition->getDescription(),
            'languages' => $edition->getLanguages() ? json_encode($edition->getLanguages()) : null,
            'illustrators' => $edition->getIllustrators() ? json_encode($edition->getIllustrators()) : null,
            'translators' => $edition->getTranslators() ? json_encode($edition->getTranslators()) : null,
            'cover_url_small' => $edition->getCoverUrlSmall(),
            'cover_url_medium' => $edition->getCoverUrlMedium(),
            'cover_url_large' => $edition->getCoverUrlLarge(),
            'covers' => $edition->getCovers() ? json_encode($edition->getCovers()) : null,
            'data_source' => $edition->getDataSource(),
        ];
    }
}
