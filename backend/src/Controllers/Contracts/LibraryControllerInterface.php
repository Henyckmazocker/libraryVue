<?php
namespace App\Controllers\Contracts;

interface LibraryControllerInterface
{
    public function getLibraryItems(int $userId);
    public function saveLibrary(int $userId);
    public function importData(array $processedData, int $userId);
    public function ping();
}
