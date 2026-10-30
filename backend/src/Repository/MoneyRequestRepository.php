<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\MoneyRequest;
use App\Entity\Operation;
use App\Entity\User;
use App\Enum\RequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MoneyRequest> */
class MoneyRequestRepository extends ServiceEntityRepository
{
    private const HISTORY_SIZE = 100;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoneyRequest::class);
    }

    /**
     * Requests of the whole family, or of one child or teenager: the pending ones first, then the latest.
     *
     * @return list<MoneyRequest>
     */
    public function recent(Family $family, ?User $child = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('u')
            ->join('r.child', 'u')
            ->where('u.family = :family')
            ->setParameter('family', $family)
            ->orderBy('CASE WHEN r.status = :pending THEN 0 ELSE 1 END')
            ->addOrderBy('r.createdAt', 'DESC')
            ->setParameter('pending', RequestStatus::Pending->value)
            ->setMaxResults(self::HISTORY_SIZE);
        if (null !== $child) {
            $qb->andWhere('r.child = :child')->setParameter('child', $child);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Latest reimbursement request of each of these operations.
     *
     * @param list<Operation> $operations
     *
     * @return array<int, MoneyRequest> operation id => request
     */
    public function latestForOperations(array $operations): array
    {
        if ([] === $operations) {
            return [];
        }
        $latest = [];
        $requests = $this->createQueryBuilder('r')
            ->where('r.operation IN (:operations)')
            ->setParameter('operations', $operations)
            ->orderBy('r.createdAt')
            ->addOrderBy('r.id')
            ->getQuery()
            ->getResult();
        foreach ($requests as $request) {
            $latest[$request->getOperation()->getId()] = $request;
        }

        return $latest;
    }

    public function latestForOperation(Operation $operation): ?MoneyRequest
    {
        return $this->findOneBy(['operation' => $operation], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function countPending(Family $family): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.child', 'u')
            ->where('u.family = :family AND r.status = :pending')
            ->setParameter('family', $family)
            ->setParameter('pending', RequestStatus::Pending->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
