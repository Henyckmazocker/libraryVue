<?php
namespace App\Controllers\Contracts;

interface MovieControllerInterface
{
    public function addMovie(array $movieData, int $userId);
    public function deleteMovie(string $movieId, int $userId);
    public function updateMovieRating(string $movieId, ?float $rating, int $userId);
    public function updateMovieUserStatuses(string $movieId, array $statuses, int $userId);
    public function getMovieAllowedStatuses();
    public function getMovies(int $userId);
}
