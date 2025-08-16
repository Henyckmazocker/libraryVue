<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Database\DatabaseConnector;
use PDO;
use PDOException;
use RuntimeException;

class MySqlUserRepository implements UserRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseConnector::getConnection();
    }

    public function findByGoogleId(string $googleId): ?User
    {
        try {
            $sql = "SELECT * FROM users WHERE google_id = :google_id AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':google_id', $googleId);
            $stmt->execute();
            
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                return null;
            }

            return $this->hydrateUser($userData);
        } catch (PDOException $e) {
            error_log("Error finding user by Google ID: " . $e->getMessage());
            throw new RuntimeException("Failed to find user by Google ID");
        }
    }

    public function findById(int $id): ?User
    {
        try {
            $sql = "SELECT * FROM users WHERE id = :id AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                return null;
            }

            return $this->hydrateUser($userData);
        } catch (PDOException $e) {
            error_log("Error finding user by ID: " . $e->getMessage());
            throw new RuntimeException("Failed to find user by ID");
        }
    }

    public function findByEmail(string $email): ?User
    {
        try {
            $sql = "SELECT * FROM users WHERE email = :email AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                return null;
            }

            return $this->hydrateUser($userData);
        } catch (PDOException $e) {
            error_log("Error finding user by email: " . $e->getMessage());
            throw new RuntimeException("Failed to find user by email");
        }
    }

    public function save(User $user): User
    {
        try {
            $sql = "INSERT INTO users (google_id, email, name, picture, created_at, updated_at, last_login, preferences, is_active) 
                    VALUES (:google_id, :email, :name, :picture, FROM_UNIXTIME(:created_at), FROM_UNIXTIME(:updated_at), 
                           FROM_UNIXTIME(:last_login), :preferences, :is_active)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':google_id' => $user->getGoogleId(),
                ':email' => $user->getEmail(),
                ':name' => $user->getName(),
                ':picture' => $user->getPicture(),
                ':created_at' => $user->getCreatedAt(),
                ':updated_at' => $user->getUpdatedAt(),
                ':last_login' => $user->getLastLogin(),
                ':preferences' => $user->getPreferences() ? json_encode($user->getPreferences()) : null,
                ':is_active' => $user->isActive()
            ]);

            $userId = (int)$this->db->lastInsertId();
            
            // Return user with new ID
            return $this->findById($userId);
        } catch (PDOException $e) {
            error_log("Error saving user: " . $e->getMessage());
            throw new RuntimeException("Failed to save user");
        }
    }

    public function update(User $user): User
    {
        try {
            $sql = "UPDATE users SET 
                        email = :email, 
                        name = :name, 
                        picture = :picture, 
                        updated_at = FROM_UNIXTIME(:updated_at), 
                        last_login = FROM_UNIXTIME(:last_login), 
                        preferences = :preferences, 
                        is_active = :is_active 
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $user->getId(),
                ':email' => $user->getEmail(),
                ':name' => $user->getName(),
                ':picture' => $user->getPicture(),
                ':updated_at' => time(),
                ':last_login' => $user->getLastLogin(),
                ':preferences' => $user->getPreferences() ? json_encode($user->getPreferences()) : null,
                ':is_active' => $user->isActive()
            ]);

            return $user;
        } catch (PDOException $e) {
            error_log("Error updating user: " . $e->getMessage());
            throw new RuntimeException("Failed to update user");
        }
    }

    private function hydrateUser(array $userData): User
    {
        // Convert timestamps to unix timestamps
        $createdAt = $userData['created_at'] ? strtotime($userData['created_at']) : null;
        $updatedAt = $userData['updated_at'] ? strtotime($userData['updated_at']) : null;
        $lastLogin = $userData['last_login'] ? strtotime($userData['last_login']) : null;
        
        // Decode JSON preferences
        $preferences = null;
        if ($userData['preferences']) {
            $preferences = json_decode($userData['preferences'], true);
        }

        return new User(
            (int)$userData['id'],
            $userData['google_id'],
            $userData['email'],
            $userData['name'],
            $userData['picture'],
            $createdAt,
            $updatedAt,
            $lastLogin,
            $preferences,
            (bool)$userData['is_active']
        );
    }

    // User library methods implementation
    public function getUserBooks(int $userId, array $filters = []): array
    {
        try {
            $sql = "
                SELECT b.*, ub.added_at as user_added_at,
                       GROUP_CONCAT(bs.name SEPARATOR ', ') as user_statuses
                FROM books b
                INNER JOIN user_books ub ON b.isbn = ub.book_isbn
                LEFT JOIN user_book_statuses ubs ON b.isbn = ubs.book_isbn AND ubs.user_id = :userId
                LEFT JOIN book_statuses bs ON ubs.status_id = bs.id
                WHERE ub.user_id = :userId
            ";

            $params = [':userId' => $userId];

            // Apply filters
            if (isset($filters['status']) && !empty($filters['status'])) {
                $sql .= " AND bs.name = :status";
                $params[':status'] = $filters['status'];
            }

            if (isset($filters['title']) && !empty($filters['title'])) {
                $sql .= " AND b.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }

            $sql .= " GROUP BY b.isbn ORDER BY ub.added_at DESC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("DB Error getting user books (MySqlUserRepository): " . $e->getMessage());
            throw new RuntimeException("Could not get user books. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserMovies(int $userId, array $filters = []): array
    {
        try {
            $sql = "
                SELECT m.*, um.added_at as user_added_at,
                       GROUP_CONCAT(ms.name SEPARATOR ', ') as user_statuses
                FROM movie m
                INNER JOIN user_movies um ON m.isbn = um.movie_isbn
                LEFT JOIN user_movie_statuses ums ON m.isbn = ums.movie_isbn AND ums.user_id = :userId
                LEFT JOIN movie_statuses ms ON ums.status_id = ms.id
                WHERE um.user_id = :userId
            ";

            $params = [':userId' => $userId];

            // Apply filters
            if (isset($filters['status']) && !empty($filters['status'])) {
                $sql .= " AND ms.name = :status";
                $params[':status'] = $filters['status'];
            }

            if (isset($filters['title']) && !empty($filters['title'])) {
                $sql .= " AND m.title LIKE :title";
                $params[':title'] = '%' . $filters['title'] . '%';
            }

            $sql .= " GROUP BY m.isbn ORDER BY um.added_at DESC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("DB Error getting user movies (MySqlUserRepository): " . $e->getMessage());
            throw new RuntimeException("Could not get user movies. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function getUserLibraryStats(int $userId): array
    {
        try {
            $stats = [];

            // Count books by status
            $sqlBooks = "
                SELECT bs.name as status, COUNT(*) as count
                FROM user_books ub
                LEFT JOIN user_book_statuses ubs ON ub.book_isbn = ubs.book_isbn AND ub.user_id = ubs.user_id
                LEFT JOIN book_statuses bs ON ubs.status_id = bs.id
                WHERE ub.user_id = :userId
                GROUP BY bs.name
            ";
            
            $stmt = $this->db->prepare($sqlBooks);
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $stats['books'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count movies by status
            $sqlMovies = "
                SELECT ms.name as status, COUNT(*) as count
                FROM user_movies um
                LEFT JOIN user_movie_statuses ums ON um.movie_isbn = ums.movie_isbn AND um.user_id = ums.user_id
                LEFT JOIN movie_statuses ms ON ums.status_id = ms.id
                WHERE um.user_id = :userId
                GROUP BY ms.name
            ";
            
            $stmt = $this->db->prepare($sqlMovies);
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $stats['movies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total counts
            $sqlTotalBooks = "SELECT COUNT(*) as total FROM user_books WHERE user_id = :userId";
            $stmt = $this->db->prepare($sqlTotalBooks);
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $stats['total_books'] = $stmt->fetchColumn();

            $sqlTotalMovies = "SELECT COUNT(*) as total FROM user_movies WHERE user_id = :userId";
            $stmt = $this->db->prepare($sqlTotalMovies);
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $stats['total_movies'] = $stmt->fetchColumn();

            return $stats;

        } catch (PDOException $e) {
            error_log("DB Error getting user library stats (MySqlUserRepository): " . $e->getMessage());
            throw new RuntimeException("Could not get user library stats. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function hasUserBook(int $userId, string $isbn): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM user_books WHERE user_id = :userId AND book_isbn = :isbn";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->execute();
            
            return $stmt->fetchColumn() > 0;

        } catch (PDOException $e) {
            error_log("DB Error checking user book (MySqlUserRepository): " . $e->getMessage());
            throw new RuntimeException("Could not check user book. DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    public function hasUserMovie(int $userId, string $movieId): bool
    {
        try {
            error_log("HasUserMovie: userId=$userId, movieId=$movieId");
            
            $sql = "SELECT COUNT(*) FROM user_movies WHERE user_id = :userId AND movie_isbn = :movieId";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':movieId', $movieId);
            $stmt->execute();
            
            $count = $stmt->fetchColumn();
            $hasMovie = $count > 0;
            error_log("HasUserMovie: count=$count, hasMovie=" . ($hasMovie ? 'true' : 'false'));
            
            return $hasMovie;

        } catch (PDOException $e) {
            error_log("DB Error checking user movie (MySqlUserRepository): " . $e->getMessage());
            throw new RuntimeException("Could not check user movie. DB Error: " . $e->getMessage(), 0, $e);
        }
    }
}
