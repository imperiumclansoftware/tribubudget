<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\RecurringOperation;
use App\Entity\User;
use App\Enum\CategoryKind;
use App\Money\Month;
use App\Money\MonthForecast;
use App\Money\SavingsAllocator;
use App\Money\Schedules;
use App\Money\Today;
use App\Repository\AccountRepository;
use App\Repository\AllowanceRepository;
use App\Repository\CategoryRepository;
use App\Repository\MoneyRequestRepository;
use App\Repository\OperationRepository;
use App\Repository\PocketMoneyEntryRepository;
use App\Repository\RecurringOperationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Overview of a month for the parents: balances, spending against the budgets, trend. The
 * teenagers' accounts stay outside these figures.
 */
#[Route('/api', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents ont accès au tableau de bord.')]
final class DashboardController extends AbstractController
{
    private const HISTORY_MONTHS = 6;
    /** Recurring operations shown when they fall within this many days */
    private const UPCOMING_DAYS = 30;

    public function __construct(
        private readonly OperationRepository $operations,
    ) {
    }

    #[Route('/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function dashboard(
        #[CurrentUser] User $me,
        Today $today,
        Schedules $schedules,
        AccountRepository $accounts,
        CategoryRepository $categories,
        UserRepository $users,
        PocketMoneyEntryRepository $pocketMoney,
        AllowanceRepository $allowances,
        MoneyRequestRepository $requests,
        RecurringOperationRepository $recurring,
        SavingsAllocator $savings,
        MonthForecast $forecast,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^'.Month::PATTERN.'$/'])] ?string $month = null,
    ): JsonResponse {
        $family = $me->getFamily();
        $schedules->catchUp($family);
        $period = null === $month ? Month::of($today->date()) : Month::parse($month);

        $balances = $accounts->balances($family);
        $household = $accounts->household($family);
        $spent = $this->operations->spentByCategory($family, $period->start, $period->end());
        $expenseCategories = array_filter($categories->forFamily($family), static fn (Category $c) => CategoryKind::Expense === $c->getKind());
        $childBalances = $pocketMoney->balances($family);

        $history = [];
        for ($i = self::HISTORY_MONTHS - 1; $i >= 0; --$i) {
            $m = $period->previous($i);
            $history[] = ['month' => $m->key()] + $this->operations->totals($family, $m->start, $m->end());
        }

        return $this->json([
            'month' => $period->key(),
            ...$this->operations->totals($family, $period->start, $period->end()),
            'accounts' => array_map(static fn (Account $a) => [
                'id' => $a->getId(),
                'name' => $a->getName(),
                'type' => $a->getType()->value,
                'balance' => $balances[$a->getId()] ?? $a->getInitialBalance(),
            ], $household),
            'totalBalance' => array_sum(array_map(static fn (Account $a) => $balances[$a->getId()] ?? $a->getInitialBalance(), $household)),
            // Every expense category with a budget or some spending, the biggest first
            'categories' => array_map(static fn (Category $c) => [
                'id' => $c->getId(),
                'name' => $c->getName(),
                'color' => $c->getColor(),
                'spent' => $spent[$c->getId()] ?? 0,
                'budget' => $c->getMonthlyBudget(),
            ], self::sortBySpending(array_filter(
                $expenseCategories,
                static fn (Category $c) => null !== $c->getMonthlyBudget() || ($spent[$c->getId()] ?? 0) !== 0,
            ), $spent)),
            'uncategorized' => $this->operations->uncategorizedExpenses($family, $period->start, $period->end()),
            'history' => $history,
            'children' => array_map(static fn (User $child) => [
                'id' => $child->getId(),
                'displayName' => $child->getDisplayName(),
                'balance' => $childBalances[$child->getId()] ?? 0,
            ], $users->children($family)),
            // Outside the household budget: what each teenager holds on their accounts
            'teens' => array_map(static fn (User $teen) => [
                'id' => $teen->getId(),
                'displayName' => $teen->getDisplayName(),
                'balance' => array_sum(array_map(static fn (Account $a) => $balances[$a->getId()] ?? $a->getInitialBalance(), $accounts->ownedBy($teen))),
            ], $users->teens($family)),
            'upcoming' => array_map(static fn (RecurringOperation $r) => [
                'id' => $r->getId(),
                'label' => $r->getLabel(),
                'amount' => $r->getAmount(),
                'date' => $r->getNextOn()->format('Y-m-d'),
                'categoryColor' => $r->getCategory()?->getColor(),
            ], $recurring->upcoming($family, $today->date()->modify(\sprintf('+%d days', self::UPCOMING_DAYS)))),
            // Projects whose share of their savings account already covers the target
            'fundableProjects' => array_merge(...array_map(static fn (array $allocation) => array_map(static fn (array $a) => [
                'id' => $a['project']->getId(),
                'name' => $a['project']->getName(),
                'target' => $a['project']->getTarget(),
                'accountName' => $allocation['account']->getName(),
            ], array_values(array_filter($allocation['projects'], static fn (array $a) => $a['fundable']))), $savings->allocate($family))),
            'activeAllowances' => \count(array_filter($allowances->forFamily($family), static fn ($a) => $a->isActive())),
            'pendingRequests' => $requests->countPending($family),
            // What should be left at the end of the current month; null for the other months
            'forecast' => $period->key() === Month::of($today->date())->key() ? $forecast->forecast($family, $period) : null,
        ]);
    }

    /**
     * @param array<Category> $categories
     * @param array<int, int> $spent
     *
     * @return list<Category>
     */
    private static function sortBySpending(array $categories, array $spent): array
    {
        usort($categories, static fn (Category $a, Category $b) => ($spent[$b->getId()] ?? 0) <=> ($spent[$a->getId()] ?? 0) ?: strcmp($a->getName(), $b->getName()));

        return $categories;
    }
}
