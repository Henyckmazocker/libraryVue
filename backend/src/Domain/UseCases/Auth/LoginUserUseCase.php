<?php
declare(strict_types=1);

namespace App\Domain\UseCases\Auth;

use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Model\User;
use InvalidArgumentException;

class LoginUserUseCase
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Login or register user with Google OAuth data
     */
    public function execute(array $googleTokenData): User
    {
        // Validate required Google token data
        if (!isset($googleTokenData['sub'], $googleTokenData['email'], $googleTokenData['name'])) {
            if (function_exists('logger')) {
                try {
                    logger('auth')->warning('Invalid Google token data provided', [
                        'provided_fields' => array_keys($googleTokenData),
                        'required_fields' => ['sub', 'email', 'name']
                    ]);
                } catch (\Throwable $e) {
                    error_log("Logging error in LoginUserUseCase: " . $e->getMessage());
                }
            }
            throw new InvalidArgumentException('Invalid Google token data. Missing required fields.');
        }

        $googleId = $googleTokenData['sub'];
        $email = $googleTokenData['email'];
        $name = $googleTokenData['name'];
        $picture = $googleTokenData['picture'] ?? null;

        if (function_exists('logger')) {
            try {
                logger('auth')->info('Attempting user authentication', [
                    'google_id' => $googleId,
                    'email' => $email,
                    'name' => $name
                ]);
            } catch (\Throwable $e) {
                error_log("Logging error in LoginUserUseCase: " . $e->getMessage());
            }
        }

        // Try to find existing user
        $existingUser = $this->userRepository->findByGoogleId($googleId);
        
        if ($existingUser) {
            if (function_exists('logger')) {
                try {
                    logger('auth')->info('Existing user found, updating login data', [
                        'user_id' => $existingUser->getId(),
                        'email' => $email
                    ]);
                } catch (\Throwable $e) {
                    error_log("Logging error in LoginUserUseCase: " . $e->getMessage());
                }
            }
            
            // Update last login and any changed data
            $existingUser->updateLastLogin();
            if ($existingUser->getEmail() !== $email) {
                $existingUser->setEmail($email);
            }
            if ($existingUser->getName() !== $name) {
                $existingUser->setName($name);
            }
            if ($existingUser->getPicture() !== $picture) {
                $existingUser->setPicture($picture);
            }
            
            $this->userRepository->update($existingUser);
            
            if (function_exists('logger')) {
                try {
                    logger('auth')->auth('login', (string)$existingUser->getId(), true, [
                        'login_type' => 'google_oauth'
                    ]);
                } catch (\Throwable $e) {
                    error_log("Logging error in LoginUserUseCase: " . $e->getMessage());
                }
            }
            
            return $existingUser;
        }

        if (function_exists('logger')) {
            try {
                logger('auth')->info('Creating new user account', [
                    'google_id' => $googleId,
                    'email' => $email
                ]);
            } catch (\Throwable $e) {
                error_log("Logging error in LoginUserUseCase: " . $e->getMessage());
            }
        }

        // Create new user
        $newUser = User::create([
            'google_id' => $googleId,
            'email' => $email,
            'name' => $name,
            'picture' => $picture
        ]);

        $savedUser = $this->userRepository->save($newUser);
        
        if (function_exists('logger')) {
            try {
                logger('auth')->auth('register', (string)$savedUser->getId(), true, [
                    'login_type' => 'google_oauth'
                ]);
            } catch (\Throwable $e) {
                error_log("Logging error in LoginUserUseCase: " . $e->getMessage());
            }
        }

        return $savedUser;
    }
}
