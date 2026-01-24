<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\UseCases\Movies\AddMovieUseCase;
use App\Domain\UseCases\Movies\DeleteMovieUseCase;
use App\Domain\UseCases\Movies\UpdateMovieRatingUseCase;
use App\Domain\UseCases\Movies\UpdateMovieUserStatusesUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase;
use App\Domain\UseCases\Movies\EditUserMovieUseCase;
use App\Domain\UseCases\Movies\GetTrendingMoviesUseCase;
use App\Domain\DTO\Commands\AddMovieCommand;
use App\Domain\DTO\Commands\DeleteMovieCommand;
use App\Domain\DTO\Commands\UpdateMovieRatingCommand;
use App\Domain\DTO\Commands\UpdateMovieStatusesCommand;
use App\Domain\DTO\Commands\EditUserMovieCommand;
use App\Domain\DTO\Queries\GetMoviesByUserQuery;
use App\Domain\DTO\Queries\GetTrendingMoviesQuery;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Domain\Repository\Movie\MovieTagRepositoryInterface;
use App\Domain\Repository\Movie\MovieNoteRepositoryInterface;

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
    private MovieTagRepositoryInterface $movieTagRepository;
    private MovieNoteRepositoryInterface $movieNoteRepository;
    private GetTrendingMoviesUseCase $getTrendingMoviesUseCase;

    public function __construct(
        AddMovieUseCase $addMovieUseCase,
        DeleteMovieUseCase $deleteMovieUseCase,
        UpdateMovieRatingUseCase $updateMovieRatingUseCase,
        UpdateMovieUserStatusesUseCase $updateMovieUserStatusesUseCase,
        GetMoviesUseCase $getMoviesUseCase,
        GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase,
        AuthMiddleware $authMiddleware,
        EditUserMovieUseCase $editUserMovieUseCase,
        MovieTagRepositoryInterface $movieTagRepository,
        MovieNoteRepositoryInterface $movieNoteRepository,
        GetTrendingMoviesUseCase $getTrendingMoviesUseCase
    ) {
        $this->addMovieUseCase = $addMovieUseCase;
        $this->deleteMovieUseCase = $deleteMovieUseCase;
        $this->updateMovieRatingUseCase = $updateMovieRatingUseCase;
        $this->updateMovieUserStatusesUseCase = $updateMovieUserStatusesUseCase;
        $this->getMoviesUseCase = $getMoviesUseCase;
        $this->getMovieAllowedStatusesUseCase = $getMovieAllowedStatusesUseCase;
        $this->editUserMovieUseCase = $editUserMovieUseCase;
        $this->authMiddleware = $authMiddleware;
        $this->movieTagRepository = $movieTagRepository;
        $this->movieNoteRepository = $movieNoteRepository;
        $this->getTrendingMoviesUseCase = $getTrendingMoviesUseCase;
    }

    /**
     * Add a new movie to user's library
     * 
     * @param AddMovieCommand $command Command containing movie data and user ID
     * @return array Success response with movie data
     */
    public function addMovie(AddMovieCommand $command): array
    {
        $addedMovie = $this->addMovieUseCase->execute($command);
        return $this->successResponse('Movie added: ' . $addedMovie->getTitle(), $addedMovie->toArray(), 201);
    }

    /**
     * Delete a movie from user's library
     * 
     * @param DeleteMovieCommand $command Command containing user ID and movie ID
     * @return array Success response
     */
    public function deleteMovie(DeleteMovieCommand $command): array
    {
        $this->deleteMovieUseCase->execute($command);
        return $this->successResponse('Movie removed from your library: ' . $command->movieId);
    }

    /**
     * Update movie rating
     * 
     * @param UpdateMovieRatingCommand $command Command containing user ID, movie ID, and rating
     * @return array Success response
     */
    public function updateMovieRating(UpdateMovieRatingCommand $command): array
    {
        $this->updateMovieRatingUseCase->execute($command);
        return $this->successResponse('Movie rating updated successfully.');
    }

    /**
     * Update movie user statuses
     * 
     * @param UpdateMovieStatusesCommand $command Command containing user ID, movie ID, and statuses
     * @return array Success response
     */
    public function updateMovieUserStatuses(UpdateMovieStatusesCommand $command): array
    {
        $this->updateMovieUserStatusesUseCase->execute($command);
        return $this->successResponse('User statuses updated for Movie ID ' . $command->movieId);
    }

    public function getMovieAllowedStatuses(): array
    {
        $query = \App\Domain\DTO\Queries\GetAllowedStatusesQuery::forMovies();
        $statuses = $this->getMovieAllowedStatusesUseCase->execute($query);
        return $this->successResponse('Allowed movie statuses retrieved.', $statuses);
    }

    /**
     * Get user's movies
     * 
     * @param GetMoviesByUserQuery $query Query containing user ID
     * @return array Success response with movies data
     */
    public function getMovies(GetMoviesByUserQuery $query): array
    {
        $movies = $this->getMoviesUseCase->execute($query);
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
    /**
     * Edit all aspects of a user_movie: main data, tags, and notes
     * 
     * @param EditUserMovieCommand $command Command containing all edit data
     * @return array Success response
     */
    public function editUserMovie(EditUserMovieCommand $command): array
    {
        $this->editUserMovieUseCase->execute($command);
        return $this->successResponse('User movie actualizado correctamente.');
    }

    /**
     * Obtiene todos los tags del usuario para películas
     */
    public function getUserMovieTags(int $userId): array
    {
        try {
            $tags = $this->movieTagRepository->getByUser($userId);
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
            $tagId = $this->movieTagRepository->create($userId, $name, $color);
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
            $tags = $this->movieTagRepository->getByMovie($userId, $movieIsbn);
            return $this->successResponse('Tags de la película obtenidos correctamente', $tags);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tags de la película: ' . $e->getMessage());
        }
    }

    /**
     * Get trending movies based on user activity
     * 
     * @param GetTrendingMoviesQuery $query Query containing limit and daysWindow
     * @return array Success response with trending movies data
     */
    public function getTrendingMovies(GetTrendingMoviesQuery $query): array
    {
        // Get authenticated user ID from session
        $userId = $_SESSION['user_data']['id'] ?? null;
        
        // Create query with userId
        $queryWithUser = GetTrendingMoviesQuery::create(
            $query->limit,
            $query->daysWindow,
            $userId
        );
        
        $trendingMovies = $this->getTrendingMoviesUseCase->execute($queryWithUser);
        return $this->successResponse('Trending movies retrieved.', $trendingMovies);
    }
}
