<?php

namespace App\Money;

/** Calendar month, the period of the budgets ("2026-10"). */
final readonly class Month
{
    public const PATTERN = '\d{4}-(0[1-9]|1[0-2])';

    private function __construct(
        public \DateTimeImmutable $start,
    ) {
    }

    public static function of(\DateTimeImmutable $date): self
    {
        return new self($date->modify('first day of this month')->setTime(0, 0));
    }

    /** @param string $month "YYYY-MM", validated against PATTERN */
    public static function parse(string $month): self
    {
        return new self(new \DateTimeImmutable($month.'-01'));
    }

    public function end(): \DateTimeImmutable
    {
        return $this->start->modify('first day of next month');
    }

    public function previous(int $months = 1): self
    {
        return new self($this->start->modify(\sprintf('-%d months', $months)));
    }

    public function key(): string
    {
        return $this->start->format('Y-m');
    }
}
