<?php

namespace App\Money;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\MoneyRequest;
use App\Entity\Operation;
use App\Entity\PocketMoneyEntry;
use App\Entity\User;
use App\Enum\CategoryKind;
use App\Enum\PocketMoneyKind;
use App\Enum\RequestKind;
use App\Repository\CategoryRepository;
use App\Repository\PocketMoneyEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Movements of the children's pocket money. Money given by the parents (allowances, advances)
 * can be taken from a household account: the expense is then recorded there too.
 */
final class PocketMoney
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PocketMoneyEntryRepository $entries,
        private readonly CategoryRepository $categories,
        private readonly Today $today,
    ) {
    }

    /** The child spends their own money: never more than they have. */
    public function spend(User $child, int $amount, string $label, \DateTimeImmutable $date, User $by): PocketMoneyEntry
    {
        if ($amount > $this->entries->balance($child)) {
            throw new UnprocessableEntityHttpException($by === $child ? 'Tu n’as pas assez d’argent de poche pour cette dépense.' : $child->getDisplayName().' n’a pas assez d’argent de poche pour cette dépense.');
        }

        return $this->record($child, PocketMoneyKind::Expense, -$amount, $label, $date, $by);
    }

    /** Gift or correction by a parent: positive to credit, negative to debit. */
    public function adjust(User $child, int $amount, string $label, User $parent): PocketMoneyEntry
    {
        return $this->record($child, PocketMoneyKind::Adjustment, $amount, $label, $this->today->date(), $parent);
    }

    /** Pays one allowance, keeping back what is needed to repay the advances. */
    public function payAllowance(User $child, int $amount, \DateTimeImmutable $date, ?Account $account): void
    {
        $this->record($child, PocketMoneyKind::Allowance, $amount, 'Argent de poche', $date);
        $this->takeFromHousehold($account, $amount, 'Argent de poche de '.$child->getDisplayName(), $date);

        $repaid = $child->repay($amount);
        if ($repaid > 0) {
            $this->record($child, PocketMoneyKind::Repayment, -$repaid, 'Remboursement d’avance', $date);
        }
    }

    /** Applies an accepted request: an advance is credited now and repaid on the next allowances. */
    public function grant(MoneyRequest $request, User $parent, ?Account $account): void
    {
        if (RequestKind::Advance !== $request->getKind()) {
            return;
        }
        $child = $request->getChild();
        $today = $this->today->date();
        $this->record($child, PocketMoneyKind::Advance, $request->getAmount(), 'Avance : '.$request->getReason(), $today, $parent);
        $this->takeFromHousehold($account, $request->getAmount(), 'Avance à '.$child->getDisplayName(), $today, $parent);
        $child->owe($request->getAmount());
    }

    private function record(User $child, PocketMoneyKind $kind, int $amount, string $label, \DateTimeImmutable $date, ?User $by = null): PocketMoneyEntry
    {
        $entry = new PocketMoneyEntry($child, $kind, $amount, mb_substr($label, 0, PocketMoneyEntry::LABEL_MAX_LENGTH), $date, $by);
        $this->em->persist($entry);

        return $entry;
    }

    /**
     * Records money given by the parents as an expense of a household account: in the pocket money
     * category unless another one is given. Nothing without an account.
     */
    public function takeFromHousehold(?Account $account, int $amount, string $label, \DateTimeImmutable $date, ?User $by = null, ?Category $category = null): void
    {
        if (null === $account || $amount <= 0) {
            return;
        }
        $this->em->persist(new Operation($account, $category ?? $this->pocketMoneyCategory($account), -$amount, mb_substr($label, 0, Operation::LABEL_MAX_LENGTH), $date, $by));
    }

    /** Recreated if the parents deleted it */
    private function pocketMoneyCategory(Account $account): Category
    {
        $category = $this->categories->pocketMoney($account->getFamily());
        if (null === $category) {
            $category = new Category($account->getFamily(), 'Argent de poche', CategoryKind::Expense, '#f4a261', Category::POCKET_MONEY);
            $this->em->persist($category);
        }

        return $category;
    }
}
