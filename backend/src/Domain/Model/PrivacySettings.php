<?php

declare(strict_types=1);

namespace App\Domain\Model;

class PrivacySettings
{
    // Maps event_type constants to property names
    private const EVENT_TYPE_MAP = [
        FeedEvent::TYPE_ITEM_ADDED      => 'showAdditions',
        FeedEvent::TYPE_STATUS_CHANGED  => 'showStatusChanges',
        FeedEvent::TYPE_ITEM_RATED      => 'showRatings',
        FeedEvent::TYPE_NOTES_UPDATED   => 'showNotes',
        FeedEvent::TYPE_READING_SESSION => 'showReadingSessions',
        FeedEvent::TYPE_ACHIEVEMENT     => 'showAchievements',
    ];

    public function __construct(
        private int  $userId,
        private bool $showAdditions      = true,
        private bool $showStatusChanges  = true,
        private bool $showRatings        = true,
        private bool $showNotes          = false,
        private bool $showReadingSessions = true,
        private bool $showAchievements   = true
    ) {}

    public function getUserId(): int           { return $this->userId; }
    public function showAdditions(): bool      { return $this->showAdditions; }
    public function showStatusChanges(): bool  { return $this->showStatusChanges; }
    public function showRatings(): bool        { return $this->showRatings; }
    public function showNotes(): bool          { return $this->showNotes; }
    public function showReadingSessions(): bool { return $this->showReadingSessions; }
    public function showAchievements(): bool   { return $this->showAchievements; }

    public function isEventVisible(string $eventType): bool
    {
        $property = self::EVENT_TYPE_MAP[$eventType] ?? null;
        if ($property === null) {
            return false;
        }
        return (bool) $this->{$property};
    }

    public function getVisibleEventTypes(): array
    {
        return array_keys(array_filter(
            self::EVENT_TYPE_MAP,
            fn(string $prop) => (bool) $this->{$prop}
        ));
    }

    public function toArray(): array
    {
        return [
            'user_id'              => $this->userId,
            'show_additions'       => $this->showAdditions,
            'show_status_changes'  => $this->showStatusChanges,
            'show_ratings'         => $this->showRatings,
            'show_notes'           => $this->showNotes,
            'show_reading_sessions' => $this->showReadingSessions,
            'show_achievements'    => $this->showAchievements,
        ];
    }
}
