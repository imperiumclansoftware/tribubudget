<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class HealthTest extends ApiTestCase
{
    public function testTheHealthCheckNeedsNoAccount(): void
    {
        self::assertSame('ok', $this->api('GET', '/api/health', null)['status']);
    }
}
