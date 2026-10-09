<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Wallets\Enums\WalletStatus;
use App\Modules\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'currency_code' => 'USD',
            'name' => fake()->unique()->word(),
            'status' => WalletStatus::Active,
        ];
    }

    public function currency(string $code): static
    {
        return $this->state(fn () => ['currency_code' => $code]);
    }

    public function frozen(): static
    {
        return $this->state(fn () => ['status' => WalletStatus::Frozen]);
    }
}
