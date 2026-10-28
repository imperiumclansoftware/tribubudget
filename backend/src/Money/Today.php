<?php

namespace App\Money;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Today's date in the family's time zone (APP_TIMEZONE), at midnight in PHP's default time zone:
 * the time zone in which Doctrine reads the date columns, so that both compare as dates.
 */
final class Today
{
    public function __construct(
        private readonly ClockInterface $clock,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    public function date(): \DateTimeImmutable
    {
        $day = $this->clock->now()->setTimezone(new \DateTimeZone($this->timezone))->format('Y-m-d');

        return new \DateTimeImmutable($day);
    }
}
