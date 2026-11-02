<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Error messages as the Docker image sends them: without debug, where Symfony replaces the message
 * of an HTTP exception by its status text.
 */
final class ApiErrorTest extends ApiTestCase
{
    private static bool $containerRemoved = false;

    protected function setUp(): void
    {
        parent::setUp();
        // Without debug, the kernel never checks its compiled container (a file of its own, next to the
        // debug one): built again once per run, so that it never tests yesterday's code
        if (!self::$containerRemoved) {
            (new Filesystem())->remove(glob(static::getContainer()->getParameter('kernel.cache_dir').'/App_KernelTestContainer*'));
            self::$containerRemoved = true;
        }
        self::ensureKernelShutdown();
        $this->client = static::createClient(['debug' => false]);
        // This kernel has its own cache pools: the attempts of the previous tests are still counted
        static::getContainer()->get('cache.app')->clear();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testKeepsTheMessagesOfTheAccessRules(): void
    {
        [, $child] = $this->addChild($this->registerFamily());

        $error = $this->api('GET', '/api/accounts', $child);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('Seuls les parents ont accès aux comptes.', $error['detail']);
    }

    public function testHidesTheMessagesOfTheFramework(): void
    {
        $error = $this->api('GET', '/api/nothing-here', $this->registerFamily());

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Not Found', $error['detail']);
    }

    public function testKeepsTheViolationsOfTheValidation(): void
    {
        $error = $this->api('POST', '/api/accounts', $this->registerFamily(), []);

        self::assertResponseStatusCodeSame(422);
        self::assertNotEmpty($error['violations']);
    }
}
