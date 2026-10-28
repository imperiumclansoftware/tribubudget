<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class MoneyRequestTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAnAcceptedAdvanceIsRepaidByTheNextAllowances(): void
    {
        self::mockTime('2026-10-28 09:00:00');
        $parent = $this->registerFamily();
        [$leaId, $lea] = $this->addChild($parent);
        $this->api('PUT', "/api/children/$leaId/allowance", $parent, ['amount' => 500, 'frequency' => 'weekly', 'nextPaymentOn' => '2026-11-04']);

        $request = $this->api('POST', '/api/requests', $lea, ['kind' => 'advance', 'amount' => 800, 'reason' => 'Cadeau pour Tom']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, $this->api('GET', '/api/me', $parent)['pendingRequests']);

        $this->api('POST', '/api/requests/'.$request['id'].'/approve', $parent, ['answer' => 'D’accord !']);
        self::assertResponseIsSuccessful();
        $pocket = $this->api('GET', "/api/children/$leaId", $lea);
        self::assertSame(800, $pocket['balance']);
        self::assertSame(800, $pocket['advanceOwed']);

        // First allowance: entirely kept back; second: 300 kept back, 200 for Léa
        self::mockTime('2026-11-11 09:00:00');
        $pocket = $this->api('GET', "/api/children/$leaId", $lea);
        self::assertSame(800 + 200, $pocket['balance']);
        self::assertSame(0, $pocket['advanceOwed']);
    }

    public function testOnlyTheParentsAnswerAndOnlyOnce(): void
    {
        $parent = $this->registerFamily();
        [, $lea] = $this->addChild($parent);
        $request = $this->api('POST', '/api/requests', $lea, ['kind' => 'purchase', 'amount' => 4500, 'reason' => 'Baskets']);

        $this->api('POST', '/api/requests/'.$request['id'].'/approve', $lea, []);
        self::assertResponseStatusCodeSame(403);

        $this->api('POST', '/api/requests/'.$request['id'].'/reject', $parent, ['answer' => 'Pour Noël peut-être']);
        self::assertResponseIsSuccessful();
        $this->api('POST', '/api/requests/'.$request['id'].'/approve', $parent, []);
        self::assertResponseStatusCodeSame(403);

        [$mine] = $this->api('GET', '/api/requests', $lea);
        self::assertSame('rejected', $mine['status']);
        self::assertSame('Pour Noël peut-être', $mine['answer']);
    }

    public function testAChildSeesOnlyTheirOwnRequestsAndCanWithdrawThem(): void
    {
        $parent = $this->registerFamily();
        [, $lea] = $this->addChild($parent, 'lea');
        [, $tom] = $this->addChild($parent, 'tom');
        $this->api('POST', '/api/requests', $tom, ['kind' => 'advance', 'amount' => 100, 'reason' => 'Goûter']);
        $leas = $this->api('POST', '/api/requests', $lea, ['kind' => 'advance', 'amount' => 200, 'reason' => 'Cinéma']);

        self::assertSame(['Cinéma'], array_column($this->api('GET', '/api/requests', $lea), 'reason'));
        self::assertCount(2, $this->api('GET', '/api/requests', $parent));

        $this->api('POST', '/api/requests/'.$leas['id'].'/cancel', $tom);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('cancelled', $this->api('POST', '/api/requests/'.$leas['id'].'/cancel', $lea)['status']);
    }

    public function testParentsDoNotMakeRequests(): void
    {
        $this->api('POST', '/api/requests', $this->registerFamily(), ['kind' => 'advance', 'amount' => 100, 'reason' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }
}
