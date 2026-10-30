<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A teenager sees their own accounts; the parents of the family oversee every teenager's and are
 * the only ones to set their allowance.
 *
 * @extends Voter<string, User>
 */
final class TeenVoter extends Voter
{
    public const VIEW = 'TEEN_VIEW';
    public const MANAGE = 'TEEN_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE], true) && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $me = $token->getUser();
        if (!$me instanceof User || !$subject->isTeen() || $me->getFamily() !== $subject->getFamily()) {
            return false;
        }

        return $me->isParent() || (self::VIEW === $attribute && $me === $subject);
    }
}
