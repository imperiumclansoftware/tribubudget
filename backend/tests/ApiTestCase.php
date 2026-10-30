<?php

namespace App\Tests;

use App\Entity\Family;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Money\FamilySetup;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests of the API: a fresh database for each test, and helpers to create a family
 * and talk to the API as one of its members.
 */
abstract class ApiTestCase extends WebTestCase
{
    private static bool $schemaCreated = false;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
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
    protected function addTeen(string $parentToken, string $username = 'hugo', string $password = 'secret-hugo'): array
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

    protected function login(string $username, string $password): string
    {
        $response = $this->api('POST', '/api/login', null, ['username' => $username, 'password' => $password]);
        self::assertResponseIsSuccessful();

        return $response['token'];
    }

    /** @return array<mixed>|null decoded JSON response */
    protected function api(string $method, string $uri, ?string $token, ?array $body = null): ?array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, $uri, server: $server, content: null === $body ? null : json_encode($body));
        $content = $this->client->getResponse()->getContent();

        return '' === $content ? null : json_decode($content, true);
    }

    protected function firstAccountId(string $parentToken): int
    {
        return $this->api('GET', '/api/accounts', $parentToken)[0]['id'];
    }
}
