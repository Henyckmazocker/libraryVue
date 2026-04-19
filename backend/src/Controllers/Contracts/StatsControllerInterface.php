<?php

namespace App\Controllers\Contracts;

interface StatsControllerInterface
{
    public function getBookStats(int $userId): array;
    public function getMovieStats(int $userId): array;
    public function getGameStats(int $userId): array;
}
