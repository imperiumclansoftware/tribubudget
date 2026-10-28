<?php

namespace App\Repository;

use App\Entity\Allowance;
use App\Entity\Family;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Allowance> */
class AllowanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Allowance::class);
    }

    public function forChild(User $child): ?Allowance
    {
        return $this->findOneBy(['child' => $child]);
    }

    /** @return list<Allowance> */
    public function forFamily(Family $family): array
    {
        return $this->createQueryBuilder('al')
            ->join('al.child', 'u')
            ->where('u.family = :family')
            ->setParameter('family', $family)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Family> families with an allowance to pay by $today */
    public function familiesWithDuePayments(\DateTimeImmutable $today): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('f')
            ->from(Family::class, 'f')
            ->where('EXISTS (SELECT 1 FROM App\Entity\Allowance al JOIN al.child u WHERE u.family = f AND al.active = true AND al.nextPaymentOn <= :today)')
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }
}
