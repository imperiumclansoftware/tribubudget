<?php

namespace App\Enum;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Cash = 'cash';
}
