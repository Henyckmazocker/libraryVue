<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BaseController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BaseControllerTest extends TestCase
{
    /**
     * BaseController is abstract; expose its protected helpers via an anonymous
     * subclass so they can be exercised directly.
     */
    private function controller(): object
    {
        return new class extends BaseController {
            public function externalServiceErrorPublic(string $service): array
            {
                return $this->externalServiceError($service);
            }
        };
    }

    #[Test]
    public function external_service_error_returns_a_clean_503(): void
    {
        $response = $this->controller()->externalServiceErrorPublic('Spotify');

        $this->assertSame('error', $response['status']);
        $this->assertSame(503, $response['http_code']);
        $this->assertNull($response['data']);
        $this->assertStringContainsString('Spotify', $response['message']);
    }

    #[Test]
    public function external_service_error_does_not_leak_upstream_detail(): void
    {
        $response = $this->controller()->externalServiceErrorPublic('OMDb');

        // The message must be generic and user-facing, never the upstream error
        // (e.g. "Invalid client secret", "Invalid API key").
        $this->assertStringContainsString('temporarily unavailable', $response['message']);
        $this->assertStringNotContainsStringIgnoringCase('invalid', $response['message']);
        $this->assertStringNotContainsStringIgnoringCase('secret', $response['message']);
    }
}
