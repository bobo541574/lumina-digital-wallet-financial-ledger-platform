<?php

namespace Database\Seeders;

use App\Modules\Deposits\Services\DepositService;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Shared\Models\Currency;
use App\Modules\Transfers\DTOs\TransferData;
use App\Modules\Transfers\Services\TransferService;
use App\Modules\Wallets\Enums\WalletStatus;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Models\WalletBalance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::firstOrCreate(['email' => 'test@example.com'], ['name' => 'Test Customer', 'password' => Hash::make('password'), 'email_verified_at' => now()]);
        if (! $customer->hasRole(Role::Customer->value)) {
            $customer->assignRole(Role::Customer->value);
        }

        $admin = User::firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Admin User', 'password' => Hash::make('password'), 'email_verified_at' => now()]);
        if (! $admin->hasRole(Role::Admin->value)) {
            $admin->assignRole(Role::Admin->value);
        }

        $currencies = Currency::all();
        if ($currencies->isEmpty()) {
            $this->call(CurrencySeeder::class);
            $currencies = Currency::all();
        }

        $demo = collect([['email' => 'demo1@example.com', 'name' => 'Demo One'], ['email' => 'demo2@example.com', 'name' => 'Demo Two'], ['email' => 'ops@example.com', 'name' => 'Ops Staff']]);
        $demo->each(function ($d) use ($currencies) {
            $u = User::firstOrCreate(['email' => $d['email']], ['name' => $d['name'], 'password' => Hash::make('password'), 'email_verified_at' => now()]);
            if (! $u->hasRole(Role::Customer->value)) {
                $u->assignRole(Role::Customer->value);
            }
            foreach ($currencies as $cur) {
                $w = Wallet::firstOrCreate(['user_id' => $u->id, 'currency_code' => $cur->code, 'name' => "Main {$cur->code}"], ['status' => WalletStatus::Active]);
                WalletBalance::updateOrCreate(['wallet_id' => $w->id, 'currency_code' => $cur->code], ['posted_balance' => rand(10000, 500000), 'reserved_balance' => 0]);
            }
        });

        foreach ($currencies as $cur) {
            $w = Wallet::firstOrCreate(['user_id' => $customer->id, 'currency_code' => $cur->code, 'name' => "Main {$cur->code}"], ['status' => WalletStatus::Active]);
            WalletBalance::updateOrCreate(['wallet_id' => $w->id, 'currency_code' => $cur->code], ['posted_balance' => rand(500000, 2000000), 'reserved_balance' => 0]);
        }

        // Ensure ledger accounts for all wallets
        foreach (Wallet::all() as $w) {
            LedgerAccount::firstOrCreate([
                'wallet_id' => $w->id,
            ], [
                'code' => 'WALLET_'.$w->id,
                'name' => 'Wallet '.$w->currency_code,
                'type' => AccountType::Liability,
                'currency_code' => $w->currency_code,
                'status' => 'active',
                'is_system' => true,
            ]);
        }

        $ds = app(DepositService::class);
        $ts = app(TransferService::class);
        foreach (Wallet::where('user_id', $customer->id)->get() as $w) {
            try {
                $ds->createManual($customer, $w->id, 100000, []);
            } catch (\Exception $e) {
            }
        }
        $demo1 = User::where('email', 'demo1@example.com')->first();
        $demo2 = User::where('email', 'demo2@example.com')->first();
        $usdCust = Wallet::where('user_id', $customer->id)->where('currency_code', 'USD')->first();
        $usd1 = Wallet::where('user_id', $demo1->id)->where('currency_code', 'USD')->first();
        $usd2 = Wallet::where('user_id', $demo2->id)->where('currency_code', 'USD')->first();
        if ($usdCust && $usd1) {
            $ts->transfer(new TransferData($customer->id, $usdCust->id, $usd1->id, 'USD', 20000, 0, bin2hex(random_bytes(16)), 'demo'));
        }
        if ($usdCust && $usd2) {
            $ts->transfer(new TransferData($customer->id, $usdCust->id, $usd2->id, 'USD', 15000, 0, bin2hex(random_bytes(16)), 'demo'));
        }

        $this->command->info('Demo data seeded with wallets, balances, deposits, transfers');
    }
}
