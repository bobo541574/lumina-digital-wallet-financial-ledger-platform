<?php

namespace Database\Factories;

use App\Modules\Shared\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->word(),
            'minor_units' => 2,
            'is_active' => true,
        ];
    }
}
