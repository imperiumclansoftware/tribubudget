<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Rights over a child's pocket money: the child sees and spends their own, the parents of the
 * family see and spend everyone's and are the only ones to manage it (gifts, allowance).
 *
 * @extends Voter<string, User>
 */
final class ChildVoter extends Voter
{
    public const VIEW = 'CHILD_VIEW';
    public const SPEND = 'CHILD_SPEND';
    public const MANAGE = 'CHILD_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::SPEND, self::MANAGE], true) && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $me = $token->getUser();
        if (!$me instanceof User || !$subject->isChild() || $me->getFamily() !== $subject->getFamily()) {
            return false;
        }

        return match ($attribute) {
            self::MANAGE => $me->isParent(),
            default => $me->isParent() || $me === $subject,
        };
    }
}
