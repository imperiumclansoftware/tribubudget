<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class AllowanceTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testMissedAllowancesArePaidWhenTheFamilyComesBack(): void
    {
        self::mockTime('2026-10-28 09:00:00');
        $parent = $this->registerFamily();
        [$leaId, $lea] = $this->addChild($parent);
        $accountId = $this->firstAccountId($parent);

        // Due on the 7th, 14th, 21st and 28th
        $this->api('PUT', "/api/children/$leaId/allowance", $parent, ['amount' => 500, 'frequency' => 'weekly', 'nextPaymentOn' => '2026-10-07', 'accountId' => $accountId]);
        self::assertResponseIsSuccessful();

        $this->api('GET', '/api/me', $lea);
        $pocket = $this->api('GET', "/api/children/$leaId", $lea);
        self::assertSame(2000, $pocket['balance']);
        self::assertSame('2026-11-04', $pocket['allowance']['nextPaymentOn']);

        // Taken from the household account, under the pocket money category
        $operations = $this->api('GET', '/api/operations?month=2026-10', $parent);
        self::assertCount(4, $operations);
        self::assertSame(-2000, array_sum(array_column($operations, 'amount')));

        // Nothing more on the next visit
        $this->api('GET', '/api/me', $parent);
        self::assertSame(2000, $this->api('GET', "/api/children/$leaId", $lea)['balance']);
    }

    public function testAnInactiveAllowanceIsNotPaid(): void
    {
        self::mockTime('2026-10-28 09:00:00');
        $parent = $this->registerFamily();
        [$leaId] = $this->addChild($parent);

        $this->api('PUT', "/api/children/$leaId/allowance", $parent, ['amount' => 500, 'frequency' => 'monthly', 'nextPaymentOn' => '2026-10-01', 'active' => false]);

        self::assertSame(0, $this->api('GET', "/api/children/$leaId", $parent)['balance']);
    }

    public function testAnAllowanceIsWeeklyOrMonthly(): void
    {
        $parent = $this->registerFamily();
        [$leaId] = $this->addChild($parent);

        $this->api('PUT', "/api/children/$leaId/allowance", $parent, ['amount' => 500, 'frequency' => 'yearly', 'nextPaymentOn' => '2026-10-01']);
        self::assertResponseStatusCodeSame(422);
    }
}
