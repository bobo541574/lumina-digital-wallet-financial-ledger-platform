<?php

namespace App\Modules\Reconciliation\Enums;

/**
 * How a reconciliation discrepancy is classified (BLUEPRINT run state machine).
 */
enum MismatchClassification: string
{
    case Missing = 'missing';
    case Duplicate = 'duplicate';
    case AmountMismatch = 'amount_mismatch';
    case StatusMismatch = 'status_mismatch';
    case Timing = 'timing';
}
