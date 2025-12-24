<?php
namespace App\Controllers\Contracts;

interface LibraryControllerInterface
{
    public function getLibraryItems(int $userId): array;
    public function saveLibrary(int $userId): array;
    public function importData(array $processedData, int $userId): array;
    public function ping();
}
