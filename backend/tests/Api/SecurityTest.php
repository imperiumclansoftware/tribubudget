<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

/** Signing in by session cookie, CSRF, password rules and security headers (docs/adr). */
final class SecurityTest extends ApiTestCase
{
    public function testSigningInAnswersWithTheMemberAndASessionCookieButNoToken(): void
    {
        $this->registerFamily();

        $me = $this->api('POST', '/api/login', null, ['username' => 'maman', 'password' => 'motdepasse']);

        self::assertResponseIsSuccessful();
        self::assertSame('maman', $me['username']);
        self::assertArrayNotHasKey('token', $me);
        $cookie = $this->client->getResponse()->headers->getCookies()[0] ?? null;
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
    }

    public function testAWrongPasswordAndAnUnknownMemberGetTheSameAnswer(): void
    {
        $this->registerFamily();

        $wrongPassword = $this->api('POST', '/api/login', null, ['username' => 'maman', 'password' => 'pas-le-bon']);
        self::assertResponseStatusCodeSame(401);
        $unknown = $this->api('POST', '/api/login', null, ['username' => 'personne', 'password' => 'pas-le-bon']);
        self::assertResponseStatusCodeSame(401);
        self::assertSame($wrongPassword, $unknown);
        self::assertSame('Identifiant ou code incorrect.', $unknown['detail']);
    }

    public function testTooManyFailedSignInsAreSlowedDown(): void
    {
        $this->registerFamily();
        for ($i = 0; $i < 5; ++$i) {
            $this->api('POST', '/api/login', null, ['username' => 'maman', 'password' => 'pas-le-bon']);
        }

        $response = $this->api('POST', '/api/login', null, ['username' => 'maman', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(429);
        self::assertStringStartsWith('Trop de tentatives', $response['detail']);
    }

    public function testChangesWithoutTheCsrfTokenAreRefused(): void
    {
        $parent = $this->registerFamily();

        $this->api('POST', '/api/accounts', $parent, ['name' => 'Livret', 'type' => 'savings', 'initialBalance' => 0], csrf: false);
        self::assertResponseStatusCodeSame(403);

        // Signing in too: another site cannot sign the browser in to its own account
        $this->api('POST', '/api/login', null, ['username' => 'maman', 'password' => 'motdepasse'], csrf: false);
        self::assertResponseStatusCodeSame(403);

        // Reading needs no CSRF token
        $this->api('GET', '/api/accounts', $parent, csrf: false);
        self::assertResponseIsSuccessful();
    }

    public function testACsrfHeaderThatDoesNotMatchTheCookieIsRefused(): void
    {
        $parent = $this->registerFamily();
        $this->client->getCookieJar()->clear();

        $this->client->request('POST', '/api/logout', server: [
            'HTTP_COOKIE' => $this->sessionCookie().'='.$parent.'; XSRF-TOKEN=celui-du-cookie',
            'HTTP_X_XSRF_TOKEN' => 'un-autre',
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testSigningOutEndsTheSession(): void
    {
        $parent = $this->registerFamily();

        $this->api('POST', '/api/logout', $parent);
        self::assertResponseStatusCodeSame(204);

        $this->api('GET', '/api/me', $parent);
        self::assertResponseStatusCodeSame(401);
    }

    public function testAParentOrTeenPasswordMustBeLongAndHardToGuess(): void
    {
        $parent = $this->registerFamily();
        $member = ['displayName' => 'Hugo', 'username' => 'hugo', 'role' => 'teen'];

        $tooShort = $this->api('POST', '/api/members', $parent, $member + ['password' => 'Court-2026']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Le mot de passe d’un adolescent compte au moins 12 caractères.', $tooShort['detail']);

        $weak = $this->api('POST', '/api/members', $parent, $member + ['password' => 'aaaaaaaaaaaa']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('trop facile à deviner', $weak['detail']);

        $this->api('POST', '/api/members', $parent, $member + ['password' => 'Velo-rouge-du-dimanche']);
        self::assertResponseStatusCodeSame(201);
    }

    public function testAChildKeepsSigningInWithAPin(): void
    {
        [, $child] = $this->addChild($this->registerFamily(), 'lea', '4821');

        self::assertSame('child', $this->api('GET', '/api/me', $child)['role']);
    }

    public function testANewPasswordSignsTheOtherSessionsOut(): void
    {
        $parent = $this->registerFamily();
        [$hugoId, $hugo] = $this->addTeen($parent);

        $this->api('PUT', "/api/members/$hugoId/password", $parent, ['password' => 'Nouveau-velo-bleu-2026']);
        self::assertResponseStatusCodeSame(204);

        $this->api('GET', '/api/me', $hugo);
        self::assertResponseStatusCodeSame(401);
        $this->login('hugo', 'Nouveau-velo-bleu-2026');
    }

    public function testTheApiSendsTheSecurityHeaders(): void
    {
        $this->api('GET', '/api/health', null);

        $headers = $this->client->getResponse()->headers;
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        self::assertStringContainsString("default-src 'none'", (string) $headers->get('Content-Security-Policy'));
        self::assertSame('same-origin', $headers->get('Cross-Origin-Opener-Policy'));
    }
}
