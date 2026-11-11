<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class TransferTest extends ApiTestCase
{
    private string $parent;
    private int $checkingId;
    private int $savingsId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parent = $this->registerFamily();
        $this->checkingId = $this->firstAccountId($this->parent);
        $this->api('PUT', '/api/accounts/'.$this->checkingId, $this->parent, ['name' => 'Compte courant', 'type' => 'checking', 'initialBalance' => 200000]);
        $this->savingsId = $this->api('POST', '/api/accounts', $this->parent, ['name' => 'Livret A', 'type' => 'savings', 'initialBalance' => 0])['id'];
    }

    public function testTheMoneyMovesFromOneAccountToTheOther(): void
    {
        $transfer = $this->transfer(50000);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('Épargne du mois', $transfer['label']);

        self::assertSame([150000, 50000], $this->balances());
        $out = $this->api('GET', '/api/operations?month=2026-10&accountId='.$this->checkingId, $this->parent);
        self::assertSame([[-50000, $transfer['id'], $this->savingsId]], array_map(static fn ($o) => [$o['amount'], $o['transfer']['id'], $o['transfer']['counterpartAccountId']], $out));
    }

    public function testATransferIsNeitherAnIncomeNorAnExpense(): void
    {
        $this->api('POST', '/api/operations', $this->parent, ['accountId' => $this->checkingId, 'type' => 'expense', 'amount' => 3000, 'label' => 'Pain', 'date' => '2026-10-02']);
        $this->transfer(50000);

        $dashboard = $this->api('GET', '/api/dashboard?month=2026-10', $this->parent);
        self::assertSame(0, $dashboard['income']);
        self::assertSame(3000, $dashboard['expenses']);
        // Only the bread: the transfer has no category either, but it is no expense
        self::assertSame(3000, $dashboard['uncategorized']);
        self::assertSame(200000 - 3000, $dashboard['totalBalance']);
    }

    public function testATransferFeedsTheSavingsProjects(): void
    {
        $this->api('POST', '/api/projects', $this->parent, ['accountId' => $this->savingsId, 'name' => 'Vélo', 'target' => 40000]);
        $this->transfer(50000);

        self::assertSame(['Vélo'], array_column($this->api('GET', '/api/dashboard', $this->parent)['fundableProjects'], 'name'));
    }

    public function testEditingOrDeletingTheTransferChangesBothOperations(): void
    {
        $transfer = $this->transfer(50000);

        $this->api('PUT', '/api/transfers/'.$transfer['id'], $this->parent, ['fromAccountId' => $this->savingsId, 'toAccountId' => $this->checkingId, 'amount' => 20000, 'date' => '2026-10-06']);
        self::assertResponseIsSuccessful();
        self::assertSame([220000, -20000], $this->balances());

        $this->api('DELETE', '/api/transfers/'.$transfer['id'], $this->parent);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([200000, 0], $this->balances());
        self::assertSame([], $this->api('GET', '/api/operations?month=2026-10', $this->parent));
    }

    public function testItsOperationsAreNotChangedOneByOne(): void
    {
        $this->transfer(50000);
        [$operation] = $this->api('GET', '/api/operations?month=2026-10&accountId='.$this->checkingId, $this->parent);

        $this->api('DELETE', '/api/operations/'.$operation['id'], $this->parent);
        self::assertResponseStatusCodeSame(422);
        self::assertSame([150000, 50000], $this->balances());
    }

    public function testTheAccountsAreTwoDifferentAccountsOfTheFamily(): void
    {
        $this->api('POST', '/api/transfers', $this->parent, ['fromAccountId' => $this->checkingId, 'toAccountId' => $this->checkingId, 'amount' => 100, 'date' => '2026-10-05']);
        self::assertResponseStatusCodeSame(422);

        $other = $this->registerFamily('papa', 'Famille Durand');
        $this->api('POST', '/api/transfers', $other, ['fromAccountId' => $this->firstAccountId($other), 'toAccountId' => $this->savingsId, 'amount' => 100, 'date' => '2026-10-05']);
        self::assertResponseStatusCodeSame(422);
        [, $child] = $this->addChild($this->parent);
        $this->transfer(100, $child);
        self::assertResponseStatusCodeSame(403);
    }

    public function testATransferGivenACategoryCountsInItsBudgetButStaysNoExpense(): void
    {
        $savingsBudget = $this->api('POST', '/api/categories', $this->parent, ['name' => 'Économies', 'kind' => 'expense', 'color' => '#2a9d8f', 'monthlyBudget' => 30000]);
        $transfer = $this->api('POST', '/api/transfers', $this->parent, [
            'fromAccountId' => $this->checkingId, 'toAccountId' => $this->savingsId, 'amount' => 20000,
            'date' => '2026-10-05', 'categoryId' => $savingsBudget['id'],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame($savingsBudget['id'], $transfer['categoryId']);

        // The money taken out of the current account is in the category, the money in is not
        $legs = $this->api('GET', '/api/operations?month=2026-10&categoryId='.$savingsBudget['id'], $this->parent);
        self::assertSame([[-20000, $this->checkingId]], array_map(static fn ($o) => [$o['amount'], $o['accountId']], $legs));
        self::assertSame($savingsBudget['id'], $legs[0]['transfer']['categoryId']);

        $dashboard = $this->api('GET', '/api/dashboard?month=2026-10', $this->parent);
        self::assertSame(['spent' => 20000, 'budget' => 30000], array_intersect_key(array_column($dashboard['categories'], null, 'name')['Économies'], ['spent' => 0, 'budget' => 0]));
        // Still no expense: the money stays in the family
        self::assertSame([0, 0, 200000], [$dashboard['income'], $dashboard['expenses'], $dashboard['totalBalance']]);

        // Without its category, it leaves the budget
        $this->api('PUT', '/api/transfers/'.$transfer['id'], $this->parent, ['fromAccountId' => $this->checkingId, 'toAccountId' => $this->savingsId, 'amount' => 20000, 'date' => '2026-10-05']);
        self::assertResponseIsSuccessful();
        self::assertSame(0, array_column($this->api('GET', '/api/dashboard?month=2026-10', $this->parent)['categories'], 'spent', 'name')['Économies']);
    }

    public function testOnlyAnExpenseCategoryAndOnlyFromTheHousehold(): void
    {
        $salary = array_column($this->api('GET', '/api/categories', $this->parent), 'id', 'name')['Salaires'];
        $this->api('POST', '/api/transfers', $this->parent, ['fromAccountId' => $this->checkingId, 'toAccountId' => $this->savingsId, 'amount' => 100, 'date' => '2026-10-05', 'categoryId' => $salary]);
        self::assertResponseStatusCodeSame(422);

        $savingsBudget = $this->api('POST', '/api/categories', $this->parent, ['name' => 'Économies', 'kind' => 'expense', 'color' => '#2a9d8f', 'monthlyBudget' => 30000]);
        [$hugoId, $hugo] = $this->addTeen($this->parent);
        $accounts = array_column($this->api('GET', "/api/teens/$hugoId", $hugo)['accounts'], 'id', 'type');
        $this->api('POST', '/api/transfers', $hugo, ['fromAccountId' => $accounts['checking'], 'toAccountId' => $accounts['savings'], 'amount' => 100, 'date' => '2026-10-05', 'categoryId' => $savingsBudget['id']]);
        self::assertResponseStatusCodeSame(422);
    }

    /** @return array<string, mixed> */
    private function transfer(int $amount, ?string $token = null): ?array
    {
        return $this->api('POST', '/api/transfers', $token ?? $this->parent, [
            'fromAccountId' => $this->checkingId, 'toAccountId' => $this->savingsId, 'amount' => $amount,
            'label' => 'Épargne du mois', 'date' => '2026-10-05',
        ]);
    }

    /** @return array{int, int} checking, savings */
    private function balances(): array
    {
        $balances = array_column($this->api('GET', '/api/accounts', $this->parent), 'balance', 'id');

        return [$balances[$this->checkingId], $balances[$this->savingsId]];
    }
}
