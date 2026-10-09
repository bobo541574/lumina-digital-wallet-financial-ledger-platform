<?php

namespace App\Modules\Withdrawals\Exceptions;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;
use App\Modules\Withdrawals\Models\WalletHold;

class HoldReleaseUnsafeException extends DomainException
{
    public function __construct(WalletHold $hold, string $action)
    {
        parent::__construct(
            ErrorCode::HoldReleaseUnsafe,
            "Hold cannot be {$action} from status [{$hold->status->value}].",
            ['hold_id' => $hold->id, 'status' => $hold->status->value, 'action' => $action],
        );
    }
}
