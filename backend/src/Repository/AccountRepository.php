<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Family;
use App\Entity\User;
use App\Enum\AccountType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Account> */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /** @return list<Account> the household's accounts and the teenagers' */
    public function forFamily(Family $family): array
    {
        return $this->findBy(['family' => $family], ['name' => 'ASC']);
    }

    /** @return list<Account> the accounts of the household budget */
    public function household(Family $family): array
    {
        return $this->findBy(['family' => $family, 'owner' => null], ['name' => 'ASC']);
    }

    /** @return list<Account> */
    public function ownedBy(User $teen): array
    {
        return $this->findBy(['owner' => $teen], ['name' => 'ASC']);
    }

    /** Where the money given to a teenager arrives: their first current account, or any of theirs */
    public function mainAccountOf(User $teen): ?Account
    {
        return $this->findOneBy(['owner' => $teen, 'type' => AccountType::Checking], ['id' => 'ASC'])
            ?? $this->findOneBy(['owner' => $teen], ['id' => 'ASC']);
    }

    /**
     * Current balance of each account of the family, in cents; with $reconciled, of the operations
     * ticked off against the bank statement only, to compare with it.
     *
     * @return array<int, int> account id => balance
     */
    public function balances(Family $family, bool $reconciled = false): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.id, a.initialBalance + COALESCE(SUM(o.amount), 0) AS balance')
            ->leftJoin('App\Entity\Operation', 'o', 'WITH', $reconciled ? 'o.account = a AND o.reconciled = true' : 'o.account = a')
            ->where('a.family = :family')
            ->setParameter('family', $family)
            ->groupBy('a.id')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $row) => ['id' => $row['id'], 'balance' => (int) $row['balance']], $rows), 'balance', 'id');
    }
}
