<?php
declare(strict_types=1);

namespace App\Application\UseCase\Auth;

use App\Application\Domain\Repository\UserRepositoryInterface;
use App\Application\Domain\Model\User;
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
            throw new InvalidArgumentException('Invalid Google token data. Missing required fields.');
        }

        $googleId = $googleTokenData['sub'];
        $email = $googleTokenData['email'];
        $name = $googleTokenData['name'];
        $picture = $googleTokenData['picture'] ?? null;

        // Try to find existing user
        $existingUser = $this->userRepository->findByGoogleId($googleId);
        
        if ($existingUser) {
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
            return $existingUser;
        }

        // Create new user
        $newUser = User::create([
            'google_id' => $googleId,
            'email' => $email,
            'name' => $name,
            'picture' => $picture
        ]);

        return $this->userRepository->save($newUser);
    }
}
