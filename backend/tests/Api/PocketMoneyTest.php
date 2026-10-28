<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class PocketMoneyTest extends ApiTestCase
{
    public function testAChildSpendsWhatTheyHaveAndNoMore(): void
    {
        $parent = $this->registerFamily();
        [$leaId, $lea] = $this->addChild($parent);
        $this->api('POST', "/api/children/$leaId/adjustments", $parent, ['amount' => 2000, 'label' => 'Anniversaire']);
        self::assertResponseStatusCodeSame(201);

        $pocket = $this->api('POST', "/api/children/$leaId/expenses", $lea, ['amount' => 500, 'label' => 'Bonbons', 'date' => '2026-10-20']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(1500, $pocket['balance']);

        $response = $this->api('POST', "/api/children/$leaId/expenses", $lea, ['amount' => 1501, 'label' => 'Jeu vidéo', 'date' => '2026-10-21']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Tu n’as pas assez d’argent de poche pour cette dépense.', $response['detail']);
    }

    public function testAChildOnlyDeletesTheExpensesTheyRecorded(): void
    {
        $parent = $this->registerFamily();
        [$leaId, $lea] = $this->addChild($parent);
        $this->api('POST', "/api/children/$leaId/adjustments", $parent, ['amount' => 2000, 'label' => 'Cadeau']);
        $pocket = $this->api('POST', "/api/children/$leaId/expenses", $lea, ['amount' => 300, 'label' => 'Stickers', 'date' => '2026-10-20']);
        ['expense' => $expense, 'adjustment' => $gift] = array_column($pocket['entries'], null, 'kind');
        self::assertTrue($expense['canDelete']);
        self::assertFalse($gift['canDelete']);

        $this->api('DELETE', '/api/pocket-money/'.$gift['id'], $lea);
        self::assertResponseStatusCodeSame(403);
        $pocket = $this->api('DELETE', '/api/pocket-money/'.$expense['id'], $lea);
        self::assertResponseIsSuccessful();
        self::assertSame(2000, $pocket['balance']);
    }

    public function testTheParentsSeeEveryChildsBalance(): void
    {
        $parent = $this->registerFamily();
        [$leaId] = $this->addChild($parent, 'lea');
        $this->addChild($parent, 'tom');
        $this->api('POST', "/api/children/$leaId/adjustments", $parent, ['amount' => 750, 'label' => 'Cadeau']);

        $children = $this->api('GET', '/api/children', $parent);
        self::assertSame(['Lea' => 750, 'Tom' => 0], array_column($children, 'balance', 'displayName'));
    }
}
