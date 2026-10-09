<?php

namespace App\Modules\Withdrawals\Services;

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Models\WalletBalance;
use App\Modules\Wallets\Services\WalletBalanceService;
use App\Modules\Withdrawals\Enums\HoldStatus;
use App\Modules\Withdrawals\Exceptions\HoldReleaseUnsafeException;
use App\Modules\Withdrawals\Models\WalletHold;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WalletHoldService
{
    public function __construct(private readonly WalletBalanceService $balances) {}

    /**
     * Reserve funds: create an active hold and increase the wallet's reserved
     * balance. The balance row is locked for the duration of the caller's
     * transaction so concurrent debits cannot overdraw available funds.
     */
    public function reserve(Wallet $wallet, int $amount, ?string $withdrawalId = null, ?Carbon $expiresAt = null): WalletHold
    {
        $balance = $this->lockBalance($wallet);

        $this->balances->assertCanDebit($wallet, $balance, $amount);

        $hold = WalletHold::create([
            'reference' => 'HLD'.strtoupper((string) Str::ulid()),
            'wallet_id' => $wallet->id,
            'currency_code' => $wallet->currency_code,
            'amount' => $amount,
            'status' => HoldStatus::Active,
            'withdrawal_id' => $withdrawalId,
            'expires_at' => $expiresAt,
        ]);

        $this->balances->reserve($balance, $amount);

        return $hold;
    }

    /**
     * Consume a hold when its reserve materialises as a posted movement.
     */
    public function consume(WalletHold $hold): WalletHold
    {
        $this->assertActive($hold, 'consumed');

        $balance = $this->lockBalanceById($hold->wallet_id);
        $this->balances->consumeReserved($balance, $hold->amount);

        $hold->forceFill([
            'status' => HoldStatus::Consumed,
            'settled_at' => now(),
        ])->save();

        return $hold;
    }

    /**
     * Release a hold when the movement will never post (failed or cancelled).
     */
    public function release(WalletHold $hold): WalletHold
    {
        $this->assertActive($hold, 'released');

        $balance = $this->lockBalanceById($hold->wallet_id);
        $this->balances->release($balance, $hold->amount);

        $hold->forceFill([
            'status' => HoldStatus::Released,
            'settled_at' => now(),
        ])->save();

        return $hold;
    }

    public function expire(WalletHold $hold): WalletHold
    {
        $this->assertActive($hold, 'expired');

        if ($hold->expires_at === null || $hold->expires_at->isFuture()) {
            throw new DomainException(
                ErrorCode::HoldExpired,
                'Hold cannot be expired before its expiry time.',
                ['hold_id' => $hold->id],
            );
        }

        $balance = $this->lockBalanceById($hold->wallet_id);
        $this->balances->release($balance, $hold->amount);

        $hold->forceFill([
            'status' => HoldStatus::Expired,
            'settled_at' => now(),
        ])->save();

        return $hold;
    }

    private function assertActive(WalletHold $hold, string $action): void
    {
        if (! $hold->status->isActive()) {
            throw new HoldReleaseUnsafeException($hold, $action);
        }
    }

    private function lockBalance(Wallet $wallet): WalletBalance
    {
        return $this->lockBalanceById($wallet->id);
    }

    private function lockBalanceById(string $walletId): WalletBalance
    {
        $balance = WalletBalance::query()->where('wallet_id', $walletId)->lockForUpdate()->first();

        if ($balance) {
            return $balance;
        }

        $wallet = Wallet::query()->findOrFail($walletId);

        return $this->balances->ensureFor($wallet);
    }
}
