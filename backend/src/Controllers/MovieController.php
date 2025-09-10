<?php
namespace App\Controllers;

use App\Domain\UseCases\Movies\AddMovieUseCase;
use App\Domain\UseCases\Movies\DeleteMovieUseCase;
use App\Domain\UseCases\Movies\UpdateMovieRatingUseCase;
use App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Domain\UseCases\Movies\EditUserMovieUseCase;
use App\Domain\Repository\MovieRepositoryInterface;

class MovieController extends BaseController implements Contracts\MovieControllerInterface
{

    private AddMovieUseCase $addMovieUseCase;
    private DeleteMovieUseCase $deleteMovieUseCase;
    private UpdateMovieRatingUseCase $updateMovieRatingUseCase;
    private UpdateMovieUserStatusesUseCase $updateMovieUserStatusesUseCase;
    private GetMoviesUseCase $getMoviesUseCase;
    private GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase;
    private AuthMiddleware $authMiddleware;
    private EditUserMovieUseCase $editUserMovieUseCase;
    private MovieRepositoryInterface $movieRepository;

    public function __construct(
        AddMovieUseCase $addMovieUseCase,
        DeleteMovieUseCase $deleteMovieUseCase,
        UpdateMovieRatingUseCase $updateMovieRatingUseCase,
        UpdateMovieUserStatusesUseCase $updateMovieUserStatusesUseCase,
        GetMoviesUseCase $getMoviesUseCase,
        GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase,
        AuthMiddleware $authMiddleware,
        EditUserMovieUseCase $editUserMovieUseCase,
        MovieRepositoryInterface $movieRepository
    ) {
        $this->addMovieUseCase = $addMovieUseCase;
        $this->deleteMovieUseCase = $deleteMovieUseCase;
        $this->updateMovieRatingUseCase = $updateMovieRatingUseCase;
        $this->updateMovieUserStatusesUseCase = $updateMovieUserStatusesUseCase;
        $this->getMoviesUseCase = $getMoviesUseCase;
        $this->getMovieAllowedStatusesUseCase = $getMovieAllowedStatusesUseCase;
        $this->editUserMovieUseCase = $editUserMovieUseCase;
        $this->authMiddleware = $authMiddleware;
        $this->movieRepository = $movieRepository;
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
     * Modifica todos los aspectos de un user_movie: datos principales, tags y notas.
     * @param string $movieIsbn
     * @param int $userId
     * @param array $data
     * @param array $tags
     * @param array $notes
     * @return array
     */
    public function editUserMovie(string $movieIsbn, int $userId, array $data = [], array $tags = [], array $notes = []): array
    {
        if (empty($movieIsbn) || empty($userId)) {
            throw new \InvalidArgumentException('movieIsbn y userId son requeridos para editar user_movie.');
        }

        $this->editUserMovieUseCase->execute($userId, $movieIsbn, $data, $tags, $notes);
        return $this->successResponse('User movie actualizado correctamente.');
    }

    /**
     * Obtiene todos los tags del usuario para películas
     */
    public function getUserMovieTags(int $userId): array
    {
        try {
            $tags = $this->movieRepository->getUserMovieTags($userId);
            return $this->successResponse('Tags obtenidos correctamente', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tags: ' . $e->getMessage());
        }
    }

    /**
     * Crea un nuevo tag para el usuario (películas)
     */
    public function createUserMovieTag(int $userId, string $name, string $color = '#1976d2'): array
    {
        try {
            $tagId = $this->movieRepository->addUserMovieTag($userId, $name, $color);
            $newTag = ['id' => $tagId, 'name' => $name, 'color' => $color];
            return $this->successResponse('Tag creado correctamente', $newTag);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear tag: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene los tags de una película específica
     */
    public function getMovieTags(int $userId, string $movieIsbn): array
    {
        try {
            $tags = $this->movieRepository->getMovieTags($userId, $movieIsbn);
            return $this->successResponse('Tags de la película obtenidos correctamente', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tags de la película: ' . $e->getMessage());
        }
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
            $authRequiredActions = ['add_movie', 'delete_movie', 'update_movie_rating', 'update_movie_user_statuses', 'get_movies', 'edit_user_movie', 'get_user_movie_tags', 'create_user_movie_tag', 'get_movie_tags'];
            
            if (in_array($action, $authRequiredActions)) {
                $authResult = $this->authMiddleware->requireAuth();
                if ($authResult['status'] === 'error') {
                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo json_encode($authResult);
                    exit();
                }
                
                // Check CSRF for modifying actions
                $csrfRequiredActions = ['add_movie', 'delete_movie', 'update_movie_rating', 'update_movie_user_statuses', 'edit_user_movie', 'create_user_movie_tag'];
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
                'delete_movie' => $this->deleteMovie($inputData['movieId'] ?? 0, $authResult['user']['id']),
                'update_movie_rating' => $this->updateMovieRating($inputData['movieId'] ?? 0, $inputData['rating'] ?? null, $authResult['user']['id']),
                'update_movie_user_statuses' => $this->updateMovieUserStatuses($inputData['movieId'] ?? 0, $inputData['statuses'] ?? [], $authResult['user']['id']),
                'get_movie_allowed_statuses' => $this->getMovieAllowedStatuses(),
                'get_movies' => $this->getMovies($authResult['user']['id']),
                'edit_user_movie' => $this->editUserMovie($inputData['movieId'] ?? 0, $authResult['user']['id'], $inputData['data'] ?? [], $inputData['tags'] ?? [], $inputData['notes'] ?? []),
                'get_user_movie_tags' => $this->getUserMovieTags($authResult['user']['id']),
                'create_user_movie_tag' => $this->createUserMovieTag($authResult['user']['id'], $inputData['name'] ?? '', $inputData['color'] ?? '#1976d2'),
                'get_movie_tags' => $this->getMovieTags($authResult['user']['id'], $inputData['movieIsbn'] ?? ''),
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
