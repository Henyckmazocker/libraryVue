<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Model\ValueObjects\ISBN;

/**
 * Edition - Represents a specific published edition of a work
 * 
 * An edition is a concrete physical (or digital) manifestation of a work.
 * Properties like title, description, publisher, and languages can vary by edition.
 */
class Edition
{
    private ?int $editionId;
    private int $workId;
    private ?string $openlibraryEditionKey;
    private ?ISBN $isbn13;
    private ?ISBN $isbn10;
    private ?string $googleBooksId;
    private string $title;
    private ?string $subtitle;
    private ?string $publisher;
    private ?string $publishDate;
    private ?int $publishYear;
    private ?string $publishPlace;
    private ?string $format;
    private ?int $pages;
    private ?string $description;
    private ?array $languages; // JSON
    private ?array $illustrators; // JSON
    private ?array $translators; // JSON
    private ?array $contributors; // JSON
    private ?string $coverUrlSmall;
    private ?string $coverUrlMedium;
    private ?string $coverUrlLarge;
    private ?array $covers; // JSON - OpenLibrary cover IDs
    private ?array $lcClassifications; // JSON
    private ?string $deweyDecimalClass;
    private ?array $series; // JSON
    private ?string $seriesPosition;
    private ?string $previewLink;
    private ?string $infoLink;
    private string $dataSource;

    public function __construct(
        int $workId,
        ?string $openlibraryEditionKey,
        string $title,
        ?int $editionId = null
    ) {
        if (empty($title)) {
            throw new \InvalidArgumentException('Title cannot be empty.');
        }

        $this->workId = $workId;
        $this->openlibraryEditionKey = $openlibraryEditionKey;
        $this->title = $title;
        $this->editionId = $editionId;
        $this->dataSource = 'openlibrary';
        
        // Initialize all nullable properties
        $this->isbn13 = null;
        $this->isbn10 = null;
        $this->googleBooksId = null;
        $this->subtitle = null;
        $this->publisher = null;
        $this->publishDate = null;
        $this->publishYear = null;
        $this->publishPlace = null;
        $this->format = null;
        $this->pages = null;
        $this->description = null;
        $this->languages = null;
        $this->illustrators = null;
        $this->translators = null;
        $this->contributors = null;
        $this->coverUrlSmall = null;
        $this->coverUrlMedium = null;
        $this->coverUrlLarge = null;
        $this->covers = null;
        $this->lcClassifications = null;
        $this->deweyDecimalClass = null;
        $this->series = null;
        $this->seriesPosition = null;
        $this->previewLink = null;
        $this->infoLink = null;
    }

    // Getters and Setters
    public function getEditionId(): ?int 
    { 
        return $this->editionId; 
    }

    public function setEditionId(int $editionId): void 
    { 
        $this->editionId = $editionId; 
    }

    public function getWorkId(): int 
    { 
        return $this->workId; 
    }

    public function getOpenlibraryEditionKey(): ?string 
    { 
        return $this->openlibraryEditionKey; 
    }

    public function getIsbn13(): ?ISBN 
    { 
        return $this->isbn13; 
    }

    public function setIsbn13(?ISBN $isbn13): void 
    { 
        $this->isbn13 = $isbn13; 
    }

    public function getIsbn10(): ?ISBN 
    { 
        return $this->isbn10; 
    }

    public function setIsbn10(?ISBN $isbn10): void 
    { 
        $this->isbn10 = $isbn10; 
    }

    public function getGoogleBooksId(): ?string
    {
        return $this->googleBooksId;
    }

    public function setGoogleBooksId(?string $googleBooksId): void
    {
        $this->googleBooksId = $googleBooksId;
    }

