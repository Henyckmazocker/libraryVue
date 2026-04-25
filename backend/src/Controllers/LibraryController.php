<?php
namespace App\Controllers;

use App\Domain\UseCases\GetLibraryUseCase;
use App\Domain\UseCases\GetOwnershipFormatsUseCase;
use App\Domain\DTO\Queries\GetOwnershipFormatsQuery;
use App\Domain\UseCases\Books\GetBooksUseCase;
use App\Domain\UseCases\Movies\GetMoviesUseCase;
use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\UseCases\Movies\AddMovieUseCase;
use App\Domain\UseCases\Movies\GetMovieAllowedStatusesUseCase;
use App\Domain\UseCases\Books\GetBookAllowedStatusesUseCase;
use App\Domain\Repository\Book\UserBookRepositoryInterface;
use App\Domain\Repository\Movie\UserMovieRepositoryInterface;
use App\Domain\Repository\Book\BookTagRepositoryInterface;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Domain\DTO\Queries\GetBooksByUserQuery;
use App\Domain\DTO\Queries\GetMoviesByUserQuery;
use App\Domain\Services\OpenLibraryService;

class LibraryController extends BaseController implements Contracts\LibraryControllerInterface
{
    private GetLibraryUseCase $getLibraryUseCase;
    private GetBooksUseCase $getBooksUseCase;
    private GetMoviesUseCase $getMoviesUseCase;
    private AddBookUseCase $addBookUseCase;
    private AddMovieUseCase $addMovieUseCase;
    private GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase;
    private GetBookAllowedStatusesUseCase $getBookAllowedStatusesUseCase;
    private UserBookRepositoryInterface $userBookRepository;
    private UserMovieRepositoryInterface $userMovieRepository;
    private BookTagRepositoryInterface $bookTagRepository;
    private OpenLibraryService $openLibraryService;
    private AuthMiddleware $authMiddleware;
    private GetOwnershipFormatsUseCase $getOwnershipFormatsUseCase;

    public function __construct(
        GetLibraryUseCase $getLibraryUseCase,
        GetBooksUseCase $getBooksUseCase,
        GetMoviesUseCase $getMoviesUseCase,
        AddBookUseCase $addBookUseCase,
        AddMovieUseCase $addMovieUseCase,
        GetMovieAllowedStatusesUseCase $getMovieAllowedStatusesUseCase,
        GetBookAllowedStatusesUseCase $getBookAllowedStatusesUseCase,
        UserBookRepositoryInterface $userBookRepository,
        UserMovieRepositoryInterface $userMovieRepository,
        BookTagRepositoryInterface $bookTagRepository,
        OpenLibraryService $openLibraryService,
        AuthMiddleware $authMiddleware,
        GetOwnershipFormatsUseCase $getOwnershipFormatsUseCase
    ) {
        $this->getLibraryUseCase = $getLibraryUseCase;
        $this->getBooksUseCase = $getBooksUseCase;
        $this->getMoviesUseCase = $getMoviesUseCase;
        $this->addBookUseCase = $addBookUseCase;
        $this->addMovieUseCase = $addMovieUseCase;
        $this->getMovieAllowedStatusesUseCase = $getMovieAllowedStatusesUseCase;
        $this->getBookAllowedStatusesUseCase = $getBookAllowedStatusesUseCase;
        $this->userBookRepository = $userBookRepository;
        $this->userMovieRepository = $userMovieRepository;
        $this->bookTagRepository = $bookTagRepository;
        $this->openLibraryService = $openLibraryService;
        $this->authMiddleware = $authMiddleware;
        $this->getOwnershipFormatsUseCase = $getOwnershipFormatsUseCase;
    }

    public function getLibraryItems(int $userId): array
    {
        // Get books and movies for this specific user
        $booksQuery = new GetBooksByUserQuery($userId);
        $books = $this->getBooksUseCase->execute($booksQuery);
        
        $moviesQuery = new GetMoviesByUserQuery($userId);
        $movies = $this->getMoviesUseCase->execute($moviesQuery);
        
        return $this->successResponse('Library items (books and movies) retrieved.', [
            'books' => $books,
            'movies' => $movies
        ]);
    }

