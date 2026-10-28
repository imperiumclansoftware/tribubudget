<?php

namespace App\Enum;

/** Rhythm of the allowances (weekly or monthly) and of the recurring operations. */
enum Frequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    /**
     * Date following $date. Monthly, quarterly and yearly dates stay on $dayOfMonth, or on the
     * last day of shorter months (31 → 30 April → 31 May), instead of drifting like "+1 month" does.
     */
    public function next(\DateTimeImmutable $date, int $dayOfMonth): \DateTimeImmutable
    {
        $months = match ($this) {
            self::Weekly => 0,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
        if (0 === $months) {
            return $date->modify('+1 week');
        }
        $month = $date->modify('first day of this month')->modify(\sprintf('+%d months', $months));

        return $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min($dayOfMonth, (int) $month->format('t')));
    }
}
