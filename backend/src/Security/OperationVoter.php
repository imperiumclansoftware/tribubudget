<?php

namespace App\Security;

use App\Entity\Account;
use App\Entity\Operation;
use App\Entity\Transfer;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Parents change any operation or transfer of the family. A teenager only changes what they
 * recorded themselves on their own accounts, never what the parents put there (allowance,
 * reimbursement, transfer from the household). Ticking an operation off against the bank
 * statement changes nothing of it: a teenager ticks off any operation of their accounts.
 *
 * @extends Voter<string, Operation|Transfer>
 */
final class OperationVoter extends Voter
{
    public const EDIT = 'OPERATION_EDIT';
    public const RECONCILE = 'OPERATION_RECONCILE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (self::EDIT === $attribute && ($subject instanceof Operation || $subject instanceof Transfer))
            || (self::RECONCILE === $attribute && $subject instanceof Operation);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $me = $token->getUser();
        if (!$me instanceof User || $me->getFamily() !== $subject->getFamily()) {
            return false;
        }
        if ($me->isParent()) {
            return true;
        }
        if (self::RECONCILE === $attribute) {
            return $subject->getAccount()->getOwner() === $me;
        }
        $accounts = $subject instanceof Transfer ? [$subject->getFrom(), $subject->getTo()] : [$subject->getAccount()];

        return $me->isTeen()
            && $subject->getCreatedBy() === $me
            && [] === array_filter($accounts, static fn (Account $a) => $a->getOwner() !== $me);
    }
}