    public function saveLibrary(int $userId): array
    {
        // Obtiene la biblioteca actual del usuario específico y la guarda en my_library.json
        $booksQuery = new GetBooksByUserQuery($userId);
        $books = $this->getBooksUseCase->execute($booksQuery);
        $libraryArray = array_map(fn($book) => $book->toArray(), $books);
        $libraryFilePath = __DIR__ . '/../../public/my_library.json';
        $json = json_encode($libraryArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($libraryFilePath, $json) !== false) {
            return $this->successResponse('Library saved successfully.');
        } else {
            return $this->errorResponse('Failed to save library to file.', 500);
        }
    }

    public function importData(array $processedData, int $userId): array
    {
        if (empty($processedData)) {
            throw new \InvalidArgumentException('ProcessedData must be an array.');
        }

        $importedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($processedData as $index => $itemData) {
            try {
                // Determinar el tipo de elemento basado en los campos presentes
                $isMovie = isset($itemData['id']) && !isset($itemData['isbn']);
                $isBook = isset($itemData['isbn']);

                if ($isMovie) {
                    // Procesar como película
                    // Check if user already has this movie using hasMovie()
                    if ($this->userMovieRepository->hasMovie($userId, $itemData['id'])) {
                        $skippedCount++;
                        continue; // Skip movies user already has
                    }

                    // Prepare movie data - AddMovieCommand fromArray handles userId internally
                    $movieDataForUseCase = [
                        'id' => $itemData['id'],
                        'title' => $itemData['title'],
                        'originalTitle' => $itemData['originalTitle'] ?? $itemData['title'],
                        'director' => $itemData['director'] ?? null,
                        'coverUrl' => $itemData['coverUrl'] ?? null,
                        'rating' => $itemData['rating'] ?? null,
                        'user_rating' => $itemData['rating'] ?? null, // Use rating as user_rating for imports
                        'userStatuses' => $itemData['userStatuses'] ?? ['in-watchlist'],
                        'description' => $itemData['description'] ?? $itemData['plot'] ?? null,
                        'genres' => $itemData['genres'] ?? null
                    ];

                    $movieCommand = \App\Domain\DTO\Commands\AddMovieCommand::fromArray($movieDataForUseCase, $userId);
                    $this->addMovieUseCase->execute($movieCommand);
                    $importedCount++;

                } else if ($isBook) {
                    // Procesar como libro
                    // Check if user already has this book using hasBook()
                    if ($this->userBookRepository->hasBook($userId, $itemData['isbn'])) {
                        $skippedCount++;
                        continue; // Skip books user already has
                    }

                    // Enrich book data with OpenLibrary API before processing
                    $enrichedItemData = $this->enrichBookDataWithOpenLibrary($itemData);

                    // Prepare book data - AddBookCommand fromArray handles userId internally
                    $bookDataForUseCase = [
                        'isbn' => $enrichedItemData['isbn'],
                        'title' => $enrichedItemData['title'],
                        'author' => $enrichedItemData['author'] ?? null,
                        'publisher' => $enrichedItemData['publisher'] ?? null,
                        'publicationDate' => $enrichedItemData['publicationDate'] ?? $enrichedItemData['publicationYear'] ?? null,
                        'coverUrl' => $enrichedItemData['coverUrl'] ?? null,
                        'rating' => $itemData['rating'] ?? null, // Preserve user's rating from import
                        'user_rating' => $itemData['rating'] ?? null, // Use rating as user_rating for imports
                        'pages' => $enrichedItemData['pages'] ?? null,
                        'description' => $enrichedItemData['description'] ?? null,
                        'userStatuses' => $itemData['userStatuses'] ?? ['owned'], // Preserve user's statuses from import
                        'genres' => $enrichedItemData['genres'] ?? [],
                        'language' => $enrichedItemData['language'] ?? null
                    ];

                    $bookCommand = \App\Domain\DTO\Commands\AddBookCommand::fromArray($bookDataForUseCase, $userId);
                    $this->addBookUseCase->execute($bookCommand);
                    $importedCount++;

                    // Process custom tags if present (from Goodreads bookshelves)
                    if (isset($itemData['customTags']) && is_array($itemData['customTags']) && !empty($itemData['customTags'])) {
                        try {
                            foreach ($itemData['customTags'] as $tagName) {
                                if (empty($tagName)) continue;

                                // Create tag if it doesn't exist (returns existing ID if duplicate)
                                $tagId = $this->bookTagRepository->create($userId, $tagName);

                                // Assign tag to the book
                                $this->bookTagRepository->assign($userId, $itemData['isbn'], $tagId);
                            }
                        } catch (\Exception $e) {
                            // Log error but don't fail the entire import
                            // Silently continue - tags are optional
                        }
                    }

                } else {
                    $errors[] = "Error en elemento {$index}: No se pudo determinar si es libro o película";
                }

            } catch (\Exception $e) {
                $itemId = $itemData['id'] ?? $itemData['isbn'] ?? 'unknown';
                $errors[] = "Error en elemento {$index} (ID: {$itemId}): " . $e->getMessage();
            }
        }

        return $this->successResponse(
            "Importación completada. Elementos importados: {$importedCount}, Omitidos: {$skippedCount}",
            [
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'total' => count($processedData),
                'errors' => $errors
            ]
        );
    }

