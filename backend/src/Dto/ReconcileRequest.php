<?php

namespace App\Dto;

/** Ticks an operation off against the bank statement, or unticks it. */
final readonly class ReconcileRequest
{
    public function __construct(
        public bool $reconciled = true,
    ) {
    }
}
