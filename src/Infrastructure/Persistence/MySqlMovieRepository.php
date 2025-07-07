<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use PDOException;
use RuntimeException;
use App\Application\Domain\Repository\MovieRepositoryInterface;

class MySqlMovieRepository implements MovieRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \App\Infrastructure\Database\DatabaseConnector::getConnection();
    }

    private function getStatusId(string $statusName): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM movie_statuses WHERE name = :name");
        $stmt->bindParam(':name', $statusName);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }

    private function fetchMovieStatusNames(string $isbn): array
    {
        $sql = "SELECT s.name FROM movie_statuses s " .
               "JOIN movie_has_statuses mhs ON s.id = mhs.status_id " .
               "WHERE mhs.movie_isbn = :isbn";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':isbn', $isbn);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function fetchAllowedStatuses(): array
    {
        $stmt = $this->db->query("SELECT name FROM movie_statuses");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function findAll(array $filters = []): array
    {
        $sql = "SELECT DISTINCT m.* FROM movie m";
        $params = [];

        if (!empty($filters['userStatus'])) {
            $statusName = $filters['userStatus'];
            $statusId = $this->getStatusId($statusName);
            if ($statusId === null) {
                error_log("findAll: Status name '{$statusName}' not found in movie_statuses table.");
                return [];
            }
            $sql .= " JOIN movie_has_statuses mhs ON m.isbn = mhs.movie_isbn";
            $sql .= " WHERE mhs.status_id = :statusId";
            $params[':statusId'] = $statusId;
        }
        $sql .= " ORDER BY m.addedTimestamp DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $moviesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $movies = [];

        foreach ($moviesData as $data) {
            $data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
            $data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null;
            $userStatuses = $this->fetchMovieStatusNames($data['isbn']);
            $data['userStatuses'] = is_array($userStatuses) ? $userStatuses : [];
            $data['allowedStatuses'] = $this->fetchAllowedStatuses();
            $movies[] = $data; // Aquí deberías mapear a un Movie::fromArray si tienes un modelo Movie
        }
        return $movies;
    }

    public function save(array $movie): void
    {
        $this->db->beginTransaction();
        try {
            $sqlMovie = "INSERT INTO movie (isbn, title, author, coverUrl, rating, addedTimestamp) " .
                   "VALUES (:isbn, :title, :author, :coverUrl, :rating, :addedTimestamp) " .
                   "ON DUPLICATE KEY UPDATE " .
                   "title = VALUES(title), author = VALUES(author), coverUrl = VALUES(coverUrl), " .
                   "rating = VALUES(rating), addedTimestamp = VALUES(addedTimestamp)";
            $stmtMovie = $this->db->prepare($sqlMovie);
            $stmtMovie->execute([
                ':isbn' => $movie['isbn'],
                ':title' => $movie['title'],
                ':author' => $movie['author'] ?? null,
                ':coverUrl' => $movie['coverUrl'] ?? null,
                ':rating' => $movie['rating'] ?? null,
                ':addedTimestamp' => $movie['addedTimestamp'] ?? time()
            ]);
            $isbn = $movie['isbn'];
            $userStatusNames = $movie['userStatuses'] ?? [];
            $stmtDeleteStatuses = $this->db->prepare("DELETE FROM movie_has_statuses WHERE movie_isbn = :isbn");
            $stmtDeleteStatuses->bindParam(':isbn', $isbn);
            $stmtDeleteStatuses->execute();
            if (empty($userStatusNames)) {
                throw new RuntimeException("Movie must have at least one user status to save. ISBN: " . $isbn);
            }
            $sqlInsertStatus = "INSERT INTO movie_has_statuses (movie_isbn, status_id) VALUES (:isbn, :status_id)";
            $stmtInsertStatus = $this->db->prepare($sqlInsertStatus);
            foreach ($userStatusNames as $statusName) {
                $statusId = $this->getStatusId($statusName);
                if ($statusId === null) {
                    throw new RuntimeException("Invalid status name '{$statusName}' encountered for movie ISBN {$isbn}. Not found in 'movie_statuses' table.");
                }
                $stmtInsertStatus->execute([':isbn' => $isbn, ':status_id' => $statusId]);
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Save Error (MySqlMovieRepository): " . $e->getMessage() . " Movie data: " . json_encode($movie));
            throw new RuntimeException("Could not save movie and/or its statuses. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Generic Error during save (MySqlMovieRepository): " . $e->getMessage() . " Movie data: " . json_encode($movie));
            throw new RuntimeException("An unexpected error occurred while saving movie and statuses: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteByIsbn(string $isbn): bool
    {
        $this->db->beginTransaction();
        try {
            $stmtDeleteLinks = $this->db->prepare("DELETE FROM movie_has_statuses WHERE movie_isbn = :isbn");
            $stmtDeleteLinks->bindParam(':isbn', $isbn);
            $stmtDeleteLinks->execute();
            $stmtDeleteMovie = $this->db->prepare("DELETE FROM movie WHERE isbn = :isbn");
            $stmtDeleteMovie->bindParam(':isbn', $isbn);
            $stmtDeleteMovie->execute();
            $deleted = $stmtDeleteMovie->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Delete Error (MySqlMovieRepository): " . $e->getMessage() . " ISBN: " . $isbn);
            throw new RuntimeException("Could not delete movie. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Generic Error during delete (MySqlMovieRepository): " . $e->getMessage() . " ISBN: " . $isbn);
            throw new RuntimeException("An unexpected error occurred while deleting movie: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteById(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $stmtDeleteLinks = $this->db->prepare("DELETE FROM movie_has_statuses WHERE movie_isbn = (SELECT isbn FROM movie WHERE id = :id)");
            $stmtDeleteLinks->bindParam(':id', $id);
            $stmtDeleteLinks->execute();
            $stmtDeleteMovie = $this->db->prepare("DELETE FROM movie WHERE id = :id");
            $stmtDeleteMovie->bindParam(':id', $id);
            $stmtDeleteMovie->execute();
            $deleted = $stmtDeleteMovie->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Delete Error (MySqlMovieRepository): " . $e->getMessage() . " ID: " . $id);
            throw new RuntimeException("Could not delete movie. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Generic Error during delete (MySqlMovieRepository): " . $e->getMessage() . " ID: " . $id);
            throw new RuntimeException("An unexpected error occurred while deleting movie: " . $e->getMessage(), 0, $e);
        }
    }

    public function deleteByName(string $title): bool
    {
        $this->db->beginTransaction();
        try {
            $stmtDeleteLinks = $this->db->prepare("DELETE FROM movie_has_statuses WHERE movie_isbn IN (SELECT isbn FROM movie WHERE title = :title)");
            $stmtDeleteLinks->bindParam(':title', $title);
            $stmtDeleteLinks->execute();
            $stmtDeleteMovie = $this->db->prepare("DELETE FROM movie WHERE title = :title");
            $stmtDeleteMovie->bindParam(':title', $title);
            $stmtDeleteMovie->execute();
            $deleted = $stmtDeleteMovie->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Delete Error (MySqlMovieRepository): " . $e->getMessage() . " Title: " . $title);
            throw new RuntimeException("Could not delete movie. DB Error: " . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Generic Error during delete (MySqlMovieRepository): " . $e->getMessage() . " Title: " . $title);
            throw new RuntimeException("An unexpected error occurred while deleting movie: " . $e->getMessage(), 0, $e);
        }
    }

    public function findByIsbn(string $isbn): ?array
    {
        $sql = "SELECT * FROM movie WHERE isbn = :isbn";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':isbn', $isbn);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        $data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
        $data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null;
        $userStatuses = $this->fetchMovieStatusNames($isbn);
        $data['userStatuses'] = is_array($userStatuses) ? $userStatuses : [];
        $data['allowedStatuses'] = $this->fetchAllowedStatuses();
        return $data; // O mapear a Movie::fromArray si tienes un modelo Movie
    }
}
