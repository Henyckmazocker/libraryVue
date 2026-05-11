<?php
namespace App\Controllers;

use App\Controllers\Contracts\StatsControllerInterface;
use App\Domain\Repository\Book\UserBookEditionRepositoryInterface;
use App\Domain\Repository\Book\EditionRepositoryInterface;
use App\Domain\Repository\Book\WorkRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Game\UserGameRepositoryInterface;
use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\Repository\Video\UserVideoRepositoryInterface;
use App\Domain\Repository\Book\ReadingProgressRepositoryInterface;
use App\Infrastructure\Middleware\AuthMiddleware;

class StatsController extends BaseController implements StatsControllerInterface
{
    private UserBookEditionRepositoryInterface $userBookEditionRepository;
    private EditionRepositoryInterface $editionRepository;
    private WorkRepositoryInterface $workRepository;
    private UserMovieRepositoryInterface $userMovieRepository;
    private UserGameRepositoryInterface $userGameRepository;
    private UserAlbumRepositoryInterface $userAlbumRepository;
    private UserVideoRepositoryInterface $userVideoRepository;
    private ReadingProgressRepositoryInterface $readingProgressRepository;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        UserBookEditionRepositoryInterface $userBookEditionRepository,
        EditionRepositoryInterface $editionRepository,
        WorkRepositoryInterface $workRepository,
        UserMovieRepositoryInterface $userMovieRepository,
        UserGameRepositoryInterface $userGameRepository,
        UserAlbumRepositoryInterface $userAlbumRepository,
        UserVideoRepositoryInterface $userVideoRepository,
        ReadingProgressRepositoryInterface $readingProgressRepository,
        AuthMiddleware $authMiddleware
    ) {
        $this->userBookEditionRepository = $userBookEditionRepository;
        $this->editionRepository = $editionRepository;
        $this->workRepository = $workRepository;
        $this->userMovieRepository = $userMovieRepository;
        $this->userGameRepository = $userGameRepository;
        $this->userAlbumRepository = $userAlbumRepository;
        $this->userVideoRepository = $userVideoRepository;
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
            error_log('[StatsController] Error getting book stats: ' . $e->getMessage());
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

    public function getGameStats(int $userId): array
    {
        try {
            // Obtener todos los juegos del usuario
            $games = $this->userGameRepository->findByUser($userId);
            
            $stats = [
                'totalGames' => count($games),
                'genreStats' => $this->calculateGameGenreStats($games),
                'statusStats' => $this->calculateGameStatusStats($games),
                'ratingStats' => $this->calculateGameRatingStats($games),
                'monthlyStats' => $this->calculateGameMonthlyStats($games),
                'platformStats' => $this->calculateGamePlatformStats($games),
                'hoursPlayedStats' => $this->calculateGameHoursPlayedStats($games)
            ];

            return $this->successResponse('Game statistics retrieved successfully', $stats);
        } catch (\Exception $e) {
            error_log('[StatsController] Error getting game stats: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve game statistics: ' . $e->getMessage(), 500);
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

            // Transformar el resultado de array de objetos a objeto simple mes => páginas
            $monthlyPages = [];
            foreach ($stats as $stat) {
                $monthlyPages[$stat['month']] = (int)$stat['pages_read'];
            }

            // Generar datos para los últimos 12 meses (incluir meses sin datos con 0)
            $result = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $result[$month] = $monthlyPages[$month] ?? 0;
            }

            return $result;
        } catch (\Exception $e) {
            // En caso de error, devolver array vacío para evitar que falle toda la respuesta
            return [];
        }
    }

    private function calculateGameGenreStats(array $games): array
    {
        $genreCounts = [];
        $totalWithGenres = 0;

        foreach ($games as $game) {
            $genres = $game->getGenres();
            if (!empty($genres) && is_array($genres)) {
                $totalWithGenres++;
                foreach ($genres as $genre) {
                    // Genre objects have a toString method
                    $genreName = is_object($genre) ? (string)$genre : (string)$genre;
                    if (!empty($genreName)) {
                        $genreCounts[$genreName] = ($genreCounts[$genreName] ?? 0) + 1;
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
            'gamesWithGenres' => $totalWithGenres,
            'gamesWithoutGenres' => count($games) - $totalWithGenres
        ];
    }

    private function calculateGameStatusStats(array $games): array
    {
        $statusCounts = [];
        foreach ($games as $game) {
            $statuses = $game->getUserStatuses();
            foreach ($statuses as $status) {
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
        }
        return $statusCounts;
    }

    private function calculateGameRatingStats(array $games): array
    {
        $ratingCounts = [];
        $totalRated = 0;
        $sumRatings = 0;

        foreach ($games as $game) {
            $rating = $game->getUserRating();
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

    private function calculateGameMonthlyStats(array $games): array
    {
        $monthlyCounts = [];

        foreach ($games as $game) {
            $timestamp = $game->getAddedTimestamp();
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

    private function calculateGamePlatformStats(array $games): array
    {
        $platformCounts = [];
        $totalWithPlatforms = 0;

        foreach ($games as $game) {
            $platforms = $game->getPlatforms();
            if (!empty($platforms) && is_array($platforms)) {
                $totalWithPlatforms++;
                foreach ($platforms as $platform) {
                    $platformName = is_string($platform) ? $platform : (string)$platform;
                    if (!empty($platformName)) {
                        $platformCounts[$platformName] = ($platformCounts[$platformName] ?? 0) + 1;
                    }
                }
            }
        }

        // Ordenar por popularidad y tomar los top 10
        arsort($platformCounts);
        $topPlatforms = array_slice($platformCounts, 0, 10, true);

        return [
            'topPlatforms' => $topPlatforms,
            'totalPlatforms' => count($platformCounts),
            'gamesWithPlatforms' => $totalWithPlatforms,
            'gamesWithoutPlatforms' => count($games) - $totalWithPlatforms
        ];
    }

    private function calculateGameHoursPlayedStats(array $games): array
    {
        $totalHours = 0;
        $gamesWithHours = 0;

        foreach ($games as $game) {
            $hoursPlayed = $game->getHoursPlayed();
            if ($hoursPlayed !== null && $hoursPlayed > 0) {
                $totalHours += $hoursPlayed;
                $gamesWithHours++;
            }
        }

        return [
            'totalHours' => round($totalHours, 1),
            'gamesWithHours' => $gamesWithHours,
            'averageHours' => $gamesWithHours > 0 ? round($totalHours / $gamesWithHours, 1) : 0
        ];
    }

    public function getAlbumStats(int $userId): array
    {
        try {
            $albums = $this->userAlbumRepository->findByUser($userId);

            $stats = [
                'totalAlbums'    => count($albums),
                'genreStats'     => $this->calculateAlbumGenreStats($albums),
                'statusStats'    => $this->calculateAlbumStatusStats($albums),
                'ratingStats'    => $this->calculateAlbumRatingStats($albums),
                'monthlyStats'   => $this->calculateAlbumMonthlyStats($albums),
                'albumTypeStats' => $this->calculateAlbumTypeStats($albums),
                'listenStats'    => $this->calculateAlbumListenStats($albums),
            ];

            return $this->successResponse('Album statistics retrieved successfully', $stats);
        } catch (\Exception $e) {
            error_log('[StatsController] Error getting album stats: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve album statistics: ' . $e->getMessage(), 500);
        }
    }

    private function calculateAlbumGenreStats(array $albums): array
    {
        $genreCounts = [];
        $totalWithGenres = 0;

        foreach ($albums as $album) {
            $genres = $album->getGenres();
            if (!empty($genres) && is_array($genres)) {
                $totalWithGenres++;
                foreach ($genres as $genre) {
                    $genreName = is_string($genre)
                        ? $genre
                        : (method_exists($genre, 'toString') ? $genre->toString() : (string)$genre);
                    if (!empty($genreName)) {
                        $genreCounts[$genreName] = ($genreCounts[$genreName] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($genreCounts);
        $topGenres = array_slice($genreCounts, 0, 10, true);

        return [
            'topGenres'           => $topGenres,
            'totalGenres'         => count($genreCounts),
            'albumsWithGenres'    => $totalWithGenres,
            'albumsWithoutGenres' => count($albums) - $totalWithGenres,
        ];
    }

    private function calculateAlbumStatusStats(array $albums): array
    {
        $statusCounts = [];
        foreach ($albums as $album) {
            foreach ($album->getUserStatuses() as $status) {
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
        }
        return $statusCounts;
    }

    private function calculateAlbumRatingStats(array $albums): array
    {
        $ratingCounts = [];
        $totalRated   = 0;
        $sumRatings   = 0;

        foreach ($albums as $album) {
            $rating = $album->getUserRating();
            if ($rating !== null) {
                $ratingValue = $rating->toFloat();
                if ($ratingValue > 0) {
                    $rounded = round($ratingValue * 2) / 2;
                    if ($rounded >= 1 && $rounded <= 5) {
                        $key = (string)$rounded;
                        $ratingCounts[$key] = ($ratingCounts[$key] ?? 0) + 1;
                        $totalRated++;
                        $sumRatings += $ratingValue;
                    }
                }
            }
        }

        ksort($ratingCounts);

        return [
            'distribution'  => $ratingCounts,
            'totalRated'    => $totalRated,
            'averageRating' => $totalRated > 0 ? round($sumRatings / $totalRated, 1) : 0,
        ];
    }

    private function calculateAlbumMonthlyStats(array $albums): array
    {
        $monthlyCounts = [];
        foreach ($albums as $album) {
            $ts = $album->getAddedTimestamp();
            if ($ts) {
                $month = date('Y-m', $ts->toUnixTimestamp());
                $monthlyCounts[$month] = ($monthlyCounts[$month] ?? 0) + 1;
            }
        }

        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthlyData[$month] = $monthlyCounts[$month] ?? 0;
        }

        return $monthlyData;
    }

    private function calculateAlbumTypeStats(array $albums): array
    {
        $typeCounts = [];
        foreach ($albums as $album) {
            $type = $album->getAlbumType() ?? 'unknown';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }
        arsort($typeCounts);
        return $typeCounts;
    }

    private function calculateAlbumListenStats(array $albums): array
    {
        $totalListens      = 0;
        $albumsWithListens = 0;

        foreach ($albums as $album) {
            $count = $album->getListenCount();
            if ($count !== null && $count > 0) {
                $totalListens += $count;
                $albumsWithListens++;
            }
        }

        return [
            'totalListens'      => $totalListens,
            'albumsWithListens' => $albumsWithListens,
            'averageListens'    => $albumsWithListens > 0 ? round($totalListens / $albumsWithListens, 1) : 0,
        ];
    }

    // ============================================================================
    // VIDEO STATS
    // ============================================================================

    public function getVideoStats(int $userId): array
    {
        try {
            $videos = $this->userVideoRepository->findByUser($userId);

            $stats = [
                'totalVideos'   => count($videos),
                'statusStats'   => $this->calculateVideoStatusStats($videos),
                'ratingStats'   => $this->calculateVideoRatingStats($videos),
                'categoryStats' => $this->calculateVideoCategoryStats($videos),
                'monthlyStats'  => $this->calculateVideoMonthlyStats($videos),
                'channelStats'  => $this->calculateVideoChannelStats($videos),
                'watchStats'    => $this->calculateVideoWatchStats($videos),
            ];

            return $this->successResponse('Video statistics retrieved successfully', $stats);
        } catch (\Exception $e) {
            error_log('[StatsController] Error getting video stats: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve video statistics: ' . $e->getMessage(), 500);
        }
    }

    private function calculateVideoStatusStats(array $videos): array
    {
        $statusCounts = [];
        foreach ($videos as $video) {
            foreach ($video->getUserStatuses() as $status) {
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
        }
        return $statusCounts;
    }

    private function calculateVideoRatingStats(array $videos): array
    {
        $ratingCounts = [];
        $totalRated   = 0;
        $sumRatings   = 0;

        foreach ($videos as $video) {
            $rating = $video->getUserRating();
            if ($rating !== null) {
                $ratingValue = $rating->toFloat();
                if ($ratingValue > 0) {
                    $rounded = round($ratingValue * 2) / 2;
                    if ($rounded >= 1 && $rounded <= 5) {
                        $key = (string)$rounded;
                        $ratingCounts[$key] = ($ratingCounts[$key] ?? 0) + 1;
                        $totalRated++;
                        $sumRatings += $ratingValue;
                    }
                }
            }
        }

        ksort($ratingCounts);

        return [
            'distribution'  => $ratingCounts,
            'totalRated'    => $totalRated,
            'averageRating' => $totalRated > 0 ? round($sumRatings / $totalRated, 1) : 0,
        ];
    }

    private function calculateVideoCategoryStats(array $videos): array
    {
        $categoryCounts = [];
        $totalWithCategories = 0;

        foreach ($videos as $video) {
            $categories = $video->getCategories();
            if (!empty($categories) && is_array($categories)) {
                $totalWithCategories++;
                foreach ($categories as $category) {
                    $name = is_string($category) ? $category : ($category['name'] ?? (string)$category);
                    if (!empty($name)) {
                        $categoryCounts[$name] = ($categoryCounts[$name] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($categoryCounts);
        $topGenres = array_slice($categoryCounts, 0, 10, true);

        return [
            'topGenres'             => $topGenres,
            'totalCategories'       => count($categoryCounts),
            'videosWithCategories'  => $totalWithCategories,
            'videosWithoutCategories' => count($videos) - $totalWithCategories,
        ];
    }

    private function calculateVideoMonthlyStats(array $videos): array
    {
        $monthlyCounts = [];
        foreach ($videos as $video) {
            $ts = $video->getAddedTimestamp();
            if ($ts) {
                $month = date('Y-m', $ts->toUnixTimestamp());
                $monthlyCounts[$month] = ($monthlyCounts[$month] ?? 0) + 1;
            }
        }

        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthlyData[$month] = $monthlyCounts[$month] ?? 0;
        }

        return $monthlyData;
    }

    private function calculateVideoChannelStats(array $videos): array
    {
        $channelCounts = [];
        foreach ($videos as $video) {
            $channel = $video->getChannelName();
            if (!empty($channel)) {
                $channelCounts[$channel] = ($channelCounts[$channel] ?? 0) + 1;
            }
        }
        arsort($channelCounts);
        return array_slice($channelCounts, 0, 10, true);
    }

    private function calculateVideoWatchStats(array $videos): array
    {
        $totalWatches      = 0;
        $videosWithWatches = 0;

        foreach ($videos as $video) {
            $count = $video->getWatchCount();
            if ($count !== null && $count > 0) {
                $totalWatches += $count;
                $videosWithWatches++;
            }
        }

        return [
            'totalWatches'      => $totalWatches,
            'videosWithWatches' => $videosWithWatches,
            'averageWatches'    => $videosWithWatches > 0 ? round($totalWatches / $videosWithWatches, 1) : 0,
        ];
    }
}