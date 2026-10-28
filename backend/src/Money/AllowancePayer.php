<?php

namespace App\Money;

use App\Entity\Family;
use App\Repository\AllowanceRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Pays the allowances that are due, including the ones missed since the last run (see Schedules). */
final class AllowancePayer
{
    /** Never catch up on more than this many payments at once (two years of weekly payments) */
    private const MAX_CATCH_UP = 104;

    public function __construct(
        private readonly AllowanceRepository $allowances,
        private readonly PocketMoney $pocketMoney,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return int number of payments made, not flushed yet */
    public function payDue(Family $family, \DateTimeImmutable $today): int
    {
        $paid = 0;
        foreach ($this->allowances->forFamily($family) as $allowance) {
            // Reload: another process may have paid it before Schedules acquired the lock
            $this->em->refresh($allowance);
            for ($i = 0; $allowance->isDue($today) && $i < self::MAX_CATCH_UP; ++$i) {
                $this->pocketMoney->payAllowance($allowance->getChild(), $allowance->getAmount(), $allowance->getNextPaymentOn(), $allowance->getAccount());
                $allowance->advance();
                ++$paid;
            }
        }

        return $paid;
    }
}
