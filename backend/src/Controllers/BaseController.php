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
     * Validate authentication state and return user data
     */
    protected function validateAuth(): array
    {
        // This method would typically check session or token
        // For now, throwing an exception to indicate it needs implementation
        throw new InvalidArgumentException('Authentication validation not implemented in controller');
    }

    /**
     * Get the authenticated user ID after validation
     */
    protected function getAuthenticatedUserId(): int
    {
        $authResult = $this->validateAuth();
        if ($authResult['status'] === 'error') {
            throw new InvalidArgumentException('User not authenticated');
        }
        return $authResult['user']['id'];
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

    /**
     * Handle HTTP request - to be implemented by child controllers
     */
    public function handleRequest(string $method, string $path): void
    {
        // This method should be overridden by each controller
        // For now, return a not implemented response
        http_response_code(501);
        echo json_encode([
            'error' => true,
            'message' => 'Method not implemented in ' . static::class
        ]);
    }
}
