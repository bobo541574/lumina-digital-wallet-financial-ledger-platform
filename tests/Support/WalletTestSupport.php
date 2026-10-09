<?php

namespace Tests\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerPostingServiceInterface;
use App\Modules\Ledger\Database\Seeders\ChartOfAccountsSeeder;
use App\Modules\Ledger\DTOs\JournalEntryData;
use App\Modules\Ledger\DTOs\JournalPostingData;
use App\Modules\Ledger\Enums\JournalEntryType;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Services\WalletService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Str;

trait WalletTestSupport
{
    protected function seedFinancials(): void
    {
        $this->seed(CurrencySeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    protected function openWallet(User $user, string $currency = 'USD', ?string $name = null): Wallet
    {
        return app(WalletService::class)->open($user, $currency, $name);
    }

    protected function systemAccount(string $code, string $currency): LedgerAccount
    {
        return LedgerAccount::query()
            ->where('code', $code)
            ->where('currency_code', $currency)
            ->whereNull('wallet_id')
            ->firstOrFail();
    }

    protected function walletAccount(Wallet $wallet): LedgerAccount
    {
        return LedgerAccount::query()->where('wallet_id', $wallet->id)->firstOrFail();
    }

    protected function fundWallet(Wallet $wallet, int $amount): void
    {
        $cash = $this->systemAccount('CASH', $wallet->currency_code);
        $account = $this->walletAccount($wallet);

        app(LedgerPostingServiceInterface::class)->post(new JournalEntryData(
            currencyCode: $wallet->currency_code,
            entryType: JournalEntryType::Deposit,
            postings: [
                JournalPostingData::debit($cash->id, $amount),
                JournalPostingData::credit($account->id, $amount, $wallet->id),
            ],
            sourceType: 'test_funding',
            sourceId: (string) Str::uuid(),
        ));
    }
}
