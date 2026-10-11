<?php

namespace Database\Seeders;

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Ledger\Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
