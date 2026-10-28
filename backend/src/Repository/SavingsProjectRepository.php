<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Family;
use App\Entity\SavingsProject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SavingsProject> */
class SavingsProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavingsProject::class);
    }

    /** @return list<SavingsProject> projects still to carry out, by account then priority */
    public function current(Family $family): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('a')
            ->join('p.account', 'a')
            ->where('a.family = :family AND p.achievedAt IS NULL')
            ->setParameter('family', $family)
            ->orderBy('a.name')
            ->addOrderBy('p.priority')
            ->addOrderBy('p.id')
            ->getQuery()
            ->getResult();
    }

    /** @return list<SavingsProject> projects carried out, the latest first */
    public function achieved(Family $family, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.account', 'a')
            ->where('a.family = :family AND p.achievedAt IS NOT NULL')
            ->setParameter('family', $family)
            ->orderBy('p.achievedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<SavingsProject> projects of the account still to carry out, by priority */
    public function currentOf(Account $account): array
    {
        return $this->findBy(['account' => $account, 'achievedAt' => null], ['priority' => 'ASC', 'id' => 'ASC']);
    }

    public function countCurrentOf(Account $account): int
    {
        return $this->count(['account' => $account, 'achievedAt' => null]);
    }
}
