<?php

use App\Modules\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
});

/**
 * These tests prove the database itself enforces the financial invariants the
 * application relies on (defence in depth behind the service layer). A regression
 * here means a control that the ledger and reconciliation trust has gone missing.
 */
it('declares the critical financial constraints', function () {
    $constraints = collect(DB::select('select conname, pg_get_constraintdef(oid) as definition from pg_constraint'))
        ->keyBy('conname');

    $required = [
        'wallet_balances_posted_non_negative' => 'posted_balance >= 0',
        'wallet_balances_reserved_non_negative' => 'reserved_balance >= 0',
        'ledger_postings_amount_positive' => 'amount > 0',
        'ledger_postings_direction_check' => 'direction',
        'transfers_amount_positive' => 'amount > 0',
        'transfers_distinct_wallets' => 'sender_wallet_id',
        'withdrawals_amount_positive' => 'amount > 0',
        'wallet_holds_amount_positive' => 'amount > 0',
        'idempotency_keys_scope_actor_id_key_unique' => null,
        'wallets_user_id_currency_code_unique' => null,
    ];

    foreach ($required as $name => $needle) {
        expect($constraints->has($name), "Missing database constraint {$name}.")->toBeTrue();

        if ($needle !== null) {
            expect($constraints[$name]->definition)->toContain($needle);
        }
    }
});

it('installs the ledger immutability triggers', function () {
    $triggers = collect(DB::select('select tgname from pg_trigger where not tgisinternal'))
        ->pluck('tgname');

    expect($triggers)->toContain('ledger_postings_immutable')
        ->toContain('journal_entries_immutable');
});

it('rejects a negative wallet balance at the database level', function () {
    $wallet = $this->openWallet(User::factory()->create(), 'USD');
    $this->fundWallet($wallet, 5000);

    expect(fn () => DB::table('wallet_balances')
        ->where('wallet_id', $wallet->id)
        ->update(['posted_balance' => -1]))
        ->toThrow(QueryException::class);
});

it('rejects a self-transfer row at the database level', function () {
    $wallet = $this->openWallet(User::factory()->create(), 'USD');

    expect(fn () => DB::table('transfers')->insert([
        'id' => (string) Str::uuid(),
        'reference' => Str::random(30),
        'sender_wallet_id' => $wallet->id,
        'recipient_wallet_id' => $wallet->id,
        'currency_code' => 'USD',
        'amount' => 100,
        'fee' => 0,
        'status' => 'pending',
        'initiated_by' => $wallet->user_id,
        'occurred_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('prevents updating or deleting a posted ledger entry', function () {
    $wallet = $this->openWallet(User::factory()->create(), 'USD');
    $this->fundWallet($wallet, 5000);

    $posting = DB::table('ledger_postings')->first();
    $entry = DB::table('journal_entries')->where('id', $posting->journal_entry_id)->first();

    expect(fn () => DB::table('ledger_postings')->where('id', $posting->id)->update(['amount' => 1]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('ledger_postings')->where('id', $posting->id)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('journal_entries')->where('id', $entry->id)->delete())
        ->toThrow(QueryException::class);
});

it('keeps wallet currency immutable against the ledger account currency', function () {
    $wallet = $this->openWallet(User::factory()->create(), 'USD');
    $this->fundWallet($wallet, 5000);

    expect(fn () => DB::table('wallets')
        ->where('id', $wallet->id)
        ->update(['currency_code' => 'MMK']))
        ->toThrow(QueryException::class);
});

it('enforces that ledger account types are known', function () {
    expect(fn () => DB::table('ledger_accounts')->insert([
        'id' => (string) Str::uuid(),
        'code' => 'BAD_ACCOUNT',
        'name' => 'Bad',
        'type' => 'not_a_type',
        'currency_code' => 'USD',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
