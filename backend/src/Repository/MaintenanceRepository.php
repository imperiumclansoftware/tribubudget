<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\Maintenance;
use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Maintenance> */
class MaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Maintenance::class);
    }

    /** @return list<Maintenance> the ones still to do first, by date; then the latest done */
    public function forVehicle(Vehicle $vehicle): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('o')
            ->leftJoin('m.operation', 'o')
            ->where('m.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('CASE WHEN m.operation IS NULL THEN 0 ELSE 1 END')
            ->addOrderBy('CASE WHEN m.operation IS NULL THEN m.plannedOn ELSE o.date END', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Maintenance> still to do, planned before $end (excluded): overdue ones included */
    public function dueBefore(Family $family, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.vehicle', 'v')
            ->where('v.family = :family AND m.operation IS NULL AND m.plannedOn < :end')
            ->setParameter('family', $family)
            ->setParameter('end', $end->format('Y-m-d'))
            ->orderBy('m.plannedOn')
            ->getQuery()
            ->getResult();
    }
}
