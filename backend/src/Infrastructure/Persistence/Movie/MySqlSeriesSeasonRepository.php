<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Movie;

use App\Domain\Repository\Movie\SeriesSeasonRepositoryInterface;
use App\Infrastructure\Persistence\Concerns\LoggableTrait;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MySqlSeriesSeasonRepository implements SeriesSeasonRepositoryInterface
{
    use LoggableTrait;

    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger
    ) {}

    public function trackSeason(
        int $userId,
        string $seriesIsbn,
        int $seasonNumber,
        string $status,
        ?string $dateViewed,
        ?float $personalRating,
        ?string $notes
    ): void {
        try {
            $sql = '
                INSERT INTO user_series_seasons
                    (user_id, series_isbn, season_number, status, date_viewed, personal_rating, notes)
                VALUES
                    (:userId, :seriesIsbn, :seasonNumber, :status, :dateViewed, :personalRating, :notes)
                ON DUPLICATE KEY UPDATE
                    status           = VALUES(status),
                    date_viewed      = VALUES(date_viewed),
                    personal_rating  = VALUES(personal_rating),
                    notes            = VALUES(notes),
                    updated_at       = CURRENT_TIMESTAMP
            ';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId'         => $userId,
                ':seriesIsbn'     => $seriesIsbn,
                ':seasonNumber'   => $seasonNumber,
                ':status'         => $status,
                ':dateViewed'     => $dateViewed,
                ':personalRating' => $personalRating,
                ':notes'          => $notes,
            ]);
        } catch (PDOException $e) {
            $this->logError('DB Error tracking series season', $e, [
                'userId'       => $userId,
                'seriesIsbn'   => $seriesIsbn,
                'seasonNumber' => $seasonNumber,
            ]);
            throw new RuntimeException('Could not track series season. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getProgress(int $userId, string $seriesIsbn): array
    {
        try {
            $sql = '
                SELECT season_number, status, date_viewed, personal_rating, notes
                FROM user_series_seasons
                WHERE user_id = :userId AND series_isbn = :seriesIsbn
                ORDER BY season_number ASC
            ';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':userId' => $userId, ':seriesIsbn' => $seriesIsbn]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $result[(int) $row['season_number']] = [
                    'status'          => $row['status'],
                    'date_viewed'     => $row['date_viewed'],
                    'personal_rating' => $row['personal_rating'] !== null
                        ? (float) $row['personal_rating'] : null,
                    'notes'           => $row['notes'],
                ];
            }

            return $result;
        } catch (PDOException $e) {
            $this->logError('DB Error getting series progress', $e, [
                'userId'     => $userId,
                'seriesIsbn' => $seriesIsbn,
            ]);
            throw new RuntimeException('Could not get series progress. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deleteSeason(int $userId, string $seriesIsbn, int $seasonNumber): void
    {
        try {
            $sql = '
                DELETE FROM user_series_seasons
                WHERE user_id = :userId
                  AND series_isbn = :seriesIsbn
                  AND season_number = :seasonNumber
            ';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':userId'       => $userId,
                ':seriesIsbn'   => $seriesIsbn,
                ':seasonNumber' => $seasonNumber,
            ]);
        } catch (PDOException $e) {
            $this->logError('DB Error deleting series season', $e, [
                'userId'       => $userId,
                'seriesIsbn'   => $seriesIsbn,
                'seasonNumber' => $seasonNumber,
            ]);
            throw new RuntimeException('Could not delete series season. DB Error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
