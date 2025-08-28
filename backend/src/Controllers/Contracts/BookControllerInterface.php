<?php
namespace App\Controllers\Contracts;

interface BookControllerInterface
{
    public function addBook(array $bookData, int $userId);
    public function deleteBook(string $isbn, int $userId);
    public function updateBookRating(string $isbn, ?float $rating, int $userId);
    public function updateBookUserStatuses(string $isbn, array $statuses, int $userId);
    public function getBookAllowedStatuses();
    public function getBooks(int $userId);
    public function getAllBooks();
    public function editUserBook(string $isbn, int $userId, array $data, array $tags = [], array $notes = []);
}
