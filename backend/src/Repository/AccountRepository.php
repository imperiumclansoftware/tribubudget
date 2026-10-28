<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Account> */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /** @return list<Account> */
    public function forFamily(Family $family): array
    {
        return $this->findBy(['family' => $family], ['name' => 'ASC']);
    }

    /**
     * Current balance of each account of the family, in cents.
     *
     * @return array<int, int> account id => balance
     */
    public function balances(Family $family): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.id, a.initialBalance + COALESCE(SUM(o.amount), 0) AS balance')
            ->leftJoin('App\Entity\Operation', 'o', 'WITH', 'o.account = a')
            ->where('a.family = :family')
            ->setParameter('family', $family)
            ->groupBy('a.id')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $row) => ['id' => $row['id'], 'balance' => (int) $row['balance']], $rows), 'balance', 'id');
    }
}
