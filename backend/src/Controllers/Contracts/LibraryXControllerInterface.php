<?php

namespace App\Controllers\Contracts;

interface LibraryXControllerInterface
{
    public function getUrls(array $user): array;
    public function updateUrls(array $inputData, array $user): array;
}
