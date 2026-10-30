<?php

namespace App\Money;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\MoneyRequest;
use App\Entity\Operation;
use App\Entity\User;
use App\Enum\AccountType;
use App\Enum\RequestKind;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Money the parents give a teenager (allowance, advance, reimbursement): it arrives on one of the
 * teenager's accounts and, when taken from a household account, is recorded there as an expense,
 * the teenager's accounts standing outside the household budget.
 */
final class TeenMoney
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository $accounts,
        private readonly PocketMoney $pocketMoney,
        private readonly Today $today,
    ) {
    }

    /** A new teenager starts with a current account and a savings account, both empty. */
    public function openAccounts(User $teen): void
    {
        $this->em->persist(new Account($teen->getFamily(), 'Compte courant', AccountType::Checking, owner: $teen));
        $this->em->persist(new Account($teen->getFamily(), 'Épargne', AccountType::Savings, owner: $teen));
    }

    /** Pays one allowance, keeping back what is needed to repay the advances. */
    public function payAllowance(User $teen, int $amount, \DateTimeImmutable $date, ?Account $from): void
    {
        $account = $this->mainAccount($teen);
        $this->credit($account, $amount, 'Argent de poche', $date);
        $repaid = $teen->repay($amount);
        if ($repaid > 0) {
            $this->credit($account, -$repaid, 'Remboursement d’avance', $date);
        }
        // The household only pays what the teenager actually receives
        $this->pocketMoney->takeFromHousehold($from, $amount - $repaid, 'Argent de poche de '.$teen->getDisplayName(), $date);
    }

    /**
     * Applies an accepted request: an advance is credited now and repaid on the next allowances;
     * a reimbursement is credited on the account of the expense, recorded as an expense of the
     * household in $category.
     */
    public function grant(MoneyRequest $request, User $parent, ?Account $from, ?Category $category): void
    {
        $teen = $request->getChild();
        $today = $this->today->date();
        if (RequestKind::Advance === $request->getKind()) {
            $this->credit($this->mainAccount($teen), $request->getAmount(), 'Avance : '.$request->getReason(), $today, $parent);
            $this->pocketMoney->takeFromHousehold($from, $request->getAmount(), 'Avance à '.$teen->getDisplayName(), $today, $parent);
            $teen->owe($request->getAmount());
        } elseif (RequestKind::Reimbursement === $request->getKind()) {
            $account = $request->getOperation()?->getAccount() ?? $this->mainAccount($teen);
            $this->credit($account, $request->getAmount(), 'Remboursement : '.$request->getReason(), $today, $parent);
            if (null !== $from) {
                $label = mb_substr('Remboursement à '.$teen->getDisplayName().' : '.$request->getReason(), 0, Operation::LABEL_MAX_LENGTH);
                $this->em->persist(new Operation($from, $category, -$request->getAmount(), $label, $today, $parent));
            }
        }
    }

    /** Where the money given arrives: their current account, opened again if they have none left */
    private function mainAccount(User $teen): Account
    {
        $account = $this->accounts->mainAccountOf($teen);
        if (null === $account) {
            $account = new Account($teen->getFamily(), 'Compte courant', AccountType::Checking, owner: $teen);
            $this->em->persist($account);
        }

        return $account;
    }

    private function credit(Account $account, int $amount, string $label, \DateTimeImmutable $date, ?User $by = null): void
    {
        $this->em->persist(new Operation($account, null, $amount, mb_substr($label, 0, Operation::LABEL_MAX_LENGTH), $date, $by));
    }
}
