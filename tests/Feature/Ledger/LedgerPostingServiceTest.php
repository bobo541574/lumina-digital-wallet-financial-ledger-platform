<?php

use App\Modules\Ledger\Contracts\LedgerPostingServiceInterface;
use App\Modules\Ledger\DTOs\JournalEntryData;
use App\Modules\Ledger\DTOs\JournalPostingData;
use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Enums\JournalEntryType;
use App\Modules\Ledger\Exceptions\InvalidPostingException;
use App\Modules\Ledger\Exceptions\LedgerAccountInactiveException;
use App\Modules\Ledger\Exceptions\LedgerAccountInvalidException;
use App\Modules\Ledger\Exceptions\LedgerCurrencyMismatchException;
use App\Modules\Ledger\Exceptions\LedgerDuplicateSourceException;
use App\Modules\Ledger\Exceptions\LedgerImmutableException;
use App\Modules\Ledger\Exceptions\LedgerUnbalancedException;
use App\Modules\Ledger\Exceptions\ReversalAlreadyExistsException;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Ledger\Models\LedgerPosting;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Models\WalletBalance;
use Database\Seeders\CurrencySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function ledger(): LedgerPostingServiceInterface
{
    return app(LedgerPostingServiceInterface::class);
}

function customerWalletAccount(Wallet $wallet): LedgerAccount
{
    return LedgerAccount::factory()->create([
        'code' => 'CUSTOMER_WALLET',
        'currency_code' => $wallet->currency_code,
        'wallet_id' => $wallet->id,
        'type' => AccountType::Liability,
    ]);
}

function cashAccount(string $currency = 'USD'): LedgerAccount
{
    return LedgerAccount::firstOrCreate(
        ['code' => 'CASH', 'currency_code' => $currency, 'wallet_id' => null],
        [
            'name' => 'Platform Cash',
            'type' => AccountType::Asset,
            'status' => 'active',
            'is_system' => true,
        ],
    );
}

function fundedWallet(string $currency = 'USD'): array
{
    $wallet = Wallet::factory()->currency($currency)->create();
    $account = customerWalletAccount($wallet);
    $cash = cashAccount($currency);

    app(LedgerPostingServiceInterface::class)->post(new JournalEntryData(
        currencyCode: $currency,
        entryType: JournalEntryType::Deposit,
        postings: [
            JournalPostingData::debit($cash->id, 10000),
            JournalPostingData::credit($account->id, 10000, $wallet->id),
        ],
    ));

    return [$wallet, $account];
}

it('posts a balanced journal and creates postings', function () {
    [$walletA, $accountA] = fundedWallet();
    [$walletB, $accountB] = fundedWallet();

    $result = ledger()->post(new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Transfer,
        postings: [
            JournalPostingData::debit($accountA->id, 10000, $walletA->id),
            JournalPostingData::credit($accountB->id, 10000, $walletB->id),
        ],
        sourceType: 'transfer',
        sourceId: (string) Str::uuid(),
    ));

    expect($result->journal)->toBeInstanceOf(JournalEntry::class)
        ->and($result->postings)->toHaveCount(2)
        ->and(LedgerPosting::where('journal_entry_id', $result->journal->id)->count())->toBe(2);

    expect(WalletBalance::find($walletA->id)->posted_balance)->toBe(0)
        ->and(WalletBalance::find($walletB->id)->posted_balance)->toBe(20000);
});

it('rejects an unbalanced journal and persists nothing', function () {
    [$walletA, $accountA] = fundedWallet();
    $accountB = LedgerAccount::factory()->create(['currency_code' => 'USD']);

    $before = JournalEntry::count();

    expect(fn () => ledger()->post(new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Transfer,
        postings: [
            JournalPostingData::debit($accountA->id, 10000, $walletA->id),
            JournalPostingData::credit($accountB->id, 9000),
        ],
    )))->toThrow(LedgerUnbalancedException::class);

    expect(JournalEntry::count())->toBe($before);
});

it('rejects postings whose account currency differs from the journal', function () {
    $usd = LedgerAccount::factory()->create(['currency_code' => 'USD']);
    $mmk = LedgerAccount::factory()->create(['currency_code' => 'MMK']);

    expect(fn () => ledger()->post(new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Transfer,
        postings: [
            JournalPostingData::debit($usd->id, 100),
            JournalPostingData::credit($mmk->id, 100),
        ],
    )))->toThrow(LedgerCurrencyMismatchException::class);
});

