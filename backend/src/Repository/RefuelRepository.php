<?php

namespace App\Repository;

use App\Entity\Refuel;
use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Refuel> */
class RefuelRepository extends ServiceEntityRepository
{
    private const HISTORY_SIZE = 100;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Refuel::class);
    }

    /** @return list<Refuel> the latest first */
    public function history(Vehicle $vehicle): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('o')
            ->join('r.operation', 'o')
            ->where('r.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('o.date', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(self::HISTORY_SIZE)
            ->getQuery()
            ->getResult();
    }

    /**
     * Actual consumption over the fill-ups with an odometer reading, in hundredths of litre (or
     * kWh) per 100 km: what was put in after the first of them, over the distance driven since.
     * Null below two readings.
     */
    public function consumption(Vehicle $vehicle): ?int
    {
        $row = $this->createQueryBuilder('r')
            ->select('MIN(r.odometer) AS first, MAX(r.odometer) AS last, SUM(r.volume) AS volume, COUNT(r.id) AS fills')
            ->where('r.vehicle = :vehicle AND r.odometer IS NOT NULL')
            ->setParameter('vehicle', $vehicle)
            ->getQuery()
            ->getSingleResult();
        if ((int) $row['fills'] < 2 || $row['last'] <= $row['first']) {
            return null;
        }
        // The first fill-up only filled the tank the distance is measured from
        $first = $this->findOneBy(['vehicle' => $vehicle, 'odometer' => $row['first']]);
        $volume = (int) $row['volume'] - ($first?->getVolume() ?? 0);

        return (int) round($volume * 100 / ((int) $row['last'] - (int) $row['first']));
    }
}
