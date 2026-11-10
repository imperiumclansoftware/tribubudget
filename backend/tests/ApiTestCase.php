<?php

namespace App\Tests;

use App\Entity\Family;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\EventSubscriber\CsrfDoubleSubmitSubscriber;
use App\Money\FamilySetup;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests of the API: a fresh database for each test, and helpers to create a family
 * and talk to the API as one of its members.
 *
 * A "token" here is the id of the member's session: login() returns it, api() sends it as the
 * session cookie, with the CSRF cookie and header, so that one test can talk as several members.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const XSRF = 'test-xsrf-token';

    private static bool $schemaCreated = false;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Failed sign-ins of a previous test must not slow this one down (login_throttling)
        static::getContainer()->get('cache.rate_limiter')->clear();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        if (!self::$schemaCreated) {
            $schema = new SchemaTool($em);
            $metadata = $em->getMetadataFactory()->getAllMetadata();
            $schema->dropSchema($metadata);
            $schema->createSchema($metadata);
            self::$schemaCreated = true;
        }
        $platform = $em->getConnection()->getDatabasePlatform();
        $tables = array_map(static fn ($m) => $platform->quoteSingleIdentifier($m->getTableName()), $em->getMetadataFactory()->getAllMetadata());
        $em->getConnection()->executeStatement('TRUNCATE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');
    }

    /**
     * Creates a family with its first parent, as the installation does (the API itself creates
     * only one family per server); returns the token of the parent.
     */
    protected function registerFamily(string $username = 'maman', string $familyName = 'Famille Martin'): string
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $family = new Family($familyName);
        $parent = new User($family, $username, ucfirst($username), FamilyRole::Parent);
        $parent->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($parent, 'motdepasse'));
        $em->persist($family);
        $em->persist($parent);
        $container->get(FamilySetup::class)->populate($family);
        $em->flush();

        return $this->login($username, 'motdepasse');
    }

    /** Adds a child to the parent's family; returns [child id, child token]. */
    protected function addChild(string $parentToken, string $username = 'lea', string $pin = '1234'): array
    {
        $child = $this->api('POST', '/api/members', $parentToken, [
            'displayName' => ucfirst($username),
            'username' => $username,
            'role' => 'child',
            'password' => $pin,
        ]);
        self::assertResponseStatusCodeSame(201);

        return [$child['id'], $this->login($username, $pin)];
    }

    /** Adds a teenager to the parent's family; returns [teen id, teen token]. */
    protected function addTeen(string $parentToken, string $username = 'hugo', string $password = 'Velo-rouge-du-dimanche'): array
    {
        $teen = $this->api('POST', '/api/members', $parentToken, [
            'displayName' => ucfirst($username),
            'username' => $username,
            'role' => 'teen',
            'password' => $password,
        ]);
        self::assertResponseStatusCodeSame(201);

        return [$teen['id'], $this->login($username, $password)];
    }

    /** Signs in; returns the id of the new session. */
    protected function login(string $username, string $password): string
    {
        $this->api('POST', '/api/login', null, ['username' => $username, 'password' => $password]);
        self::assertResponseIsSuccessful();
        $session = $this->client->getCookieJar()->get($this->sessionCookie());
        self::assertNotNull($session, 'Signing in must set the session cookie.');

        return $session->getValue();
    }

    /**
     * @param string|null $token session id from login(), null for an anonymous request
     *
     * @return array<mixed>|null decoded JSON response
     */
    protected function api(string $method, string $uri, ?string $token, ?array $body = null, bool $csrf = true): ?array
    {
        $jar = $this->client->getCookieJar();
        $jar->clear();
        if (null !== $token) {
            $jar->set(new Cookie($this->sessionCookie(), $token));
        }
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($csrf) {
            $jar->set(new Cookie(CsrfDoubleSubmitSubscriber::COOKIE, self::XSRF));
            $server['HTTP_X_XSRF_TOKEN'] = self::XSRF;
        }
        $this->client->request($method, $uri, server: $server, content: null === $body ? null : json_encode($body));
        $content = $this->client->getResponse()->getContent();

        return '' === $content ? null : json_decode($content, true);
    }

    /** Name of the session cookie (TRIBUBUDGET_SID, but the test session storage has its own) */
    protected function sessionCookie(): string
    {
        return static::getContainer()->get('session.factory')->createSession()->getName();
    }

    protected function firstAccountId(string $parentToken): int
    {
        return $this->api('GET', '/api/accounts', $parentToken)[0]['id'];
    }
}
