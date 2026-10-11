<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
{
    protected $model = UserNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => fake()->uuid(),
            'type' => 'deposit.succeeded',
            'title' => 'Deposit received',
            'body' => fake()->sentence(),
            'data' => [],
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
