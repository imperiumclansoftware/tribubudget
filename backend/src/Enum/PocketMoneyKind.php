<?php

namespace App\Enum;

enum PocketMoneyKind: string
{
    /** Recurring payment of the allowance */
    case Allowance = 'allowance';
    /** Spent by the child */
    case Expense = 'expense';
    /** Advance granted by a parent, repaid on the next allowances */
    case Advance = 'advance';
    /** Part of an allowance kept back to repay an advance */
    case Repayment = 'repayment';
    /** Credit or debit made by a parent (gift, correction…) */
    case Adjustment = 'adjustment';
}
