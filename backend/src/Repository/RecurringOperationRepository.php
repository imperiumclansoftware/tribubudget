<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\RecurringOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RecurringOperation> */
class RecurringOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurringOperation::class);
    }

    /** @return list<RecurringOperation> the next ones first */
    public function forFamily(Family $family): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('a')
            ->join('r.account', 'a')
            ->where('a.family = :family')
            ->setParameter('family', $family)
            ->orderBy('r.active', 'DESC')
            ->addOrderBy('r.nextOn')
            ->addOrderBy('r.label')
            ->getQuery()
            ->getResult();
    }

    /**
     * Active recurring operations whose next date is on or before $until, the soonest first.
     *
     * @return list<RecurringOperation>
     */
    public function upcoming(Family $family, \DateTimeImmutable $until): array
    {
        return array_values(array_filter(
            $this->forFamily($family),
            static fn (RecurringOperation $r) => $r->isActive() && !$r->isFinished() && $r->getNextOn() <= $until,
        ));
    }

    /** @return list<Family> families with a recurring operation to record by $today */
    public function familiesWithDueOperations(\DateTimeImmutable $today): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('f')
            ->from(Family::class, 'f')
            ->where('EXISTS (SELECT 1 FROM App\Entity\RecurringOperation r JOIN r.account a WHERE a.family = f AND r.active = true AND r.nextOn <= :today AND (r.endsOn IS NULL OR r.nextOn <= r.endsOn))')
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }
}
