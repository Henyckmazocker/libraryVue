<?php
namespace App\Controllers;

use App\Domain\UseCases\Movies\AddMovieUseCase;
use App\Domain\UseCases\Movies\DeleteMovieUseCase;
use App\Domain\UseCases\Movies\UpdateMovieRatingUseCase;
use App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase;
use App\Infrastructure\Middleware\AuthMiddleware;

class MovieController extends BaseController implements Contracts\MovieControllerInterface
{
    private AddMovieUseCase $addMovieUseCase;
    private DeleteMovieUseCase $deleteMovieUseCase;
    private UpdateMovieRatingUseCase $updateMovieRatingUseCase;
    private UpdateMovieUserStatusesUseCase $updateMovieUserStatusesUseCase;
    private GetMoviesUseCase $getMoviesUseCase;
    private GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        AddMovieUseCase $addMovieUseCase,
        DeleteMovieUseCase $deleteMovieUseCase,
        UpdateMovieRatingUseCase $updateMovieRatingUseCase,
        UpdateMovieUserStatusesUseCase $updateMovieUserStatusesUseCase,
        GetMoviesUseCase $getMoviesUseCase,
        GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase,
        AuthMiddleware $authMiddleware
    ) {
        $this->addMovieUseCase = $addMovieUseCase;
        $this->deleteMovieUseCase = $deleteMovieUseCase;
        $this->updateMovieRatingUseCase = $updateMovieRatingUseCase;
        $this->updateMovieUserStatusesUseCase = $updateMovieUserStatusesUseCase;
        $this->getMoviesUseCase = $getMoviesUseCase;
        $this->getMovieAllowedStatusesUseCase = $getMovieAllowedStatusesUseCase;
        $this->authMiddleware = $authMiddleware;
    }

    public function addMovie(array $movieData, int $userId): array
    {
        if (empty($movieData)) {
            throw new \InvalidArgumentException('Movie data is required for add_movie action.');
        }
        
        $addedMovie = $this->addMovieUseCase->execute($movieData, $userId);
        return $this->successResponse('Movie added: ' . $addedMovie->getTitle(), $addedMovie->toArray(), 201);
    }

    public function deleteMovie(string $movieId, int $userId): array
    {
        if (empty($movieId)) {
            throw new \InvalidArgumentException('ID is required for delete_movie action.');
        }
        
        $this->deleteMovieUseCase->execute($userId, $movieId);
        return $this->successResponse('Movie removed from your library: ' . $movieId);
    }

    public function updateMovieRating(string $movieId, ?float $rating, int $userId): array
    {
        if (empty($movieId)) {
            return $this->errorResponse('movieId is required to update movie rating.');
        }
        
        // Allow null rating for unrating
        if ($rating === 0.0) {
            $rating = null;
        }
        
        $this->updateMovieRatingUseCase->execute($userId, $movieId, $rating);
        return $this->successResponse('Movie rating updated successfully.');
    }

    public function updateMovieUserStatuses(string $movieId, array $statuses, int $userId): array
    {
        if (empty($movieId)) {
            throw new \InvalidArgumentException('movieId is required for update_movie_user_statuses.');
        }
        
        if (empty($statuses)) {
            throw new \InvalidArgumentException('Statuses must be a non-empty array.');
        }
        
        $this->updateMovieUserStatusesUseCase->execute($userId, $movieId, $statuses);
        return $this->successResponse('User statuses updated for Movie ID ' . $movieId);
    }

    public function getMovieAllowedStatuses(): array
    {
        $statuses = $this->getMovieAllowedStatusesUseCase->execute();
        return $this->successResponse('Allowed movie statuses retrieved.', $statuses);
    }

    public function getMovies(int $userId): array
    {
        $movies = $this->getMoviesUseCase->execute($userId);
        return $this->successResponse('Movies data retrieved.', $movies);
    }

    /**
     * Handle HTTP request for movie endpoints
     */
    public function handleRequest(string $method, string $path): void
    {
        try {
            $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
            $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;
            
            // Handle authentication for actions that require it
            $authResult = null;
            $authRequiredActions = ['add_movie', 'delete_movie', 'update_movie_rating', 'update_movie_user_statuses', 'get_movies'];
            
            if (in_array($action, $authRequiredActions)) {
                $authResult = $this->authMiddleware->requireAuth();
                if ($authResult['status'] === 'error') {
                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo json_encode($authResult);
                    exit();
                }
                
                // Check CSRF for modifying actions
                $csrfRequiredActions = ['add_movie', 'delete_movie', 'update_movie_rating', 'update_movie_user_statuses'];
                if (in_array($action, $csrfRequiredActions)) {
                    $csrfResult = $this->authMiddleware->requireAuthAndCSRF($inputData['csrf_token'] ?? null);
                    if ($csrfResult['status'] === 'error') {
                        http_response_code(403);
                        header('Content-Type: application/json');
                        echo json_encode($csrfResult);
                        exit();
                    }
                    $authResult = $csrfResult;
                }
            }
            
            $response = match ($action) {
                'add_movie' => $this->addMovie($inputData['movie'] ?? [], $authResult['user']['id']),
                'delete_movie' => $this->deleteMovie($inputData['id'] ?? 0, $authResult['user']['id']),
                'update_movie_rating' => $this->updateMovieRating($inputData['id'] ?? 0, $inputData['rating'] ?? null, $authResult['user']['id']),
                'update_movie_user_statuses' => $this->updateMovieUserStatuses($inputData['id'] ?? 0, $inputData['statuses'] ?? [], $authResult['user']['id']),
                'get_movie_allowed_statuses' => $this->getMovieAllowedStatuses(),
                'get_movies' => $this->getMovies($authResult['user']['id']),
                default => $this->errorResponse('Invalid movie action: ' . $action)
            };
            
            $statusCode = $response['status'] === 'success' ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit();

        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage()
            ], JSON_PRETTY_PRINT);
            exit();
        }
    }
}
