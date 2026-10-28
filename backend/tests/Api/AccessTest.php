<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** What a child may not do, and what no one may do with another family's data. */
final class AccessTest extends ApiTestCase
{
    /** @return iterable<array{string, string}> */
    public static function parentOnly(): iterable
    {
        yield ['GET', '/api/accounts'];
        yield ['POST', '/api/accounts'];
        yield ['GET', '/api/categories'];
        yield ['GET', '/api/operations'];
        yield ['POST', '/api/operations'];
        yield ['GET', '/api/dashboard'];
        yield ['GET', '/api/members'];
        yield ['POST', '/api/members'];
        yield ['GET', '/api/children'];
        yield ['GET', '/api/recurring-operations'];
        yield ['POST', '/api/recurring-operations'];
    }

    #[DataProvider('parentOnly')]
    public function testAChildHasNoAccessToTheHouseholdBudget(string $method, string $uri): void
    {
        [, $child] = $this->addChild($this->registerFamily());

        $this->api($method, $uri, $child, 'POST' === $method ? [] : null);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAChildSeesTheirOwnPocketMoneyButNotTheirSiblings(): void
    {
        $parent = $this->registerFamily();
        [$leaId, $lea] = $this->addChild($parent, 'lea');
        [$tomId] = $this->addChild($parent, 'tom');

        $this->api('GET', "/api/children/$leaId", $lea);
        self::assertResponseIsSuccessful();
        $this->api('GET', "/api/children/$tomId", $lea);
        self::assertResponseStatusCodeSame(403);
        $this->api('POST', "/api/children/$tomId/expenses", $lea, ['amount' => 100, 'label' => 'Bonbons', 'date' => '2026-10-01']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAChildCannotGiveThemselvesMoney(): void
    {
        [$leaId, $lea] = $this->addChild($this->registerFamily());

        $this->api('POST', "/api/children/$leaId/adjustments", $lea, ['amount' => 10000, 'label' => 'Cadeau']);
        self::assertResponseStatusCodeSame(403);
        $this->api('PUT', "/api/children/$leaId/allowance", $lea, ['amount' => 10000, 'frequency' => 'weekly', 'nextPaymentOn' => '2026-10-01']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAParentSeesNothingOfAnotherFamily(): void
    {
        $martin = $this->registerFamily('maman', 'Famille Martin');
        [$leaId] = $this->addChild($martin, 'lea');
        $accountId = $this->firstAccountId($martin);

        $durand = $this->registerFamily('papa', 'Famille Durand');
        $this->api('GET', "/api/children/$leaId", $durand);
        self::assertResponseStatusCodeSame(403);
        $this->api('PUT', "/api/accounts/$accountId", $durand, ['name' => 'Piraté', 'type' => 'checking']);
        self::assertResponseStatusCodeSame(404);
        $this->api('POST', '/api/operations', $durand, ['accountId' => $accountId, 'type' => 'expense', 'amount' => 100, 'label' => 'x', 'date' => '2026-10-01']);
        self::assertResponseStatusCodeSame(422);
        $this->api('DELETE', "/api/members/$leaId", $durand);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAParentCannotDeleteThemselves(): void
    {
        $parent = $this->registerFamily();
        $me = $this->api('GET', '/api/me', $parent);

        $this->api('DELETE', '/api/members/'.$me['id'], $parent);
        self::assertResponseStatusCodeSame(422);
    }
}
