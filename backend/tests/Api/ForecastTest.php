<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ForecastTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private string $parent;
    private int $accountId;
    /** @var array<string, array<string, mixed>> categories by name */
    private array $categories;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-10-15 09:00:00');
        $this->parent = $this->registerFamily();
        $this->accountId = $this->firstAccountId($this->parent);
        $this->categories = array_column($this->api('GET', '/api/categories', $this->parent), null, 'name');
    }

    public function testWhatIsLeftOnceTheMonthIsOver(): void
    {
        // Income: the salary already received, and a recurring one still to come
        $this->recurring('Salaires', 'income', 300000, '2026-10-01');
        $this->recurring('Allocations', 'income', 10000, '2026-10-25');
        // The rent is covered by the housing budget, not counted twice
        $this->budget('Logement', 100000);
        $this->recurring('Logement', 'expense', 90000, '2026-10-05');
        // Recurring, no budget
        $this->recurring('Abonnements', 'expense', 1500, '2026-10-20');
        // A budget not reached counts in full, an exceeded one counts what was spent
        $this->budget('Alimentation', 50000);
        $this->expense('Alimentation', 20000);
        $this->budget('Loisirs', 5000);
        $this->expense('Loisirs', 8000);
        // One-off expenses without a budget
        $this->expense('Divers', 2000);
        $this->expense(null, 3000);
        // Pocket money: two weekly payments to come for Léa, one monthly for Hugo
        [$leaId] = $this->addChild($this->parent);
        $this->api('PUT', "/api/children/$leaId/allowance", $this->parent, ['amount' => 500, 'frequency' => 'weekly', 'nextPaymentOn' => '2026-10-21', 'accountId' => $this->accountId]);
        [$hugoId, $hugo] = $this->addTeen($this->parent);
        $this->api('PUT', "/api/teens/$hugoId/allowance", $this->parent, ['amount' => 2000, 'frequency' => 'monthly', 'nextPaymentOn' => '2026-10-30', 'accountId' => $this->accountId]);
        // Left out: a transfer, and what a teenager spends on their own account
        $savingsId = $this->api('POST', '/api/accounts', $this->parent, ['name' => 'Livret A', 'type' => 'savings', 'initialBalance' => 0])['id'];
        $this->api('POST', '/api/transfers', $this->parent, ['fromAccountId' => $this->accountId, 'toAccountId' => $savingsId, 'amount' => 40000, 'date' => '2026-10-10']);
        $hugoAccount = $this->api('GET', "/api/teens/$hugoId", $hugo)['accounts'][0]['id'];
        $this->api('POST', '/api/operations', $hugo, ['accountId' => $hugoAccount, 'type' => 'expense', 'amount' => 999, 'label' => 'Cinéma', 'date' => '2026-10-12']);

        self::assertSame([
            'income' => 310000,
            'recurring' => 1500,
            'budgets' => 100000 + 50000 + 8000,
            'pocketMoney' => 1000 + 2000,
            'other' => 5000,
            'remaining' => 310000 - 1500 - 158000 - 3000 - 5000,
        ], $this->api('GET', '/api/dashboard?month=2026-10', $this->parent)['forecast']);
    }

    public function testTheAllowancesFillThePocketMoneyBudget(): void
    {
        $this->budget('Argent de poche', 3000);
        [$leaId] = $this->addChild($this->parent);
        $this->api('PUT', "/api/children/$leaId/allowance", $this->parent, ['amount' => 500, 'frequency' => 'weekly', 'nextPaymentOn' => '2026-10-21']);

        // 1000 to pay, within the 3000 budget
        self::assertSame(3000, $this->api('GET', '/api/dashboard', $this->parent)['forecast']['pocketMoney']);
    }

    public function testOnlyForTheCurrentMonth(): void
    {
        self::assertNull($this->api('GET', '/api/dashboard?month=2026-09', $this->parent)['forecast']);
        self::assertSame(0, $this->api('GET', '/api/dashboard', $this->parent)['forecast']['remaining']);
    }

    private function recurring(string $category, string $type, int $amount, string $nextOn): void
    {
        $this->api('POST', '/api/recurring-operations', $this->parent, [
            'accountId' => $this->accountId, 'categoryId' => $this->categories[$category]['id'], 'type' => $type,
            'amount' => $amount, 'label' => $category, 'frequency' => 'monthly', 'nextOn' => $nextOn,
        ]);
        self::assertResponseStatusCodeSame(201);
    }

    private function budget(string $name, int $amount): void
    {
        $category = $this->categories[$name];
        $this->api('PUT', '/api/categories/'.$category['id'], $this->parent, ['name' => $name, 'kind' => 'expense', 'color' => $category['color'], 'monthlyBudget' => $amount]);
        self::assertResponseIsSuccessful();
    }

    private function expense(?string $category, int $amount): void
    {
        $this->api('POST', '/api/operations', $this->parent, [
            'accountId' => $this->accountId, 'categoryId' => null === $category ? null : $this->categories[$category]['id'],
            'type' => 'expense', 'amount' => $amount, 'label' => $category ?? 'Sans catégorie', 'date' => '2026-10-08',
        ]);
        self::assertResponseStatusCodeSame(201);
    }
}