    public function getTitle(): string 
    { 
        return $this->title; 
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    public function getPublisher(): ?string 
    { 
        return $this->publisher; 
    }

    public function setPublisher(?string $publisher): void 
    { 
        $this->publisher = $publisher; 
    }

    public function getPublishDate(): ?string
    {
        return $this->publishDate;
    }

    public function getPublishYear(): ?int
    {
        return $this->publishYear;
    }

    public function getPublishPlace(): ?string
    {
        return $this->publishPlace;
    }

    public function getPages(): ?int 
    { 
        return $this->pages; 
    }

    public function setPages(?int $pages): void 
    { 
        $this->pages = $pages; 
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): void
    {
        $this->format = $format;
    }

    public function getDescription(): ?string 
    { 
        return $this->description; 
    }

    public function setDescription(?string $description): void 
    { 
        $this->description = $description; 
    }

    public function getLanguages(): ?array
    {
        return $this->languages;
    }

    public function setLanguages(?array $languages): void
    {
        $this->languages = $languages;
    }

    public function getIllustrators(): ?array
    {
        return $this->illustrators;
    }

    public function setIllustrators(?array $illustrators): void
    {
        $this->illustrators = $illustrators;
    }

    public function getTranslators(): ?array
    {
        return $this->translators;
    }

    public function setTranslators(?array $translators): void
    {
        $this->translators = $translators;
    }

    public function getCoverUrlSmall(): ?string
    {
        return $this->coverUrlSmall;
    }

    public function getCoverUrlMedium(): ?string 
    { 
        return $this->coverUrlMedium; 
    }

    public function getCoverUrlLarge(): ?string
    {
        return $this->coverUrlLarge;
    }
    
    public function setCoverUrls(?string $small, ?string $medium, ?string $large): void
    {
        $this->coverUrlSmall = $small;
        $this->coverUrlMedium = $medium;
        $this->coverUrlLarge = $large;
    }

    public function getCovers(): ?array
    {
        return $this->covers;
    }

    public function setCovers(?array $covers): void
    {
        $this->covers = $covers;
    }

    public function setPublishInfo(?string $date, ?int $year, ?string $place): void
    {
        $this->publishDate = $date;
        $this->publishYear = $year;
        $this->publishPlace = $place;
    }

    public function setSeries(?array $series, ?string $position): void
    {
        $this->series = $series;
        $this->seriesPosition = $position;
    }

    public function setLinks(?string $previewLink, ?string $infoLink): void
    {
        $this->previewLink = $previewLink;
        $this->infoLink = $infoLink;
    }

    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    /**
     * Convert edition to legacy Book format for frontend compatibility
     * CRITICAL: Maintains compatibility with existing frontend
     */
    public function toLegacyFormat(Work $work): array
    {
        return [
            'isbn' => $this->isbn13?->toString() ?? $this->isbn10?->toString() ?? $this->openlibraryEditionKey,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'author' => !empty($work->getAuthors()) ? implode(', ', $work->getAuthors()) : null,
            'authors' => $work->getAuthors(),
            'publisher' => $this->publisher,
            'publication_date' => $this->publishDate,
            'publication_year' => $this->publishYear,
            'coverUrl' => $this->coverUrlMedium ?? $this->coverUrlLarge ?? $this->coverUrlSmall,
            'cover_urls' => [
                'small' => $this->coverUrlSmall,
                'medium' => $this->coverUrlMedium,
                'large' => $this->coverUrlLarge,
            ],
            'pages' => $this->pages,
            'description' => $this->description ?? $work->getDescription(),
            'genres' => $work->getSubjects(), // Compatibility: subjects → genres
            'format' => $this->format,
            'languages' => $this->languages,
            // Additional metadata for frontend (new fields)
            'edition_id' => $this->editionId,
            'work_id' => $this->workId,
            'openlibrary_edition_key' => $this->openlibraryEditionKey,
            'openlibrary_work_key' => $work->getOpenlibraryWorkKey(),
        ];
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'edition_id' => $this->editionId,
            'work_id' => $this->workId,
            'openlibrary_edition_key' => $this->openlibraryEditionKey,
            'isbn_13' => $this->isbn13?->toString(),
            'isbn_10' => $this->isbn10?->toString(),
            'google_books_id' => $this->googleBooksId,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'publisher' => $this->publisher,
            'publish_date' => $this->publishDate,
            'publish_year' => $this->publishYear,
            'publish_place' => $this->publishPlace,
            'format' => $this->format,
            'pages' => $this->pages,
            'description' => $this->description,
            'languages' => $this->languages,
            'illustrators' => $this->illustrators,
            'translators' => $this->translators,
            'contributors' => $this->contributors,
            'cover_urls' => [
                'small' => $this->coverUrlSmall,
                'medium' => $this->coverUrlMedium,
                'large' => $this->coverUrlLarge,
            ],
            'covers' => $this->covers,
            'lc_classifications' => $this->lcClassifications,
            'dewey_decimal_class' => $this->deweyDecimalClass,
            'series' => $this->series,
            'series_position' => $this->seriesPosition,
            'preview_link' => $this->previewLink,
            'info_link' => $this->infoLink,
            'data_source' => $this->dataSource,
        ];
    }

    /**
     * Create Edition from array data
     */
    public static function fromArray(array $data): self
    {
        $edition = new self(
            $data['work_id'],
            $data['openlibrary_edition_key'],
            $data['title'],
            $data['edition_id'] ?? null
        );

        if (isset($data['isbn_13']) && $data['isbn_13']) {
            $edition->setIsbn13(ISBN::fromString($data['isbn_13']));
        }
        if (isset($data['isbn_10']) && $data['isbn_10']) {
            $edition->setIsbn10(ISBN::fromString($data['isbn_10']));
        }
        if (isset($data['google_books_id'])) {
            $edition->setGoogleBooksId($data['google_books_id']);
        }
        if (isset($data['subtitle'])) {
            $edition->setSubtitle($data['subtitle']);
        }
        if (isset($data['publisher'])) {
            $edition->setPublisher($data['publisher']);
        }
        if (isset($data['publish_date']) || isset($data['publish_year']) || isset($data['publish_place'])) {
            $edition->setPublishInfo(
                $data['publish_date'] ?? null,
                $data['publish_year'] ?? null,
                $data['publish_place'] ?? null
            );
        }
        if (isset($data['format'])) {
            $edition->setFormat($data['format']);
        }
        if (isset($data['pages'])) {
            $edition->setPages($data['pages']);
        } elseif (isset($data['number_of_pages'])) {
            $edition->setPages((int)$data['number_of_pages']);
        }
        if (isset($data['description'])) {
            $edition->setDescription($data['description']);
        }
        if (isset($data['languages'])) {
            $edition->setLanguages($data['languages']);
        }
        if (isset($data['illustrators'])) {
            $edition->setIllustrators($data['illustrators']);
        }
        if (isset($data['translators'])) {
            $edition->setTranslators($data['translators']);
        }
        if (isset($data['cover_url_small']) || isset($data['cover_url_medium']) || isset($data['cover_url_large'])) {
            $edition->setCoverUrls(
                $data['cover_url_small'] ?? null,
                $data['cover_url_medium'] ?? null,
                $data['cover_url_large'] ?? null
            );
        }
        if (isset($data['covers'])) {
            $edition->setCovers($data['covers']);
        }
        if (isset($data['series']) || isset($data['series_position'])) {
            $edition->setSeries(
                $data['series'] ?? null,
                $data['series_position'] ?? null
            );
        }
        if (isset($data['preview_link']) || isset($data['info_link'])) {
            $edition->setLinks(
                $data['preview_link'] ?? null,
                $data['info_link'] ?? null
            );
        }

        return $edition;
    }
}
