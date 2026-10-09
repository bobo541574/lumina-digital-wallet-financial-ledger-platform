<?php

namespace App\Modules\PaymentProviders\DTOs;

use App\Modules\Withdrawals\Enums\WithdrawalStatus;

final readonly class PayoutResult
{
    public function __construct(
        public WithdrawalStatus $status,
        public ?string $providerReference = null,
        public ?string $providerTransactionId = null,
        public ?string $failureCode = null,
    ) {}
}
