<?php

namespace Database\Seeders;

use App\Modules\Shared\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'minor_units' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'minor_units' => 2],
            ['code' => 'MMK', 'name' => 'Myanmar Kyat', 'minor_units' => 0],
            ['code' => 'THB', 'name' => 'Thai Baht', 'minor_units' => 2],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'minor_units' => 2],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                [...$currency, 'is_active' => true],
            );
        }
    }
}
