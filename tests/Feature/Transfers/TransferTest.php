<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Transfers\Models\Transfer;
use App\Modules\Wallets\Models\WalletBalance;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->sender = User::factory()->create();
    $this->sender->assignRole(Role::Customer->value);
    $this->senderWallet = $this->openWallet($this->sender, 'USD');
    $this->fundWallet($this->senderWallet, 10000);

    $this->recipient = User::factory()->create();
    $this->recipient->assignRole(Role::Customer->value);
    $this->recipientWallet = $this->openWallet($this->recipient, 'USD');

    Sanctum::actingAs($this->sender);
});

function pay(array $overrides = [], ?string $key = 'transfer-key-0001'): TestResponse
{
    $payload = array_merge([
        'sender_wallet_id' => test()->senderWallet->id,
        'recipient_wallet_id' => test()->recipientWallet->id,
        'currency' => 'USD',
        'amount' => 2500,
    ], $overrides);

    $request = test();

    if ($key !== null) {
        $request = $request->withHeaders(['Idempotency-Key' => $key]);
    }

    return $request->postJson('/api/v1/transfers', $payload);
}

it('moves funds atomically and posts a balanced journal', function () {
    $response = pay();

    $response->assertCreated()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.amount', 2500)
        ->assertJsonPath('data.currency', 'USD');

    expect(WalletBalance::find($this->senderWallet->id)->posted_balance)->toBe(7500)
        ->and(WalletBalance::find($this->recipientWallet->id)->posted_balance)->toBe(2500)
        ->and(JournalEntry::count())->toBe(2); // funding + transfer
});

it('rejects a transfer exceeding available balance', function () {
    pay(['amount' => 10001])->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_FUNDS');

    expect(WalletBalance::find($this->senderWallet->id)->posted_balance)->toBe(10000);
});

it('rejects a self transfer', function () {
    pay(['recipient_wallet_id' => $this->senderWallet->id])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'SELF_TRANSFER');
});

it('rejects a currency mismatch between wallets', function () {
    $mmkWallet = $this->openWallet($this->recipient, 'MMK');

    pay(['recipient_wallet_id' => $mmkWallet->id])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CURRENCY_MISMATCH');
});

it('returns the original result when the idempotency key is replayed', function () {
    $first = pay(key: 'replay-key-12345');
    $second = pay(key: 'replay-key-12345');

    $first->assertCreated();
    $second->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Transfer::count())->toBe(1)
        ->and(WalletBalance::find($this->recipientWallet->id)->posted_balance)->toBe(2500);
});

it('rejects idempotency key reuse with a different payload', function () {
    pay(key: 'reuse-key-99999')->assertCreated();

    pay(['amount' => 1000], key: 'reuse-key-99999')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');

    expect(Transfer::count())->toBe(1);
});

it('requires an idempotency key', function () {
    pay(key: null)
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
});

it('forbids transferring from a wallet the actor does not own', function () {
    $other = User::factory()->create();
    $other->assignRole(Role::Customer->value);
    $otherWallet = $this->openWallet($other, 'USD');
    $this->fundWallet($otherWallet, 5000);

    pay(['sender_wallet_id' => $otherWallet->id])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'WALLET_NOT_FOUND');

    expect(WalletBalance::find($otherWallet->id)->posted_balance)->toBe(5000);
});

it('never overspends under repeated attempts', function () {
    // Only one 6,000 transfer can succeed against a 10,000 balance.
    $accepted = 0;

    for ($i = 0; $i < 5; $i++) {
        $response = pay(['amount' => 6000], key: "loop-key-{$i}000000");

        if ($response->status() === 201) {
            $accepted++;
        }
    }

    expect($accepted)->toBe(1)
        ->and(WalletBalance::find($this->senderWallet->id)->posted_balance)->toBe(4000)
        ->and(WalletBalance::find($this->recipientWallet->id)->posted_balance)->toBe(6000);
});
