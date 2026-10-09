<?php

namespace App\Modules\PaymentProviders\Contracts;

use App\Modules\PaymentProviders\DTOs\PayoutResult;
use App\Modules\Withdrawals\Models\Withdrawal;

interface PayoutProviderInterface
{
    /**
     * Stable provider key, e.g. "fake".
     */
    public function name(): string;

    /**
     * Submit a payout instruction to the provider. Providers may settle
     * synchronously (succeeded/failed) or asynchronously (processing).
     */
    public function initiate(Withdrawal $withdrawal): PayoutResult;
}
