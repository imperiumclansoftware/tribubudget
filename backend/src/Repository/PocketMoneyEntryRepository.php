<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\PocketMoneyEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PocketMoneyEntry> */
class PocketMoneyEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PocketMoneyEntry::class);
    }

    public function balance(User $child): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COALESCE(SUM(e.amount), 0)')
            ->where('e.child = :child')
            ->setParameter('child', $child)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<int, int> child id => balance, for the children who have entries */
    public function balances(Family $family): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.child) AS child, SUM(e.amount) AS balance')
            ->join('e.child', 'u')
            ->where('u.family = :family')
            ->setParameter('family', $family)
            ->groupBy('e.child')
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'balance', 'child'));
    }

    /** @return list<PocketMoneyEntry> most recent first */
    public function history(User $child, int $limit = 200): array
    {
        return $this->findBy(['child' => $child], ['date' => 'DESC', 'id' => 'DESC'], $limit);
    }
}
