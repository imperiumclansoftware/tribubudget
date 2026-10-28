<?php

namespace App\Security;

use App\Entity\PocketMoneyEntry;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @see PocketMoneyEntry::canBeDeletedBy()
 *
 * @extends Voter<string, PocketMoneyEntry>
 */
final class PocketMoneyEntryVoter extends Voter
{
    public const DELETE = 'POCKET_MONEY_ENTRY_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof PocketMoneyEntry;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $me = $token->getUser();

        return $me instanceof User && $subject->canBeDeletedBy($me);
    }
}
