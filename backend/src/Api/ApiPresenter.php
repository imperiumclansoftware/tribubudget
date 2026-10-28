<?php

namespace App\Api;

use App\Entity\Account;
use App\Entity\Allowance;
use App\Entity\Category;
use App\Entity\Family;
use App\Entity\MoneyRequest;
use App\Entity\Operation;
use App\Entity\PocketMoneyEntry;
use App\Entity\RecurringOperation;
use App\Entity\SavingsProject;
use App\Entity\Transfer;
use App\Entity\User;

/**
 * Turns entities into the JSON structures exposed by the API. Amounts are in cents.
 */
final class ApiPresenter
{
    /** @return array{id: ?int, username: string, displayName: string, role: string} */
    public function user(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'displayName' => $user->getDisplayName(),
            'role' => $user->getRole()->value,
        ];
    }

    /** @return array<string, mixed> */
    public function me(User $user, int $pendingRequests): array
    {
        return $this->user($user) + [
            'family' => $this->family($user->getFamily()),
            'pendingRequests' => $pendingRequests,
        ];
    }

    /** @return array{id: ?int, name: string} */
    public function family(Family $family): array
    {
        return ['id' => $family->getId(), 'name' => $family->getName()];
    }

    /** @return array<string, mixed> */
    public function account(Account $account, int $balance): array
    {
        return [
            'id' => $account->getId(),
            'name' => $account->getName(),
            'type' => $account->getType()->value,
            'initialBalance' => $account->getInitialBalance(),
            'minimumBalance' => $account->getMinimumBalance(),
            'balance' => $balance,
        ];
    }

    /**
     * A savings account and the share of its money each project receives (SavingsAllocator).
     *
     * @param array{account: Account, balance: int, available: int, unallocated: int, projects: list<array{project: SavingsProject, allocated: int, fundable: bool}>} $allocation
     *
     * @return array<string, mixed>
     */
    public function savingsAccount(array $allocation): array
    {
        return $this->account($allocation['account'], $allocation['balance']) + [
            'available' => $allocation['available'],
            'unallocated' => $allocation['unallocated'],
            'projects' => array_map(static fn (array $a) => [
                'id' => $a['project']->getId(),
                'name' => $a['project']->getName(),
                'target' => $a['project']->getTarget(),
                'priority' => $a['project']->getPriority(),
                'allocated' => $a['allocated'],
                'fundable' => $a['fundable'],
            ], $allocation['projects']),
        ];
    }

    /** @return array<string, mixed> */
    public function achievedProject(SavingsProject $project): array
    {
        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'target' => $project->getTarget(),
            'accountName' => $project->getAccount()->getName(),
            'achievedAt' => $project->getAchievedAt()?->format(\DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function category(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'kind' => $category->getKind()->value,
            'color' => $category->getColor(),
            'monthlyBudget' => $category->getMonthlyBudget(),
            'pocketMoney' => Category::POCKET_MONEY === $category->getCode(),
        ];
    }

    /** @return array<string, mixed> */
    public function operation(Operation $operation): array
    {
        return [
            'id' => $operation->getId(),
            'accountId' => $operation->getAccount()->getId(),
            'categoryId' => $operation->getCategory()?->getId(),
            'amount' => $operation->getAmount(),
            'label' => $operation->getLabel(),
            'date' => $operation->getDate()->format('Y-m-d'),
            'createdBy' => $operation->getCreatedBy()?->getDisplayName(),
            'recurringId' => $operation->getRecurring()?->getId(),
            'transfer' => null === $operation->getTransfer() ? null : [
                'id' => $operation->getTransfer()->getId(),
                // The other account: where the money went, or where it came from
                'counterpartAccountId' => $operation->getTransfer()->counterpartOf($operation)->getId(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function transfer(Transfer $transfer): array
    {
        return [
            'id' => $transfer->getId(),
            'fromAccountId' => $transfer->getFrom()->getId(),
            'toAccountId' => $transfer->getTo()->getId(),
            'amount' => $transfer->getAmount(),
            'label' => $transfer->getLabel(),
            'date' => $transfer->getDate()->format('Y-m-d'),
        ];
    }

    /** @return array<string, mixed> */
    public function recurringOperation(RecurringOperation $recurring): array
    {
        return [
            'id' => $recurring->getId(),
            'accountId' => $recurring->getAccount()->getId(),
            'categoryId' => $recurring->getCategory()?->getId(),
            'amount' => $recurring->getAmount(),
            'label' => $recurring->getLabel(),
            'frequency' => $recurring->getFrequency()->value,
            'nextOn' => $recurring->getNextOn()->format('Y-m-d'),
            'endsOn' => $recurring->getEndsOn()?->format('Y-m-d'),
            'active' => $recurring->isActive(),
            'finished' => $recurring->isFinished(),
        ];
    }

    /** @return array<string, mixed> */
    public function allowance(Allowance $allowance): array
    {
        return [
            'amount' => $allowance->getAmount(),
            'frequency' => $allowance->getFrequency()->value,
            'nextPaymentOn' => $allowance->getNextPaymentOn()->format('Y-m-d'),
            'accountId' => $allowance->getAccount()?->getId(),
            'active' => $allowance->isActive(),
        ];
    }

    /** @return array<string, mixed> a child, with their pocket money balance */
    public function child(User $child, int $balance, ?Allowance $allowance): array
    {
        return $this->user($child) + [
            'balance' => $balance,
            'advanceOwed' => $child->getAdvanceOwed(),
            'allowance' => null === $allowance ? null : $this->allowance($allowance),
        ];
    }

    /** @return array<string, mixed> */
    public function pocketMoneyEntry(PocketMoneyEntry $entry, User $viewer): array
    {
        return [
            'id' => $entry->getId(),
            'kind' => $entry->getKind()->value,
            'amount' => $entry->getAmount(),
            'label' => $entry->getLabel(),
            'date' => $entry->getDate()->format('Y-m-d'),
            'createdBy' => $entry->getCreatedBy()?->getDisplayName(),
            'canDelete' => $entry->canBeDeletedBy($viewer),
        ];
    }

    /** @return array<string, mixed> */
    public function request(MoneyRequest $request): array
    {
        return [
            'id' => $request->getId(),
            'child' => $this->user($request->getChild()),
            'kind' => $request->getKind()->value,
            'amount' => $request->getAmount(),
            'reason' => $request->getReason(),
            'status' => $request->getStatus()->value,
            'createdAt' => $request->getCreatedAt()->format(\DATE_ATOM),
            'decidedBy' => $request->getDecidedBy()?->getDisplayName(),
            'decidedAt' => $request->getDecidedAt()?->format(\DATE_ATOM),
            'answer' => $request->getAnswer(),
        ];
    }
}
