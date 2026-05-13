<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Social;

use App\Domain\Model\PrivacySettings;
use App\Domain\Repository\Social\PrivacySettingsRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlPrivacySettingsRepository implements PrivacySettingsRepositoryInterface
{
    use LoggableTrait;

    private const TABLE = 'user_privacy_settings';

    public function __construct(
        private readonly PDO             $db,
        private readonly LoggerInterface $logger
    ) {}

    public function findByUserId(int $userId): PrivacySettings
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . self::TABLE . " WHERE user_id = :uid LIMIT 1");
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return new PrivacySettings($userId); // defaults
            }

            return new PrivacySettings(
                userId:             (int)  $row['user_id'],
                showAdditions:      (bool) $row['show_additions'],
                showStatusChanges:  (bool) $row['show_status_changes'],
                showRatings:        (bool) $row['show_ratings'],
                showNotes:          (bool) $row['show_notes'],
                showReadingSessions:(bool) $row['show_reading_sessions'],
                showAchievements:   (bool) $row['show_achievements']
            );
        } catch (PDOException $e) {
            $this->logError('findByUserId failed', $e, ['user_id' => $userId]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function save(PrivacySettings $settings): PrivacySettings
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO " . self::TABLE
                . " (user_id, show_additions, show_status_changes, show_ratings, show_notes, show_reading_sessions, show_achievements)"
                . " VALUES (:user_id, :show_additions, :show_status_changes, :show_ratings, :show_notes, :show_reading_sessions, :show_achievements)"
                . " ON DUPLICATE KEY UPDATE"
                . " show_additions = VALUES(show_additions),"
                . " show_status_changes = VALUES(show_status_changes),"
                . " show_ratings = VALUES(show_ratings),"
                . " show_notes = VALUES(show_notes),"
                . " show_reading_sessions = VALUES(show_reading_sessions),"
                . " show_achievements = VALUES(show_achievements)"
            );
            $stmt->execute([
                ':user_id'              => $settings->getUserId(),
                ':show_additions'       => (int) $settings->showAdditions(),
                ':show_status_changes'  => (int) $settings->showStatusChanges(),
                ':show_ratings'         => (int) $settings->showRatings(),
                ':show_notes'           => (int) $settings->showNotes(),
                ':show_reading_sessions'=> (int) $settings->showReadingSessions(),
                ':show_achievements'    => (int) $settings->showAchievements(),
            ]);
            return $settings;
        } catch (PDOException $e) {
            $this->logError('save privacy settings failed', $e, ['user_id' => $settings->getUserId()]);
            throw new RuntimeException('DB error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
