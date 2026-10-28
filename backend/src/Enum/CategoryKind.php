<?php

namespace App\Enum;

enum CategoryKind: string
{
    case Expense = 'expense';
    case Income = 'income';
}
