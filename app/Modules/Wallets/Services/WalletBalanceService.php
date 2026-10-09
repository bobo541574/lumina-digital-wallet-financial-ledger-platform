<?php

namespace App\Modules\Wallets\Services;

use App\Modules\Wallets\Exceptions\InsufficientFundsException;
use App\Modules\Wallets\Exceptions\WalletNotUsableException;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Models\WalletBalance;
use Illuminate\Support\Collection;

class WalletBalanceService
{
    public function ensureFor(Wallet $wallet): WalletBalance
    {
        return WalletBalance::firstOrCreate(
            ['wallet_id' => $wallet->id],
            ['currency_code' => $wallet->currency_code, 'posted_balance' => 0, 'reserved_balance' => 0],
        );
    }

    /**
     * Lock the balance rows for the given wallets in a deterministic (ascending
     * wallet id) order to avoid deadlocks under concurrent operations.
     *
     * @param  array<int, string>  $walletIds
     * @return Collection<string, WalletBalance>
     */
    public function lockMany(array $walletIds): Collection
    {
        $ids = array_values(array_unique($walletIds));
        sort($ids);

        return WalletBalance::whereIn('wallet_id', $ids)
            ->orderBy('wallet_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('wallet_id');
    }

    public function available(WalletBalance $balance): int
    {
        return $balance->posted_balance - $balance->reserved_balance;
    }

    /**
     * Assert an outgoing (debit) movement of `amount` is possible.
     */
    public function assertCanDebit(Wallet $wallet, WalletBalance $balance, int $amount): void
    {
        if (! $wallet->status->canDebit()) {
            throw WalletNotUsableException::forDebit($wallet);
        }

        if ($this->available($balance) < $amount) {
            throw new InsufficientFundsException($this->available($balance), $amount, $wallet->currency_code);
        }
    }

    public function reserve(WalletBalance $balance, int $amount): void
    {
        $balance->reserved_balance += $amount;
        $balance->save();
    }

    public function release(WalletBalance $balance, int $amount): void
    {
        $balance->reserved_balance = max(0, $balance->reserved_balance - $amount);
        $balance->save();
    }

    /**
     * Consume a hold when the reserve materialises as a posted movement: the
     * posted balance is reduced by the ledger posting, so the reserve is simply
     * removed to keep available_balance unchanged.
     */
    public function consumeReserved(WalletBalance $balance, int $amount): void
    {
        $this->release($balance, $amount);
    }
}
