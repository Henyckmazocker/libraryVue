<?php
namespace App\Controllers\Contracts;

use App\Domain\DTO\Commands\AddBookCommand;
use App\Domain\DTO\Commands\DeleteBookCommand;
use App\Domain\DTO\Commands\UpdateBookRatingCommand;
use App\Domain\DTO\Commands\UpdateBookStatusesCommand;
use App\Domain\DTO\Commands\EditUserBookCommand;
use App\Domain\DTO\Queries\GetBooksByUserQuery;

interface BookControllerInterface
{
    public function addBook(AddBookCommand $command): array;
    public function deleteBook(DeleteBookCommand $command): array;
    public function updateBookRating(UpdateBookRatingCommand $command): array;
    public function updateBookUserStatuses(UpdateBookStatusesCommand $command): array;
    public function getBookAllowedStatuses(): array;
    public function getBooks(GetBooksByUserQuery $query): array;
    public function getAllBooks(): array;
    public function editUserBook(EditUserBookCommand $command): array;
}
