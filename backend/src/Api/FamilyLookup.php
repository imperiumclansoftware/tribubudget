<?php

namespace App\Api;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Finds the accounts and categories referenced by id in a payload, among the family's own. */
final class FamilyLookup
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function account(?int $id, User $me): ?Account
    {
        if (null === $id) {
            return null;
        }

        return $this->accounts->findOneBy(['id' => $id, 'family' => $me->getFamily()])
            ?? throw new UnprocessableEntityHttpException('Ce compte n’existe pas.');
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
