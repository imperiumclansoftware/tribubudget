<?php

namespace App\Tests\Api;

use App\Setup\SetupCode;
use App\Tests\ApiTestCase;

final class SetupTest extends ApiTestCase
{
    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        $setupCode = static::getContainer()->get(SetupCode::class);
        $setupCode->clear();
        $this->code = $setupCode->current();
    }

    public function testTheFirstParentCreatesTheFamilyReadyToUse(): void
    {
        self::assertFalse($this->api('GET', '/api/setup', null)['installed']);

        $response = $this->install();
        self::assertResponseStatusCodeSame(201);
        $me = $this->api('GET', '/api/me', $response['token']);
        self::assertSame('parent', $me['role']);
        self::assertSame('Famille Martin', $me['family']['name']);
        self::assertSame(['Compte courant'], array_column($this->api('GET', '/api/accounts', $response['token']), 'name'));
        self::assertContains('Argent de poche', array_column($this->api('GET', '/api/categories', $response['token']), 'name'));

        self::assertTrue($this->api('GET', '/api/setup', null)['installed']);
    }

    public function testTheCodeIsAskedAndAnyCaseOrDashWillDo(): void
    {
        $this->api('POST', '/api/setup/verify', null, ['code' => 'ABCD-EFGH']);
        self::assertResponseStatusCodeSame(403);
        $this->install(['code' => 'WRONGCOD']);
        self::assertResponseStatusCodeSame(403);

        $this->api('POST', '/api/setup/verify', null, ['code' => strtolower(str_replace('-', ' ', $this->code))]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testThereIsOnlyOneInstallation(): void
    {
        $this->install();
        $this->install(['username' => 'intrus']);
        self::assertResponseStatusCodeSame(409);
        $this->api('POST', '/api/setup/verify', null, ['code' => $this->code]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testNobodyElseSignsUp(): void
    {
        $this->install();
        $this->api('POST', '/api/register', null, ['familyName' => 'Autre', 'displayName' => 'X', 'username' => 'x', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheFirstParentNeedsARealPassword(): void
    {
        $response = $this->install(['password' => '1234']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Le mot de passe d’un parent compte au moins 8 caractères.', $response['detail']);
        self::assertFalse($this->api('GET', '/api/setup', null)['installed']);
    }

    /** @param array<string, string> $changes */
    private function install(array $changes = []): ?array
    {
        return $this->api('POST', '/api/setup', null, $changes + [
            'code' => $this->code,
            'familyName' => 'Famille Martin',
            'displayName' => 'Claire',
            'username' => 'claire',
            'password' => 'motdepasse',
        ]);
    }
}
