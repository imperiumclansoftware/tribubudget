<?php

namespace App\Api;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Finds the accounts, categories and teenagers referenced by id in a payload, among the family's own. */
final class FamilyLookup
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly CategoryRepository $categories,
        private readonly UserRepository $users,
    ) {
    }

    /** An account $me may use: any account of the family for a parent, their own for a teenager */
    public function account(?int $id, User $me): ?Account
    {
        if (null === $id) {
            return null;
        }
        $criteria = ['id' => $id, 'family' => $me->getFamily()];
        if (!$me->isParent()) {
            $criteria['owner'] = $me;
        }

        return $this->accounts->findOneBy($criteria)
            ?? throw new UnprocessableEntityHttpException('Ce compte n’existe pas.');
    }

    /** An account of the household budget, not a teenager's */
    public function householdAccount(?int $id, User $me): ?Account
    {
        if (null === $id) {
            return null;
        }

        return $this->accounts->findOneBy(['id' => $id, 'family' => $me->getFamily(), 'owner' => null])
            ?? throw new UnprocessableEntityHttpException('Choisissez un compte du foyer.');
    }

    public function teen(?int $id, User $me): ?User
    {
        if (null === $id) {
            return null;
        }
        $teen = $this->users->findOneBy(['id' => $id, 'family' => $me->getFamily()]);

        return true === $teen?->isTeen() ? $teen : throw new UnprocessableEntityHttpException('Cet adolescent n’existe pas.');
    }

    public function category(?int $id, User $me): ?Category
    {
        if (null === $id) {
            return null;
        }

        return $this->categories->findOneBy(['id' => $id, 'family' => $me->getFamily()])
            ?? throw new UnprocessableEntityHttpException('Cette catégorie n’existe pas.');
    }
}
