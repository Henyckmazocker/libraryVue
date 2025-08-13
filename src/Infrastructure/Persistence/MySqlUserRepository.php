<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Domain\Model\User;
use App\Application\Domain\Repository\UserRepositoryInterface;
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
}
