<?php

namespace App\Enum;

enum RequestKind: string
{
    /** Money credited now to the pocket money, repaid on the next allowances */
    case Advance = 'advance';
    /** Something the child would like the parents to buy */
    case Purchase = 'purchase';
    /** A teenager paid an expense for the family and asks the parents to pay them back */
    case Reimbursement = 'reimbursement';
}
