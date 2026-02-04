<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Book\Mappers;

use App\Domain\Model\Work;

/**
 * Maps Work entity between Domain and Persistence layers
 */
final class WorkDataMapper
{
    /**
     * Convert database row to Work domain entity
     */
    public function toDomain(array $dbRow): Work
    {
        $authors = isset($dbRow['authors']) && is_string($dbRow['authors'])
            ? json_decode($dbRow['authors'], true) ?? []
            : ($dbRow['authors'] ?? []);

        $work = new Work(
            $dbRow['title'],
            $authors,
            isset($dbRow['work_id']) ? (int) $dbRow['work_id'] : null,
            $dbRow['openlibrary_work_key'] ?? null,
            $dbRow['synthetic_work_key'] ?? null
        );

        if (isset($dbRow['subtitle'])) {
            $work->setSubtitle($dbRow['subtitle']);
        }

        if (isset($dbRow['description'])) {
            $work->setDescription($dbRow['description']);
        }

        if (isset($dbRow['subjects'])) {
            $subjects = is_string($dbRow['subjects'])
                ? json_decode($dbRow['subjects'], true) ?? []
                : $dbRow['subjects'];
            $work->setSubjects($subjects);
        }

        if (isset($dbRow['first_publish_year'])) {
            $work->setFirstPublishYear((int) $dbRow['first_publish_year']);
        }

        if (isset($dbRow['original_language'])) {
            $work->setOriginalLanguage($dbRow['original_language']);
        }

        if (isset($dbRow['needs_review'])) {
            $work->setNeedsReview((bool) $dbRow['needs_review']);
        }

        if (isset($dbRow['manually_edited']) && $dbRow['manually_edited'] && isset($dbRow['manually_edited_fields'])) {
            $fields = is_string($dbRow['manually_edited_fields'])
                ? json_decode($dbRow['manually_edited_fields'], true) ?? []
                : $dbRow['manually_edited_fields'];
            $work->markAsManuallyEdited($fields);
        }

        return $work;
    }

    /**
     * Convert Work domain entity to database row
     */
    public function toDatabase(Work $work): array
    {
        return [
            'work_id' => $work->getWorkId(),
            'openlibrary_work_key' => $work->getOpenlibraryWorkKey(),
            'synthetic_work_key' => $work->getSyntheticWorkKey(),
            'title' => $work->getTitle(),
            'subtitle' => $work->getSubtitle(),
            'authors' => json_encode($work->getAuthors()),
            'description' => $work->getDescription(),
            'subjects' => $work->getSubjects() ? json_encode($work->getSubjects()) : null,
            'first_publish_year' => $work->getFirstPublishYear(),
            'original_language' => $work->getOriginalLanguage(),
            'is_synthetic' => $work->isSynthetic() ? 1 : 0,
            'needs_review' => $work->needsReview() ? 1 : 0,
            'manually_edited' => $work->isManuallyEdited() ? 1 : 0,
            'manually_edited_fields' => $work->isManuallyEdited() ? json_encode($work->toArray()['manually_edited_fields']) : null,
        ];
    }
}
