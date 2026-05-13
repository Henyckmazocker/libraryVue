<?php

declare(strict_types=1);

namespace App\Domain\Repository\Social;

use App\Domain\Model\PrivacySettings;

interface PrivacySettingsRepositoryInterface
{
    /**
     * Find privacy settings for a user.
     * Returns default settings if none exist yet.
     */
    public function findByUserId(int $userId): PrivacySettings;

    /**
     * Persist (insert or update) privacy settings
     */
    public function save(PrivacySettings $settings): PrivacySettings;
}
