<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class DashboardTest extends ApiTestCase
{
    public function testTheMonthIsComparedToTheBudgets(): void
    {
        $parent = $this->registerFamily();
        $accountId = $this->firstAccountId($parent);
        $categories = array_column($this->api('GET', '/api/categories', $parent), null, 'name');
        $food = $categories['Alimentation'];
        $this->api('PUT', '/api/categories/'.$food['id'], $parent, ['name' => 'Alimentation', 'kind' => 'expense', 'color' => $food['color'], 'monthlyBudget' => 40000]);

        $add = fn (string $type, int $amount, string $category, string $date) => $this->api('POST', '/api/operations', $parent, [
            'accountId' => $accountId, 'categoryId' => $categories[$category]['id'], 'type' => $type, 'amount' => $amount, 'label' => $category, 'date' => $date,
        ]);
        $add('income', 250000, 'Salaires', '2026-10-01');
        $add('expense', 32000, 'Alimentation', '2026-10-05');
        $add('expense', 15000, 'Alimentation', '2026-10-12');
        $add('expense', 9000, 'Loisirs', '2026-10-14');
        $add('expense', 5000, 'Alimentation', '2026-09-20');

        $dashboard = $this->api('GET', '/api/dashboard?month=2026-10', $parent);
        self::assertResponseIsSuccessful();
        self::assertSame(250000, $dashboard['income']);
        self::assertSame(56000, $dashboard['expenses']);
        self::assertSame(250000 - 56000 - 5000, $dashboard['totalBalance']);
        self::assertSame(['id' => $food['id'], 'name' => 'Alimentation', 'color' => $food['color'], 'spent' => 47000, 'budget' => 40000], $dashboard['categories'][0]);
        self::assertSame('Loisirs', $dashboard['categories'][1]['name']);
        self::assertSame(['month' => '2026-09', 'income' => 0, 'expenses' => 5000], $dashboard['history'][4]);
    }

    public function testAnAccountWithOperationsIsNotDeleted(): void
    {
        $parent = $this->registerFamily();
        $accountId = $this->firstAccountId($parent);
        $this->api('POST', '/api/operations', $parent, ['accountId' => $accountId, 'type' => 'expense', 'amount' => 100, 'label' => 'Pain', 'date' => '2026-10-01']);

        $this->api('DELETE', "/api/accounts/$accountId", $parent);
        self::assertResponseStatusCodeSame(409);
    }
}
