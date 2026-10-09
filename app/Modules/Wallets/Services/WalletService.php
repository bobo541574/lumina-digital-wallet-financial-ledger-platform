<?php

namespace App\Modules\Wallets\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\AccountStatus;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Shared\Models\Currency;
use App\Modules\Wallets\Enums\WalletStatus;
use App\Modules\Wallets\Exceptions\UnsupportedCurrencyException;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Models\WalletBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function open(User $user, string $currencyCode, ?string $name = null): Wallet
    {
        $currency = Currency::find($currencyCode);

        if (! $currency || ! $currency->is_active) {
            throw new UnsupportedCurrencyException($currencyCode);
        }

        return DB::transaction(function () use ($user, $currencyCode, $name): Wallet {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id, 'currency_code' => $currencyCode],
                ['name' => $name, 'status' => WalletStatus::Active],
            );

            if ($wallet->wasRecentlyCreated) {
                $this->provisionLedgerAccount($wallet);
                $this->ensureBalance($wallet);
            }

            return $wallet;
        });
    }

    /**
     * Each wallet owns exactly one liability ledger account keyed by wallet_id.
     */
    private function provisionLedgerAccount(Wallet $wallet): void
    {
        LedgerAccount::firstOrCreate(
            ['wallet_id' => $wallet->id],
            [
                'code' => 'CUSTOMER_WALLET',
                'name' => 'Customer Wallet '.Str::upper(Str::limit($wallet->id, 8, '')),
                'type' => AccountType::Liability,
                'currency_code' => $wallet->currency_code,
                'status' => AccountStatus::Active,
                'is_system' => false,
            ],
        );
    }

    public function ensureBalance(Wallet $wallet): WalletBalance
    {
        return WalletBalance::firstOrCreate(
            ['wallet_id' => $wallet->id],
            ['currency_code' => $wallet->currency_code, 'posted_balance' => 0, 'reserved_balance' => 0],
        );
    }

    public function freeze(Wallet $wallet, User $actor): Wallet
    {
        return $this->changeStatus($wallet, WalletStatus::Frozen, 'wallet.freeze', $actor);
    }

    public function unfreeze(Wallet $wallet, User $actor): Wallet
    {
        return $this->changeStatus($wallet, WalletStatus::Active, 'wallet.unfreeze', $actor);
    }

    private function changeStatus(Wallet $wallet, WalletStatus $status, string $action, User $actor): Wallet
    {
        $before = ['status' => $wallet->status->value];

        $wallet->forceFill(['status' => $status])->save();

        $this->audit->record(
            action: $action,
            subjectType: 'wallet',
            subjectId: $wallet->id,
            before: $before,
            after: ['status' => $status->value],
            actor: $actor,
        );

        return $wallet;
    }
}
