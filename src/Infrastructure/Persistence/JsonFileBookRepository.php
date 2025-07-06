<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Domain\Model\Book;
use App\Application\Domain\Repository\BookRepositoryInterface;
use RuntimeException;

class JsonFileBookRepository implements BookRepositoryInterface
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        if (!file_exists($this->filePath)) {
            // Attempt to create the file if it doesn't exist with an empty array
            if (file_put_contents($this->filePath, json_encode([])) === false) {
                throw new RuntimeException("Failed to create library file: {$this->filePath}");
            }
        }
    }

    /**
     * @return Book[]
     */
    public function findAll(): array
    {
        $booksData = $this->readData();
        $books = [];
        foreach ($booksData as $bookData) {
            try {
                $books[] = Book::fromArray($bookData);
            } catch (\InvalidArgumentException $e) {
                // Log error or handle corrupted book data entry
                // For now, we'll skip corrupted entries
                error_log("Skipping corrupted book data: " . $e->getMessage() . " Data: " . json_encode($bookData));
                continue;
            }
        }
        return $books;
    }

    public function findByIsbn(string $isbn): ?Book
    {
        $books = $this->findAll();
        foreach ($books as $book) {
            if ($book->getIsbn() === $isbn) {
                return $book;
            }
        }
        return null;
    }

    public function save(Book $book): void
    {
        $booksData = $this->readData();
        $found = false;
        foreach ($booksData as $key => $bookData) {
            if (isset($bookData['isbn']) && $bookData['isbn'] === $book->getIsbn()) {
                $booksData[$key] = $book->toArray();
                $found = true;
                break;
            }
        }

        if (!$found) {
            $booksData[] = $book->toArray();
        }

        $this->writeData($booksData);
    }

    public function deleteByIsbn(string $isbn): bool
    {
        $booksData = $this->readData();
        $initialCount = count($booksData);
        $updatedBooksData = array_filter($booksData, function ($bookData) use ($isbn) {
            return !(isset($bookData['isbn']) && $bookData['isbn'] === $isbn);
        });

        if (count($updatedBooksData) < $initialCount) {
            // Re-index array to prevent it from becoming an object if keys are not sequential
            $this->writeData(array_values($updatedBooksData));
            return true;
        }
        return false;
    }

    private function readData(): array
    {
        if (!is_readable($this->filePath)) {
            throw new RuntimeException("Library file is not readable: {$this->filePath}");
        }
        $json = file_get_contents($this->filePath);
        if ($json === false) {
            throw new RuntimeException("Failed to read library file: {$this->filePath}");
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Error decoding JSON from library file: {$this->filePath}. Error: " . json_last_error_msg());
        }
        return is_array($data) ? $data : []; // Ensure it's an array, even if file was empty or contained non-array JSON
    }

    private function writeData(array $data): void
    {
        // Ensure array is numerically indexed if it's a list of books
        $jsonData = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonData === false) {
            throw new RuntimeException("Failed to encode library data to JSON. Error: " . json_last_error_msg());
        }
        if (file_put_contents($this->filePath, $jsonData) === false) {
            throw new RuntimeException("Failed to write to library file: {$this->filePath}");
        }
    }
} 