<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class ReconcileTest extends ApiTestCase
{
    public function testTheReconciledBalanceCountsTheOperationsTickedOff(): void
    {
        $parent = $this->registerFamily();
        $accountId = $this->firstAccountId($parent);
        $this->api('PUT', "/api/accounts/$accountId", $parent, ['name' => 'Compte courant', 'type' => 'checking', 'initialBalance' => 100000]);
        $bread = $this->api('POST', '/api/operations', $parent, ['accountId' => $accountId, 'type' => 'expense', 'amount' => 300, 'label' => 'Pain', 'date' => '2026-10-02']);
        $this->api('POST', '/api/operations', $parent, ['accountId' => $accountId, 'type' => 'expense', 'amount' => 5000, 'label' => 'Courses', 'date' => '2026-10-03']);
        self::assertFalse($bread['reconciled']);

        $ticked = $this->api('PUT', '/api/operations/'.$bread['id'].'/reconciled', $parent, ['reconciled' => true]);
        self::assertResponseIsSuccessful();
        self::assertTrue($ticked['reconciled']);

        [$account] = $this->api('GET', '/api/accounts', $parent);
        self::assertSame([100000 - 5300, 100000 - 300], [$account['balance'], $account['reconciledBalance']]);

        $this->api('PUT', '/api/operations/'.$bread['id'].'/reconciled', $parent, ['reconciled' => false]);
        self::assertSame(100000, $this->api('GET', '/api/accounts', $parent)[0]['reconciledBalance']);
    }

    public function testATransferLegIsTickedOffOnItsOwn(): void
    {
        $parent = $this->registerFamily();
        $checkingId = $this->firstAccountId($parent);
        $savingsId = $this->api('POST', '/api/accounts', $parent, ['name' => 'Livret A', 'type' => 'savings', 'initialBalance' => 0])['id'];
        $this->api('POST', '/api/transfers', $parent, ['fromAccountId' => $checkingId, 'toAccountId' => $savingsId, 'amount' => 2000, 'date' => '2026-10-05']);
        [$out] = $this->api('GET', "/api/operations?month=2026-10&accountId=$checkingId", $parent);

        $this->api('PUT', '/api/operations/'.$out['id'].'/reconciled', $parent, ['reconciled' => true]);
        self::assertResponseIsSuccessful();
        self::assertSame([-2000, 0], array_column($this->api('GET', '/api/accounts', $parent), 'reconciledBalance'));
    }

    public function testATeenagerTicksOffTheirOwnAccountsOnly(): void
    {
        $parent = $this->registerFamily();
        $householdId = $this->firstAccountId($parent);
        [$hugoId, $hugo] = $this->addTeen($parent);
        $hugoAccount = $this->api('GET', "/api/teens/$hugoId", $hugo)['accounts'][0]['id'];
        // Put there by a parent: Hugo cannot change it, but ticks it off
        $gift = $this->api('POST', '/api/operations', $parent, ['accountId' => $hugoAccount, 'type' => 'income', 'amount' => 2000, 'label' => 'Cadeau', 'date' => '2026-10-05']);
        $bread = $this->api('POST', '/api/operations', $parent, ['accountId' => $householdId, 'type' => 'expense', 'amount' => 300, 'label' => 'Pain', 'date' => '2026-10-02']);

        $this->api('PUT', '/api/operations/'.$gift['id'].'/reconciled', $hugo, ['reconciled' => true]);
        self::assertResponseIsSuccessful();
        self::assertSame(2000, $this->api('GET', "/api/teens/$hugoId", $hugo)['accounts'][0]['reconciledBalance']);
        $this->api('PUT', '/api/operations/'.$bread['id'].'/reconciled', $hugo, ['reconciled' => true]);
        self::assertResponseStatusCodeSame(403);
    }
}
