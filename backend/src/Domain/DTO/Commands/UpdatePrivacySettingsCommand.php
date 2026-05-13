<?php

declare(strict_types=1);

namespace App\Domain\DTO\Commands;

final readonly class UpdatePrivacySettingsCommand
{
    public function __construct(
        public int  $userId,
        public bool $showAdditions,
        public bool $showStatusChanges,
        public bool $showRatings,
        public bool $showNotes,
        public bool $showReadingSessions,
        public bool $showAchievements
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId:             $userId,
            showAdditions:      (bool) ($data['showAdditions'] ?? $data['show_additions'] ?? true),
            showStatusChanges:  (bool) ($data['showStatusChanges'] ?? $data['show_status_changes'] ?? true),
            showRatings:        (bool) ($data['showRatings'] ?? $data['show_ratings'] ?? true),
            showNotes:          (bool) ($data['showNotes'] ?? $data['show_notes'] ?? false),
            showReadingSessions:(bool) ($data['showReadingSessions'] ?? $data['show_reading_sessions'] ?? true),
            showAchievements:   (bool) ($data['showAchievements'] ?? $data['show_achievements'] ?? true)
        );
    }
}
