<?php

namespace App\Money;

use App\Entity\Family;
use App\Repository\RecurringOperationRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Records the recurring operations that are due, including the dates missed since the last run (see Schedules). */
final class RecurringOperationRecorder
{
    /** Never catch up on more than this many dates at once (two years of weekly operations) */
    private const MAX_CATCH_UP = 104;

    public function __construct(
        private readonly RecurringOperationRepository $recurring,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return int number of operations recorded, not flushed yet */
    public function recordDue(Family $family, \DateTimeImmutable $today): int
    {
        $recorded = 0;
        foreach ($this->recurring->forFamily($family) as $recurring) {
            // Reload: another process may have recorded it before Schedules acquired the lock
            $this->em->refresh($recurring);
            for ($i = 0; $recurring->isDue($today) && $i < self::MAX_CATCH_UP; ++$i) {
                $this->em->persist($recurring->occur());
                ++$recorded;
            }
        }

        return $recorded;
    }
}
