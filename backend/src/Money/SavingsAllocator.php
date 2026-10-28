<?php

namespace App\Money;

use App\Entity\Account;
use App\Entity\Family;
use App\Entity\SavingsProject;
use App\Repository\AccountRepository;
use App\Repository\SavingsProjectRepository;

/**
 * Shares the money of each savings account among its projects. What lies below the account's
 * minimum balance stays aside; the rest goes to the projects in the order of their priority,
 * each one filled up before the next one gets anything.
 */
final class SavingsAllocator
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly SavingsProjectRepository $projects,
    ) {
    }

    /**
     * @return list<array{account: Account, balance: int, available: int, unallocated: int, projects: list<array{project: SavingsProject, allocated: int, fundable: bool}>}>
     *                                                                                                                                                                       savings accounts, with what each project receives
     */
    public function allocate(Family $family): array
    {
        $balances = $this->accounts->balances($family);
        $projectsByAccount = [];
        foreach ($this->projects->current($family) as $project) {
            $projectsByAccount[$project->getAccount()->getId()][] = $project;
        }

        $result = [];
        foreach ($this->accounts->forFamily($family) as $account) {
            if (!$account->isSavings()) {
                continue;
            }
            $balance = $balances[$account->getId()] ?? $account->getInitialBalance();
            $available = max(0, $balance - ($account->getMinimumBalance() ?? 0));
            $remaining = $available;
            $allocations = [];
            foreach ($projectsByAccount[$account->getId()] ?? [] as $project) {
                $allocated = min($remaining, $project->getTarget());
                $remaining -= $allocated;
                $allocations[] = ['project' => $project, 'allocated' => $allocated, 'fundable' => $allocated >= $project->getTarget()];
            }
            $result[] = ['account' => $account, 'balance' => $balance, 'available' => $available, 'unallocated' => $remaining, 'projects' => $allocations];
        }

        return $result;
    }
}
