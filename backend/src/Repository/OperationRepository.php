<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Family;
use App\Entity\Operation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Operation> */
class OperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Operation::class);
    }

    /**
     * Operations dated from $from (included) to $to (excluded), most recent first: of $account, or
     * else of every account of $owner (a teenager), or else of every household account.
     *
     * @return list<Operation>
     */
    public function between(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to, ?Account $account = null, ?Category $category = null, ?User $owner = null): array
    {
        $qb = $this->period($family, $from, $to)
            ->addSelect('a', 'c')
            ->leftJoin('o.category', 'c')
            ->orderBy('o.date', 'DESC')
            ->addOrderBy('o.id', 'DESC');
        if (null !== $account) {
            $qb->andWhere('o.account = :account')->setParameter('account', $account);
        } elseif (null !== $owner) {
            $qb->andWhere('a.owner = :owner')->setParameter('owner', $owner);
        } else {
            $qb->andWhere('a.owner IS NULL');
        }
        if (null !== $category) {
            $qb->andWhere('o.category = :category')->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return array{income: int, expenses: int} expenses as a positive amount; transfers left out */
    public function totals(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $row = $this->budgetPeriod($family, $from, $to)
            ->select('COALESCE(SUM(CASE WHEN o.amount > 0 THEN o.amount ELSE 0 END), 0) AS income')
            ->addSelect('COALESCE(SUM(CASE WHEN o.amount < 0 THEN -o.amount ELSE 0 END), 0) AS expenses')
            ->getQuery()
            ->getSingleResult();

        return ['income' => (int) $row['income'], 'expenses' => (int) $row['expenses']];
    }

    /**
     * Net spending of each category over the period (refunds deducted), as a positive amount.
     *
     * @return array<int, int> category id => spent
     */
    public function spentByCategory(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->budgetPeriod($family, $from, $to)
            ->select('IDENTITY(o.category) AS category, -SUM(o.amount) AS spent')
            ->andWhere('o.category IS NOT NULL')
            ->groupBy('o.category')
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'spent', 'category'));
    }

    /** Expenses without a category over the period, as a positive amount; transfers left out. */
    public function uncategorizedExpenses(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->budgetPeriod($family, $from, $to)
            ->select('COALESCE(-SUM(o.amount), 0)')
            ->andWhere('o.category IS NULL AND o.amount < 0')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countForAccount(Account $account): int
    {
        return $this->count(['account' => $account]);
    }

    /**
     * Money moved between the family's accounts is neither an income nor an expense, and the
     * teenagers' accounts stay outside the household budget.
     */
    private function budgetPeriod(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): QueryBuilder
    {
        return $this->period($family, $from, $to)->andWhere('o.transfer IS NULL AND a.owner IS NULL');
    }

    private function period(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->join('o.account', 'a')
            ->where('a.family = :family')
            ->andWhere('o.date >= :from AND o.date < :to')
            ->setParameter('family', $family)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'));
    }
}