it('rejects a duplicate source journal', function () {
    $usd = LedgerAccount::factory()->create(['currency_code' => 'USD']);
    $sec = LedgerAccount::factory()->create(['currency_code' => 'USD', 'type' => AccountType::Revenue]);
    $source = (string) Str::uuid();

    $entry = fn () => new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Fee,
        postings: [
            JournalPostingData::debit($usd->id, 100),
            JournalPostingData::credit($sec->id, 100),
        ],
        sourceType: 'fee',
        sourceId: $source,
    );

    ledger()->post($entry());

    expect(fn () => ledger()->post($entry()))
        ->toThrow(LedgerDuplicateSourceException::class);
});

it('rejects postings to an inactive account', function () {
    $active = LedgerAccount::factory()->create(['currency_code' => 'USD']);
    $inactive = LedgerAccount::factory()->inactive()->create(['currency_code' => 'USD']);

    expect(fn () => ledger()->post(new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Transfer,
        postings: [
            JournalPostingData::debit($active->id, 100),
            JournalPostingData::credit($inactive->id, 100),
        ],
    )))->toThrow(LedgerAccountInactiveException::class);
});

it('rejects non-positive amounts', function () {
    $a = LedgerAccount::factory()->create(['currency_code' => 'USD']);
    $b = LedgerAccount::factory()->create(['currency_code' => 'USD']);

    expect(fn () => ledger()->post(new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Transfer,
        postings: [
            JournalPostingData::debit($a->id, 0),
            JournalPostingData::credit($b->id, 0),
        ],
    )))->toThrow(InvalidPostingException::class);
});

it('rejects a journal with fewer than two postings', function () {
    $a = LedgerAccount::factory()->create(['currency_code' => 'USD']);

    expect(fn () => ledger()->post(new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Transfer,
        postings: [JournalPostingData::debit($a->id, 100)],
    )))->toThrow(InvalidPostingException::class);
});

it('rejects a wallet id that does not match the account wallet', function () {
    $wallet = Wallet::factory()->currency('USD')->create();
    $account = customerWalletAccount($wallet);
    $other = Wallet::factory()->currency('USD')->create();

    expect(fn () => ledger()->post(new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Transfer,
        postings: [
            JournalPostingData::debit($account->id, 100, $other->id),
            JournalPostingData::credit($account->id, 100, $wallet->id),
        ],
    )))->toThrow(LedgerAccountInvalidException::class);
});

it('denies model-level mutation of posted entries', function () {
    [$wallet, $account] = fundedWallet();
    $journal = JournalEntry::first();

    $journal->description = 'tampered';

    expect(fn () => $journal->save())->toThrow(LedgerImmutableException::class);
});

it('denies raw database mutation of posted entries via trigger', function () {
    [$wallet, $account] = fundedWallet();
    $journal = JournalEntry::first();

    expect(fn () => DB::table('journal_entries')
        ->where('id', $journal->id)
        ->update(['description' => 'tampered']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('ledger_postings')->delete())
        ->toThrow(QueryException::class);
});

it('reverses a journal with an equal and opposite entry and blocks a second reversal', function () {
    [$wallet, $account] = fundedWallet();
    $cash = cashAccount();

    $original = ledger()->post(new JournalEntryData(
        currencyCode: 'USD',
        entryType: JournalEntryType::Deposit,
        postings: [
            JournalPostingData::debit($cash->id, 5000),
            JournalPostingData::credit($account->id, 5000, $wallet->id),
        ],
        sourceType: 'deposit',
        sourceId: (string) Str::uuid(),
    ));

    ledger()->post(JournalEntryData::reversal(
        currencyCode: 'USD',
        postings: [
            JournalPostingData::debit($account->id, 5000, $wallet->id),
            JournalPostingData::credit($cash->id, 5000),
        ],
        reversesJournalEntryId: $original->journal->id,
    ));

    expect($original->journal->fresh()->isReversed())->toBeTrue();
    expect(WalletBalance::find($wallet->id)->posted_balance)->toBe(10000);

    expect(fn () => ledger()->post(JournalEntryData::reversal(
        currencyCode: 'USD',
        postings: [
            JournalPostingData::debit($account->id, 5000, $wallet->id),
            JournalPostingData::credit($cash->id, 5000),
        ],
        reversesJournalEntryId: $original->journal->id,
    )))->toThrow(ReversalAlreadyExistsException::class);
});
