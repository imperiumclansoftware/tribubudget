<?php

namespace App\Tests\Money;

use App\Enum\Frequency;
use PHPUnit\Framework\TestCase;

final class FrequencyTest extends TestCase
{
    public function testMonthlyPaymentsKeepTheirDayOfMonth(): void
    {
        $dates = [];
        $date = new \DateTimeImmutable('2026-01-31');
        for ($i = 0; $i < 4; ++$i) {
            $date = Frequency::Monthly->next($date, 31);
            $dates[] = $date->format('Y-m-d');
        }

        self::assertSame(['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'], $dates);
    }

    public function testWeeklyPayments(): void
    {
        self::assertSame('2026-11-04', Frequency::Weekly->next(new \DateTimeImmutable('2026-10-28'), 28)->format('Y-m-d'));
    }

    public function testQuarterlyAndYearlyDatesKeepTheirDayOfMonth(): void
    {
        self::assertSame('2026-02-28', Frequency::Quarterly->next(new \DateTimeImmutable('2025-11-30'), 30)->format('Y-m-d'));
        self::assertSame('2028-02-29', Frequency::Yearly->next(new \DateTimeImmutable('2027-02-28'), 29)->format('Y-m-d'));
    }
}
