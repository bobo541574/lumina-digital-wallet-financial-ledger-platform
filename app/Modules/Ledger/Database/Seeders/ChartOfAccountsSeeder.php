<?php

namespace App\Modules\Ledger\Database\Seeders;

use App\Modules\Ledger\Enums\AccountStatus;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Shared\Models\Currency;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * System account chart, seeded once per active currency.
     *
     * @var array<string, array{name: string, type: AccountType}>
     */
    private array $systemAccounts = [
        'CASH' => ['name' => 'Platform Cash', 'type' => AccountType::Asset],
        'PROVIDER_CLEARING' => ['name' => 'Provider Clearing', 'type' => AccountType::Asset],
        'PAYOUT_CLEARING' => ['name' => 'Payout Clearing', 'type' => AccountType::Asset],
        'FEE_REVENUE' => ['name' => 'Fee Revenue', 'type' => AccountType::Revenue],
        'SUSPENSE' => ['name' => 'Suspense', 'type' => AccountType::Liability],
    ];

    public function run(): void
    {
        foreach (Currency::where('is_active', true)->get() as $currency) {
            foreach ($this->systemAccounts as $code => $definition) {
                LedgerAccount::updateOrCreate(
                    ['code' => $code, 'currency_code' => $currency->code, 'wallet_id' => null],
                    [
                        'name' => $definition['name'],
                        'type' => $definition['type'],
                        'status' => AccountStatus::Active,
                        'wallet_id' => null,
                        'is_system' => true,
                    ],
                );
            }
        }
    }
}
