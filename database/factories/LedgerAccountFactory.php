<?php

namespace Database\Factories;

use App\Modules\Ledger\Enums\AccountStatus;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerAccount>
 */
class LedgerAccountFactory extends Factory
{
    protected $model = LedgerAccount::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('ACC-####'),
            'name' => fake()->word(),
            'type' => AccountType::Liability,
            'currency_code' => 'USD',
            'status' => AccountStatus::Active,
            'wallet_id' => null,
            'is_system' => false,
        ];
    }

    public function type(AccountType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function currency(string $code): static
    {
        return $this->state(fn () => ['currency_code' => $code]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => AccountStatus::Closed]);
    }
}
