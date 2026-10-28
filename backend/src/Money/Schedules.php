<?php

namespace App\Money;

use App\Entity\Family;
use App\Repository\AllowanceRepository;
use App\Repository\RecurringOperationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Catches up with what falls on fixed dates, allowances and recurring operations, including the
 * dates missed since the last run: called by the app:schedules:run command (cron) and whenever
 * a member of the family opens the application.
 */
final class Schedules
{
    public function __construct(
        private readonly AllowancePayer $allowances,
        private readonly RecurringOperationRecorder $recurring,
        private readonly AllowanceRepository $allowanceRepository,
        private readonly RecurringOperationRepository $recurringRepository,
        private readonly EntityManagerInterface $em,
        private readonly LockFactory $locks,
        private readonly Today $today,
    ) {
    }

    /** @return array{allowances: int, operations: int} number of payments and operations recorded */
    public function catchUp(Family $family): array
    {
        // Two devices opening the app together must not record the same date twice: the second
        // waits, then finds everything already recorded
        $lock = $this->locks->createLock('schedules-'.$family->getId());
        $lock->acquire(true);
        try {
            $today = $this->today->date();
            $done = [
                'allowances' => $this->allowances->payDue($family, $today),
                'operations' => $this->recurring->recordDue($family, $today),
            ];
            if (array_sum($done) > 0) {
                $this->em->flush();
            }

            return $done;
        } finally {
            $lock->release();
        }
    }

    /** @return array{allowances: int, operations: int} */
    public function catchUpAll(): array
    {
        $today = $this->today->date();
        $families = [];
        foreach ([...$this->allowanceRepository->familiesWithDuePayments($today), ...$this->recurringRepository->familiesWithDueOperations($today)] as $family) {
            $families[$family->getId()] = $family;
        }

        $done = ['allowances' => 0, 'operations' => 0];
        foreach ($families as $family) {
            $result = $this->catchUp($family);
            $done['allowances'] += $result['allowances'];
            $done['operations'] += $result['operations'];
        }

        return $done;
    }
}
