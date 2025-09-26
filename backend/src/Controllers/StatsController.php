<?php
namespace App\Controllers;

use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\MovieRepositoryInterface;
use App\Infrastructure\Middleware\AuthMiddleware;

class StatsController extends BaseController
{
    private BookRepositoryInterface $bookRepository;
    private MovieRepositoryInterface $movieRepository;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        BookRepositoryInterface $bookRepository,
        MovieRepositoryInterface $movieRepository,
        AuthMiddleware $authMiddleware
    ) {
        $this->bookRepository = $bookRepository;
        $this->movieRepository = $movieRepository;
        $this->authMiddleware = $authMiddleware;
    }

    /**
     * Handle HTTP requests for statistics endpoints
     */
    public function handleRequest(string $method, string $path): void
    {
        try {
            // Set JSON content type
            header('Content-Type: application/json');
            
            // Parse input data
            $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
            $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;
            
            // Authenticate user
            $authResult = $this->authMiddleware->requireAuth();
            if ($authResult['status'] === 'error') {
                http_response_code(401);
                echo json_encode($authResult);
                return;
            }
            
            $userId = $authResult['user']['id'];
            
            // Route to appropriate method based on action
            switch ($action) {
                case 'get_book_stats':
                    $response = $this->getBookStats($userId);
                    break;
                    
                case 'get_movie_stats':
                    $response = $this->getMovieStats($userId);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Invalid action for statistics endpoint: ' . $action
                    ]);
                    return;
            }
            
            // Send response
            $httpCode = $response['http_code'] ?? ($response['status'] === 'success' ? 200 : 400);
            http_response_code($httpCode);
            echo json_encode($response, JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage()
            ]);
        }
    }

    public function getBookStats(int $userId): array
    {
        try {
            // Obtener todos los libros del usuario
            $books = $this->bookRepository->findBooksByUser($userId);
            
            $stats = [
                'totalBooks' => count($books),
                'genreStats' => $this->calculateBookGenreStats($books),
                'statusStats' => $this->calculateBookStatusStats($books),
                'ratingStats' => $this->calculateBookRatingStats($books),
                'monthlyStats' => $this->calculateBookMonthlyStats($books),
                'monthlyPagesStats' => $this->calculateMonthlyPagesStats($userId)
            ];

            return $this->successResponse('Book statistics retrieved successfully', $stats);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve book statistics: ' . $e->getMessage(), 500);
        }
    }

    public function getMovieStats(int $userId): array
    {
        try {
            // Obtener todas las películas del usuario
            $movies = $this->movieRepository->findMoviesByUser($userId);
            
            $stats = [
                'totalMovies' => count($movies),
                'genreStats' => $this->calculateMovieGenreStats($movies),
                'statusStats' => $this->calculateMovieStatusStats($movies),
                'ratingStats' => $this->calculateMovieRatingStats($movies),
                'monthlyStats' => $this->calculateMovieMonthlyStats($movies),
                'decadeStats' => $this->calculateMovieDecadeStats($movies)
            ];

            return $this->successResponse('Movie statistics retrieved successfully', $stats);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve movie statistics: ' . $e->getMessage(), 500);
        }
    }

    private function calculateBookGenreStats(array $books): array
    {
        $genreCounts = [];
        $totalWithGenres = 0;

        foreach ($books as $book) {
            $genres = $book->getGenres();
            if (!empty($genres) && is_array($genres)) {
                $totalWithGenres++;
                foreach ($genres as $genre) {
                    if (!empty($genre)) {
                        $genreCounts[$genre] = ($genreCounts[$genre] ?? 0) + 1;
                    }
                }
            }
        }

        // Ordenar por popularidad y tomar los top 10
        arsort($genreCounts);
        $topGenres = array_slice($genreCounts, 0, 10, true);

        return [
            'topGenres' => $topGenres,
            'totalGenres' => count($genreCounts),
            'booksWithGenres' => $totalWithGenres,
            'booksWithoutGenres' => count($books) - $totalWithGenres
        ];
    }

    private function calculateMovieGenreStats(array $movies): array
    {
        $genreCounts = [];
        $totalWithGenres = 0;

        foreach ($movies as $movie) {
            $genres = $movie->getGenres();
            if (!empty($genres) && is_array($genres)) {
                $totalWithGenres++;
                foreach ($genres as $genre) {
                    if (!empty($genre)) {
                        $genreCounts[$genre] = ($genreCounts[$genre] ?? 0) + 1;
                    }
                }
            }
        }

        // Ordenar por popularidad y tomar los top 10
        arsort($genreCounts);
        $topGenres = array_slice($genreCounts, 0, 10, true);

        return [
            'topGenres' => $topGenres,
            'totalGenres' => count($genreCounts),
            'moviesWithGenres' => $totalWithGenres,
            'moviesWithoutGenres' => count($movies) - $totalWithGenres
        ];
    }

    private function calculateBookStatusStats(array $books): array
    {
        $statusCounts = [];
        foreach ($books as $book) {
            $statuses = $book->getUserStatuses();
            foreach ($statuses as $status) {
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
        }
        return $statusCounts;
    }

    private function calculateMovieStatusStats(array $movies): array
    {
        $statusCounts = [];
        foreach ($movies as $movie) {
            $statuses = $movie->getUserStatuses();
            foreach ($statuses as $status) {
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
        }
        return $statusCounts;
    }

    private function calculateBookRatingStats(array $books): array
    {
        $ratingCounts = [];
        $totalRated = 0;
        $sumRatings = 0;

        foreach ($books as $book) {
            $rating = $book->getUserRating();
            if ($rating !== null && $rating > 0) {
                // Redondear a 0.5 más cercano para agrupar medios puntos
                $roundedRating = round($rating * 2) / 2;
                if ($roundedRating >= 1 && $roundedRating <= 5) {
                    $ratingKey = (string)$roundedRating;
                    $ratingCounts[$ratingKey] = ($ratingCounts[$ratingKey] ?? 0) + 1;
                    $totalRated++;
                    $sumRatings += $rating;
                }
            }
        }

        // Ordenar por rating
        ksort($ratingCounts);

        return [
            'distribution' => $ratingCounts,
            'totalRated' => $totalRated,
            'averageRating' => $totalRated > 0 ? round($sumRatings / $totalRated, 1) : 0
        ];
    }

    private function calculateMovieRatingStats(array $movies): array
    {
        $ratingCounts = [];
        $totalRated = 0;
        $sumRatings = 0;

        foreach ($movies as $movie) {
            $rating = $movie->getUserRating();
            if ($rating !== null && $rating > 0) {
                // Redondear a 0.5 más cercano para agrupar medios puntos
                $roundedRating = round($rating * 2) / 2;
                if ($roundedRating >= 1 && $roundedRating <= 5) {
                    $ratingKey = (string)$roundedRating;
                    $ratingCounts[$ratingKey] = ($ratingCounts[$ratingKey] ?? 0) + 1;
                    $totalRated++;
                    $sumRatings += $rating;
                }
            }
        }

        // Ordenar por rating
        ksort($ratingCounts);

        return [
            'distribution' => $ratingCounts,
            'totalRated' => $totalRated,
            'averageRating' => $totalRated > 0 ? round($sumRatings / $totalRated, 1) : 0
        ];
    }

    private function calculateBookMonthlyStats(array $books): array
    {
        $monthlyCounts = [];
        $currentYear = date('Y');

        foreach ($books as $book) {
            $timestamp = $book->getAddedTimestamp();
            if ($timestamp) {
                $month = date('Y-m', $timestamp);
                $monthlyCounts[$month] = ($monthlyCounts[$month] ?? 0) + 1;
            }
        }

        // Generar datos para los últimos 12 meses
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthlyData[$month] = $monthlyCounts[$month] ?? 0;
        }

        return $monthlyData;
    }

    private function calculateMovieMonthlyStats(array $movies): array
    {
        $monthlyCounts = [];

        foreach ($movies as $movie) {
            $timestamp = $movie->getAddedTimestamp();
            if ($timestamp) {
                $month = date('Y-m', $timestamp);
                $monthlyCounts[$month] = ($monthlyCounts[$month] ?? 0) + 1;
            }
        }

        // Generar datos para los últimos 12 meses
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthlyData[$month] = $monthlyCounts[$month] ?? 0;
        }

        return $monthlyData;
    }

    private function calculateMovieDecadeStats(array $movies): array
    {
        $decadeCounts = [];

        foreach ($movies as $movie) {
            // Para películas, podríamos usar el año de lanzamiento si estuviera disponible
            // Por ahora, usaremos el timestamp de cuando se agregó
            $timestamp = $movie->getAddedTimestamp();
            if ($timestamp) {
                $year = (int)date('Y', $timestamp);
                $decade = (int)(floor($year / 10) * 10);
                $decadeLabel = $decade . 's';
                $decadeCounts[$decadeLabel] = ($decadeCounts[$decadeLabel] ?? 0) + 1;
            }
        }

        return $decadeCounts;
    }

    /**
     * Calcula las estadísticas de páginas leídas por mes usando el historial de progreso
     */
    private function calculateMonthlyPagesStats(int $userId): array
    {
        try {
            return $this->bookRepository->getMonthlyPagesReadStats($userId, 12);
        } catch (\Exception $e) {
            // En caso de error, devolver array vacío para evitar que falle toda la respuesta
            return [];
        }
    }
}