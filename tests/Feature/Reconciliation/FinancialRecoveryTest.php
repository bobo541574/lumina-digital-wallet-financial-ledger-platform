<?php

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerPostingServiceInterface;
use App\Modules\Ledger\DTOs\JournalEntryData;
use App\Modules\Ledger\DTOs\JournalPostingData;
use App\Modules\Ledger\Enums\JournalEntryType;
use App\Modules\Ledger\Exceptions\JournalNotReversibleException;
use App\Modules\Ledger\Exceptions\ReversalAlreadyExistsException;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Ledger\Services\LedgerReversalService;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\Wallets\Models\WalletBalance;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\WalletTestSupport;

uses(WalletTestSupport::class);

beforeEach(function () {
    $this->seedFinancials();
    $this->seed(RolePermissionSeeder::class);

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::Customer->value);
    $this->wallet = $this->openWallet($this->customer, 'USD');
    $this->fundWallet($this->wallet, 10000);

    $this->finance = User::factory()->create();
    $this->finance->assignRole(Role::FinanceOperator->value);
});

function originalJournal(User $customer, string $walletId): JournalEntry
{
    return app(LedgerPostingServiceInterface::class)->post(new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Deposit,
        postings: [
            JournalPostingData::debit(cashAccountId('USD'), 4000),
            JournalPostingData::credit(walletAccountId($walletId), 4000, $walletId),
        ],
        sourceType: 'recovery_test',
        sourceId: (string) Str::uuid(),
    ))->journal;
}

function cashAccountId(string $currency): string
{
    return LedgerAccount::query()
        ->where('code', 'CASH')->where('currency_code', $currency)->whereNull('wallet_id')
        ->firstOrFail()->id;
}

function walletAccountId(string $walletId): string
{
    return LedgerAccount::query()
        ->where('wallet_id', $walletId)->firstOrFail()->id;
}

it('reverses a posted journal with an equal and opposite entry and records an outbox event', function () {
    $journal = originalJournal($this->customer, $this->wallet->id);
    $balanceBefore = WalletBalance::find($this->wallet->id)->posted_balance;

    $result = app(LedgerReversalService::class)->reverse($journal, $this->finance, 'Duplicate deposit');

    expect($result->journal->entry_type)->toBe(JournalEntryType::Reversal)
        ->and($result->journal->reverses_journal_entry_id)->toBe($journal->id)
        ->and($journal->fresh()->isReversed())->toBeTrue();

    expect(WalletBalance::find($this->wallet->id)->posted_balance)->toBe($balanceBefore - 4000);

    expect(OutboxMessage::query()->where('event_type', 'journal.reversed')->sole()->payload['reverses_journal_entry_id'])
        ->toBe($journal->id);

    expect(fn () => app(LedgerReversalService::class)->reverse($journal->fresh(), $this->finance))
        ->toThrow(ReversalAlreadyExistsException::class);
});

it('refuses to reverse a reversal', function () {
    $journal = originalJournal($this->customer, $this->wallet->id);
    $reversal = app(LedgerReversalService::class)->reverse($journal, $this->finance);

    expect(fn () => app(LedgerReversalService::class)->reverse($reversal->journal, $this->finance))
        ->toThrow(JournalNotReversibleException::class);
});

it('gates journal reversal on the ledger.correct permission', function () {
    $journal = originalJournal($this->customer, $this->wallet->id);

    Sanctum::actingAs($this->customer);

    $this->postJson("/api/v1/admin/journal-entries/{$journal->id}/reverse", ['reason' => 'nope'])
        ->assertStatus(403);

    Sanctum::actingAs($this->finance);

    $this->postJson("/api/v1/admin/journal-entries/{$journal->id}/reverse", ['reason' => 'Customer dispute'])
        ->assertCreated()
        ->assertJsonPath('data.entry_type', 'reversal')
        ->assertJsonPath('data.reverses_journal_entry_id', $journal->id);
});
