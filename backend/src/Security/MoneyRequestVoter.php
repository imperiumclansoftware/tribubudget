<?php

namespace App\Security;

use App\Entity\MoneyRequest;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Children ask, parents answer. A child may withdraw their own request while it is pending.
 *
 * @extends Voter<string, MoneyRequest|null>
 */
final class MoneyRequestVoter extends Voter
{
    public const CREATE = 'REQUEST_CREATE';
    public const DECIDE = 'REQUEST_DECIDE';
    public const CANCEL = 'REQUEST_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::CREATE === $attribute || (\in_array($attribute, [self::DECIDE, self::CANCEL], true) && $subject instanceof MoneyRequest);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $me = $token->getUser();
        if (!$me instanceof User) {
            return false;
        }
        if (self::CREATE === $attribute) {
            return $me->isChild();
        }
        if ($me->getFamily() !== $subject->getFamily() || !$subject->isPending()) {
            return false;
        }

        return match ($attribute) {
            self::DECIDE => $me->isParent(),
            default => $subject->getChild() === $me,
        };
    }
}
