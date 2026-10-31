<?php

namespace App\Money;

use App\Entity\Category;
use App\Entity\Family;
use App\Enum\CategoryKind;
use App\Repository\AllowanceRepository;
use App\Repository\CategoryRepository;
use App\Repository\MaintenanceRepository;
use App\Repository\OperationRepository;
use App\Repository\RecurringOperationRepository;

/**
 * What the household should have left at the end of the month: the income of the month (already
 * received, plus the recurring income still to come) minus what it spends.
 *
 * Every expense category counts what was already spent in it plus its recurring operations still
 * to come; a category with a budget counts at least its budget, which covers its recurring
 * operations (a rent in a budgeted "Housing" is not counted twice). The pocket money category also
 * counts the allowances still to pay. Transfers and the teenagers' accounts stay out, as in the
 * other figures of the household budget. The vehicle maintenance still to do by the end of the
 * month, overdue included, counts its own budget.
 */
final class MonthForecast
{
    public function __construct(
        private readonly OperationRepository $operations,
        private readonly RecurringOperationRepository $recurring,
        private readonly AllowanceRepository $allowances,
        private readonly CategoryRepository $categories,
        private readonly MaintenanceRepository $maintenances,
    ) {
    }

    /**
     * Once the schedules have caught up (Schedules), whatever falls due from now on is still to come.
     *
     * @return array{income: int, recurring: int, budgets: int, pocketMoney: int, maintenance: int, other: int, remaining: int} expenses as positive amounts
     */
    public function forecast(Family $family, Month $month): array
    {
        $end = $month->end();
        $income = 0;
        /** @var array<int|string, array{category: ?Category, spent: int, recurring: int}> $lines expenses by category id, '' without one */
        $lines = [];
        $line = static function (?Category $category) use (&$lines): string|int {
            $key = $category?->getId() ?? '';
            $lines[$key] ??= ['category' => $category, 'spent' => 0, 'recurring' => 0];

            return $key;
        };
        // A budget counts even when nothing was spent yet
        foreach ($this->categories->forFamily($family) as $category) {
            if (null !== $category->getMonthlyBudget() || Category::POCKET_MONEY === $category->getCode()) {
                $line($category);
            }
        }

        foreach ($this->operations->between($family, $month->start, $end) as $operation) {
            if (null !== $operation->getTransfer()) {
                continue;
            }
            if (self::isIncome($operation->getCategory(), $operation->getAmount())) {
                $income += $operation->getAmount();
                continue;
            }
            // Net spending: a refund in an expense category lowers it
            $key = $line($operation->getCategory());
            $lines[$key]['spent'] -= $operation->getAmount();
            if (null !== $operation->getRecurring()) {
                $lines[$key]['recurring'] -= $operation->getAmount();
            }
        }

        foreach ($this->recurring->forFamily($family) as $recurring) {
            $amount = $recurring->getAmount() * \count($recurring->datesBefore($end));
            if (self::isIncome($recurring->getCategory(), $recurring->getAmount())) {
                $income += $amount;
            } else {
                $key = $line($recurring->getCategory());
                $lines[$key]['spent'] -= $amount;
                $lines[$key]['recurring'] -= $amount;
            }
        }

        // The allowances still to pay; a teenager's are reduced by the advance they repay
        $allowances = 0;
        foreach ($this->allowances->forFamily($family) as $allowance) {
            $due = $allowance->getAmount() * \count($allowance->datesBefore($end));
            $member = $allowance->getChild();
            $allowances += $member->isTeen() ? $due - min($member->getAdvanceOwed(), $due) : $due;
        }

        $maintenance = array_sum(array_map(static fn ($m) => $m->getBudget(), $this->maintenances->dueBefore($family, $end)));
        $forecast = ['income' => $income, 'recurring' => 0, 'budgets' => 0, 'pocketMoney' => $allowances, 'maintenance' => $maintenance, 'other' => 0];
        foreach ($lines as $l) {
            $category = $l['category'];
            if (Category::POCKET_MONEY === $category?->getCode()) {
                $forecast['pocketMoney'] = max($category->getMonthlyBudget() ?? 0, $l['spent'] + $allowances);
            } elseif (null !== $category?->getMonthlyBudget()) {
                $forecast['budgets'] += max($category->getMonthlyBudget(), $l['spent']);
            } else {
                $forecast['recurring'] += $l['recurring'];
                $forecast['other'] += $l['spent'] - $l['recurring'];
            }
        }

        return $forecast + ['remaining' => $income - $forecast['recurring'] - $forecast['budgets'] - $forecast['pocketMoney'] - $forecast['maintenance'] - $forecast['other']];
    }

    /** An income category, or money coming in without a category */
    private static function isIncome(?Category $category, int $amount): bool
    {
        return null === $category ? $amount > 0 : CategoryKind::Income === $category->getKind();
    }
}
