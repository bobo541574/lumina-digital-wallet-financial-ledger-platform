<?php

namespace App\Modules\PaymentProviders\Adapters;

use App\Modules\PaymentProviders\Contracts\PayoutProviderInterface;
use App\Modules\PaymentProviders\DTOs\PayoutResult;
use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use App\Modules\Withdrawals\Models\Withdrawal;
use Illuminate\Support\Str;

/**
 * In-memory payout provider for local development and tests. Real providers are
 * asynchronous, so the default outcome is `processing`; settlement arrives via
 * webhook or reconciliation.
 */
class FakePayoutProvider implements PayoutProviderInterface
{
    public function name(): string
    {
        return 'fake';
    }

    public function initiate(Withdrawal $withdrawal): PayoutResult
    {
        return new PayoutResult(
            status: WithdrawalStatus::Processing,
            providerReference: 'PO'.strtoupper((string) Str::ulid()),
        );
    }
}
