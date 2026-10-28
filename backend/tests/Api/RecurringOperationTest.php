<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class RecurringOperationTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testTheDatesAlreadyPassedAreRecordedRightAway(): void
    {
        self::mockTime('2026-10-28 09:00:00');
        $parent = $this->registerFamily();
        $accountId = $this->firstAccountId($parent);
        $housing = array_column($this->api('GET', '/api/categories', $parent), 'id', 'name')['Logement'];

        $rent = $this->api('POST', '/api/recurring-operations', $parent, [
            'accountId' => $accountId, 'categoryId' => $housing, 'type' => 'expense', 'amount' => 95000,
            'label' => 'Loyer', 'frequency' => 'monthly', 'nextOn' => '2026-08-31',
        ]);
        self::assertResponseStatusCodeSame(201);
        // 31 August, 30 September (shorter month): the next one keeps the 31st
        self::assertSame('2026-10-31', $rent['nextOn']);

        $september = $this->api('GET', '/api/operations?month=2026-09', $parent);
        self::assertSame([['Loyer', -95000, '2026-09-30', $rent['id']]], array_map(static fn ($o) => [$o['label'], $o['amount'], $o['date'], $o['recurringId']], $september));
        self::assertCount(1, $this->api('GET', '/api/operations?month=2026-08', $parent));
        self::assertCount(0, $this->api('GET', '/api/operations?month=2026-10', $parent));

        // The 31st comes: recorded on the next visit, once
        self::mockTime('2026-10-31 08:00:00');
        $this->api('GET', '/api/me', $parent);
        $this->api('GET', '/api/me', $parent);
        self::assertCount(1, $this->api('GET', '/api/operations?month=2026-10', $parent));
    }

    public function testNothingIsRecordedAfterTheEndDateOrWhilePaused(): void
    {
        self::mockTime('2026-10-28 09:00:00');
        $parent = $this->registerFamily();
        $accountId = $this->firstAccountId($parent);

        $loan = $this->api('POST', '/api/recurring-operations', $parent, [
            'accountId' => $accountId, 'type' => 'expense', 'amount' => 20000, 'label' => 'Crédit voiture',
            'frequency' => 'monthly', 'nextOn' => '2026-08-10', 'endsOn' => '2026-09-30',
        ]);
        self::assertTrue($loan['finished']);
        $this->api('POST', '/api/recurring-operations', $parent, [
            'accountId' => $accountId, 'type' => 'expense', 'amount' => 1500, 'label' => 'Streaming',
            'frequency' => 'monthly', 'nextOn' => '2026-09-01', 'active' => false,
        ]);

        $labels = array_merge(...array_map(fn ($m) => array_column($this->api('GET', "/api/operations?month=$m", $parent), 'label'), ['2026-08', '2026-09', '2026-10']));
        self::assertSame(['Crédit voiture', 'Crédit voiture'], $labels);
    }

    public function testDeletingOneKeepsTheOperationsAlreadyRecorded(): void
    {
        self::mockTime('2026-10-28 09:00:00');
        $parent = $this->registerFamily();
        $salary = $this->api('POST', '/api/recurring-operations', $parent, [
            'accountId' => $this->firstAccountId($parent), 'type' => 'income', 'amount' => 250000, 'label' => 'Salaire',
            'frequency' => 'monthly', 'nextOn' => '2026-10-25',
        ]);

        $this->api('DELETE', '/api/recurring-operations/'.$salary['id'], $parent);
        self::assertResponseStatusCodeSame(204);
        [$operation] = $this->api('GET', '/api/operations?month=2026-10', $parent);
        self::assertSame(250000, $operation['amount']);
        self::assertNull($operation['recurringId']);
    }

    public function testTheDashboardShowsWhatFallsWithinAMonth(): void
    {
        self::mockTime('2026-10-28 09:00:00');
        $parent = $this->registerFamily();
        $accountId = $this->firstAccountId($parent);
        foreach ([['Loyer', 'monthly', '2026-11-05'], ['Assurance', 'yearly', '2027-03-01']] as [$label, $frequency, $nextOn]) {
            $this->api('POST', '/api/recurring-operations', $parent, ['accountId' => $accountId, 'type' => 'expense', 'amount' => 1000, 'label' => $label, 'frequency' => $frequency, 'nextOn' => $nextOn]);
        }

        $upcoming = $this->api('GET', '/api/dashboard', $parent)['upcoming'];
        self::assertSame([['Loyer', '2026-11-05']], array_map(static fn ($u) => [$u['label'], $u['date']], $upcoming));
    }

    public function testTheEndDateFollowsTheNextDate(): void
    {
        $parent = $this->registerFamily();
        $this->api('POST', '/api/recurring-operations', $parent, [
            'accountId' => $this->firstAccountId($parent), 'type' => 'expense', 'amount' => 1000, 'label' => 'x',
            'frequency' => 'monthly', 'nextOn' => '2026-10-01', 'endsOn' => '2026-09-01',
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testChildrenAndOtherFamiliesHaveNoAccess(): void
    {
        $parent = $this->registerFamily();
        [, $child] = $this->addChild($parent);
        $rent = $this->api('POST', '/api/recurring-operations', $parent, [
            'accountId' => $this->firstAccountId($parent), 'type' => 'expense', 'amount' => 1000, 'label' => 'Loyer',
            'frequency' => 'monthly', 'nextOn' => '2030-01-05',
        ]);

        $this->api('GET', '/api/recurring-operations', $child);
        self::assertResponseStatusCodeSame(403);
        $this->api('DELETE', '/api/recurring-operations/'.$rent['id'], $this->registerFamily('papa', 'Famille Durand'));
        self::assertResponseStatusCodeSame(404);
    }
}
