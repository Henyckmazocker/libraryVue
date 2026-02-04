<?php
namespace App\Controllers;

use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\WorkRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Book\ReadingProgressRepositoryInterface;
use App\Infrastructure\Middleware\AuthMiddleware;

class StatsController extends BaseController
{
    private UserBookEditionRepositoryInterface $userBookEditionRepository;
    private EditionRepositoryInterface $editionRepository;
    private WorkRepositoryInterface $workRepository;
    private UserMovieRepositoryInterface $userMovieRepository;
    private ReadingProgressRepositoryInterface $readingProgressRepository;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        UserBookEditionRepositoryInterface $userBookEditionRepository,
        EditionRepositoryInterface $editionRepository,
        WorkRepositoryInterface $workRepository,
        UserMovieRepositoryInterface $userMovieRepository,
        ReadingProgressRepositoryInterface $readingProgressRepository,
        AuthMiddleware $authMiddleware
    ) {
        $this->userBookEditionRepository = $userBookEditionRepository;
        $this->editionRepository = $editionRepository;
        $this->workRepository = $workRepository;
        $this->userMovieRepository = $userMovieRepository;
        $this->readingProgressRepository = $readingProgressRepository;
        $this->authMiddleware = $authMiddleware;
    }

    /**
     * Handle HTTP requests for statistics endpoints
     */
    public function getBookStats(int $userId): array
    {
        try {
            // Obtener todas las ediciones del usuario con datos completos
            $userEditions = $this->userBookEditionRepository->findByUser($userId);
            
            // Enriquecer con datos de Work para géneros y otros campos
            $enrichedBooks = [];
            foreach ($userEditions as $userEdition) {
                $edition = $this->editionRepository->findById($userEdition->getEditionId());
                if ($edition) {
                    $work = $this->workRepository->findById($edition->getWorkId());
                    if ($work) {
                        // Combinar datos de UserBookEdition + Edition + Work
                        $enrichedBooks[] = (object)[
                            'userRating' => $userEdition->getWorkRating(),
                            'userStatuses' => $this->userBookEditionRepository->getStatusesForEdition(
                                $userId, 
                                $userEdition->getEditionId()
                            ),
                            'genres' => $work->getSubjects() ?? [],
                            'addedAt' => $userEdition->getAddedAt(),
                            'consumedAt' => $userEdition->getConsumedAt(),
                            'pages' => $edition->getPages()
                        ];
                    }
                }
            }
            
            $stats = [
                'totalBooks' => count($userEditions),
                'genreStats' => $this->calculateBookGenreStats($enrichedBooks),
                'statusStats' => $this->calculateBookStatusStats($enrichedBooks),
                'ratingStats' => $this->calculateBookRatingStats($enrichedBooks),
                'monthlyStats' => $this->calculateBookMonthlyStats($enrichedBooks),
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
            $movies = $this->userMovieRepository->findByUser($userId);
            
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
            $genres = $book->genres ?? [];
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
            $statuses = $book->userStatuses ?? [];
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
            $rating = $book->userRating ?? null;
            if ($rating !== null) {
                $ratingValue = is_object($rating) ? $rating->toFloat() : (float)$rating;
                if ($ratingValue > 0) {
                    // Redondear a 0.5 más cercano para agrupar medios puntos
                    $roundedRating = round($ratingValue * 2) / 2;
                    if ($roundedRating >= 1 && $roundedRating <= 5) {
                        $ratingKey = (string)$roundedRating;
                        $ratingCounts[$ratingKey] = ($ratingCounts[$ratingKey] ?? 0) + 1;
                        $totalRated++;
                        $sumRatings += $ratingValue;
                    }
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
            if ($rating !== null) {
                $ratingValue = $rating->toFloat();
                if ($ratingValue > 0) {
                    // Redondear a 0.5 más cercano para agrupar medios puntos
                    $roundedRating = round($ratingValue * 2) / 2;
                    if ($roundedRating >= 1 && $roundedRating <= 5) {
                        $ratingKey = (string)$roundedRating;
                        $ratingCounts[$ratingKey] = ($ratingCounts[$ratingKey] ?? 0) + 1;
                        $totalRated++;
                        $sumRatings += $ratingValue;
                    }
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
            $timestamp = $book->addedAt ?? null;
            if ($timestamp) {
                $unixTime = is_object($timestamp) ? $timestamp->toUnixTimestamp() : strtotime($timestamp);
                $month = date('Y-m', $unixTime);
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
                $month = date('Y-m', $timestamp->toUnixTimestamp());
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
                $year = (int)date('Y', $timestamp->toUnixTimestamp());
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
            $stats = $this->readingProgressRepository->getMonthlyStats($userId, 12);
            return $stats;
        } catch (\Exception $e) {
            // En caso de error, devolver array vacío para evitar que falle toda la respuesta
            return [];
        }
    }
}