    public function ping(): array
    {
        return $this->successResponse('pong', null);
    }

    public function getOwnershipFormats(array $inputData, int $userId): array
    {
        try {
            $query = GetOwnershipFormatsQuery::fromArray($inputData);
            $formats = $this->getOwnershipFormatsUseCase->execute($query);
            return $this->successResponse('Ownership formats retrieved.', ['formats' => $formats]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->errorResponse('Could not retrieve ownership formats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Enrich book data with OpenLibrary API information
     *
     * @param array $bookData Original book data from import
     * @return array Enriched book data (or original if enrichment fails)
     */
    private function enrichBookDataWithOpenLibrary(array $bookData): array
    {
        // Only enrich if we have an ISBN
        if (empty($bookData['isbn'])) {
            return $bookData;
        }

        try {
            // Fetch edition data from OpenLibrary
            $olEdition = $this->openLibraryService->getEditionByISBN($bookData['isbn']);

            if ($olEdition === null) {
                return $bookData; // No data found, return original
            }

            // Enrich with OpenLibrary data (prefer OL data over imported data when available)
            $enrichedData = $bookData;

            // Title and subtitle
            if (!empty($olEdition['title'])) {
                $enrichedData['title'] = $olEdition['title'];
            }
            if (!empty($olEdition['subtitle'])) {
                $enrichedData['subtitle'] = $olEdition['subtitle'];
            }

            // Publisher (take first from array)
            if (!empty($olEdition['publishers']) && is_array($olEdition['publishers'])) {
                $enrichedData['publisher'] = $olEdition['publishers'][0];
            }

            // Publication date/year
            if (!empty($olEdition['publish_year'])) {
                $enrichedData['publicationDate'] = $olEdition['publish_year'];
                $enrichedData['publicationYear'] = $olEdition['publish_year'];
            } elseif (!empty($olEdition['publish_date'])) {
                $enrichedData['publicationDate'] = $olEdition['publish_date'];
            }

            // Pages
            if (!empty($olEdition['number_of_pages'])) {
                $enrichedData['pages'] = $olEdition['number_of_pages'];
            }

            // Cover URL (prefer OpenLibrary cover)
            if (!empty($olEdition['cover_url'])) {
                $enrichedData['coverUrl'] = $olEdition['cover_url'];
            }

            // Physical format
            if (!empty($olEdition['physical_format'])) {
                $enrichedData['format'] = $olEdition['physical_format'];
            }

            // Languages
            if (!empty($olEdition['languages']) && is_array($olEdition['languages'])) {
                // Extract language codes
                $languages = array_map(function($lang) {
                    if (is_array($lang) && isset($lang['key'])) {
                        return basename($lang['key']);
                    }
                    return $lang;
                }, $olEdition['languages']);

                if (!empty($languages)) {
                    $enrichedData['language'] = $languages[0]; // Use first language
                }
            }

            return $enrichedData;

        } catch (\Exception $e) {
            // If enrichment fails, return original data
            // The AddBookUseCase will handle it with fallback to synthetic work/edition
            return $bookData;
        }
    }

}
