<?php
namespace App\Controllers\Contracts;

use App\Domain\DTO\Commands\AddMovieCommand;
use App\Domain\DTO\Commands\DeleteMovieCommand;
use App\Domain\DTO\Commands\UpdateMovieRatingCommand;
use App\Domain\DTO\Commands\UpdateMovieStatusesCommand;
use App\Domain\DTO\Commands\EditUserMovieCommand;
use App\Domain\DTO\Queries\GetMoviesByUserQuery;

interface MovieControllerInterface
{
    public function addMovie(AddMovieCommand $command): array;
    public function deleteMovie(DeleteMovieCommand $command): array;
    public function updateMovieRating(UpdateMovieRatingCommand $command): array;
    public function updateMovieUserStatuses(UpdateMovieStatusesCommand $command): array;
    public function getMovieAllowedStatuses(): array;
    public function getMovies(GetMoviesByUserQuery $query): array;
    public function editUserMovie(EditUserMovieCommand $command): array;
}
