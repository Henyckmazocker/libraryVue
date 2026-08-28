<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\DTO\Commands\AcceptFriendRequestCommand;
use App\Domain\DTO\Commands\RejectFriendRequestCommand;
use App\Domain\DTO\Commands\RemoveFriendCommand;
use App\Domain\DTO\Commands\ResolveRecommendationCommand;
use App\Domain\DTO\Commands\SendFriendRequestCommand;
use App\Domain\DTO\Commands\SendRecommendationCommand;
use App\Domain\DTO\Queries\GetFriendRequestsQuery;
use App\Domain\DTO\Queries\GetFriendsQuery;
use App\Domain\DTO\Queries\GetInboxCountQuery;
use App\Domain\DTO\Queries\GetInboxQuery;
use App\Domain\DTO\Queries\GetPublicProfileQuery;
use App\Domain\DTO\Queries\SearchUsersQuery;
use App\Domain\UseCases\Social\AcceptFriendRequestUseCase;
use App\Domain\UseCases\Social\GetFriendRequestsUseCase;
use App\Domain\UseCases\Social\GetFriendsUseCase;
use App\Domain\UseCases\Social\GetInboxCountUseCase;
use App\Domain\UseCases\Social\GetInboxUseCase;
use App\Domain\UseCases\Social\GetPublicProfileUseCase;
use App\Domain\UseCases\Social\RejectFriendRequestUseCase;
use App\Domain\UseCases\Social\RemoveFriendUseCase;
use App\Domain\UseCases\Social\ResolveRecommendationUseCase;
use App\Domain\UseCases\Social\SearchUsersUseCase;
use App\Domain\UseCases\Social\SendFriendRequestUseCase;
use App\Domain\UseCases\Social\SendRecommendationUseCase;
use DomainException;
use RuntimeException;

class SocialController extends BaseController
{
    public function __construct(
        private readonly SendFriendRequestUseCase   $sendFriendRequestUseCase,
        private readonly AcceptFriendRequestUseCase $acceptFriendRequestUseCase,
        private readonly RejectFriendRequestUseCase $rejectFriendRequestUseCase,
        private readonly RemoveFriendUseCase        $removeFriendUseCase,
        private readonly GetFriendsUseCase          $getFriendsUseCase,
        private readonly GetFriendRequestsUseCase   $getFriendRequestsUseCase,
        private readonly SearchUsersUseCase         $searchUsersUseCase,
        private readonly GetPublicProfileUseCase    $getPublicProfileUseCase,
        private readonly SendRecommendationUseCase  $sendRecommendationUseCase,
        private readonly GetInboxUseCase            $getInboxUseCase,
        private readonly GetInboxCountUseCase       $getInboxCountUseCase,
        private readonly ResolveRecommendationUseCase $resolveRecommendationUseCase
    ) {}

    public function sendFriendRequest(SendFriendRequestCommand $command): array
    {
        try {
            $friendship = $this->sendFriendRequestUseCase->execute($command);
            return $this->successResponse('Friend request sent', $friendship->toArray(), 201);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function acceptFriendRequest(AcceptFriendRequestCommand $command): array
    {
        try {
            $friendship = $this->acceptFriendRequestUseCase->execute($command);
            return $this->successResponse('Friend request accepted', $friendship->toArray());
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function rejectFriendRequest(RejectFriendRequestCommand $command): array
    {
        try {
            $this->rejectFriendRequestUseCase->execute($command);
            return $this->successResponse('Friend request rejected');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function removeFriend(RemoveFriendCommand $command): array
    {
        try {
            $this->removeFriendUseCase->execute($command);
            return $this->successResponse('Friend removed');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function getFriends(GetFriendsQuery $query): array
    {
        $friends = $this->getFriendsUseCase->execute($query);
        return $this->successResponse('Friends retrieved', $friends);
    }

    public function getFriendRequests(GetFriendRequestsQuery $query): array
    {
        $requests = $this->getFriendRequestsUseCase->execute($query);
        return $this->successResponse('Friend requests retrieved', $requests);
    }

    public function searchUsers(SearchUsersQuery $query): array
    {
        $results = $this->searchUsersUseCase->execute($query);
        return $this->successResponse('Users found', $results);
    }

    public function getPublicProfile(GetPublicProfileQuery $query): array
    {
        try {
            $profile = $this->getPublicProfileUseCase->execute($query);
            return $this->successResponse('Profile retrieved', $profile);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    // =========================================================================
    // Recommendations (the inbox)
    // =========================================================================

    public function sendRecommendation(SendRecommendationCommand $command): array
    {
        try {
            $recommendation = $this->sendRecommendationUseCase->execute($command);
            return $this->successResponse('Recommendation sent', [
                'recommendationId' => $recommendation->getId(),
            ], 201);
        } catch (RuntimeException $e) {
            // No sois amigos (400) y ya se la mandaste (409) son la misma clase
            // de excepción: el estado los separa, no el tipo.
            $codigo = str_contains($e->getMessage(), 'already recommended') ? 409 : 400;
            return $this->errorResponse($e->getMessage(), $codigo);
        }
    }

    public function getInbox(GetInboxQuery $query): array
    {
        $inbox = $this->getInboxUseCase->execute($query);
        return $this->successResponse('Inbox retrieved', $inbox);
    }

    public function getInboxCount(GetInboxCountQuery $query): array
    {
        $count = $this->getInboxCountUseCase->execute($query);
        return $this->successResponse('Inbox count retrieved', $count);
    }

    public function resolveRecommendation(ResolveRecommendationCommand $command): array
    {
        try {
            $this->resolveRecommendationUseCase->execute($command);
            return $this->successResponse('Recommendation resolved');
        } catch (DomainException $e) {
            // Ajena: 403, no 404. Se distingue de «no existe» a propósito.
            return $this->errorResponse($e->getMessage(), 403);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
