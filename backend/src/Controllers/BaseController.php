<?php
declare(strict_types=1);

namespace App\Controllers;

use InvalidArgumentException;

abstract class BaseController
{
    /**
     * Create a success response
     */
    protected function successResponse(string $message, array $data = null, int $httpCode = 200): array
    {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'http_code' => $httpCode
        ];
    }

    /**
     * Create an error response
     */
    protected function errorResponse(string $message, int $httpCode = 400): array
    {
        return [
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'http_code' => $httpCode
        ];
    }

    /**
     * Standard response for a failed external API call (Spotify, OMDb, IGDB, ...).
     *
     * Returns a clean 503 with a generic message instead of leaking the upstream
     * error to the client. The real cause is already logged by the service.
     */
    protected function externalServiceError(string $service): array
    {
        return $this->errorResponse(
            "The {$service} service is temporarily unavailable. Please try again later.",
            503
        );
    }

    /**
     * Validate that required fields are present in input data
     */
    protected function validateRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                throw new InvalidArgumentException("Field '{$field}' is required.");
            }
        }
    }

    /**
     * Extract numeric rating from input, handling null and zero values
     */
    protected function extractNumericRating($rating): ?float
    {
        if ($rating === null) {
            return null;
        }
        
        if (is_numeric($rating)) {
            $numericRating = (float)$rating;
            return $numericRating == 0 ? null : $numericRating; // Treat 0 as unrate
        }
        
        throw new InvalidArgumentException('Rating must be a number or null.');
    }

    /**
     * Validate that input is a non-empty array
     */
    protected function validateNonEmptyArray($data, string $fieldName): array
    {
        if (!is_array($data) || empty($data)) {
            throw new InvalidArgumentException("{$fieldName} must be a non-empty array.");
        }
        return $data;
    }
}
