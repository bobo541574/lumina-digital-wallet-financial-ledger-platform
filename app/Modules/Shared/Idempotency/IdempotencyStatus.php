<?php

namespace App\Modules\Shared\Idempotency;

enum IdempotencyStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
}
