<?php

namespace App\Modules\Transfers\Services;

use App\Modules\Ledger\Contracts\LedgerPostingServiceInterface;
use App\Modules\Ledger\DTOs\JournalEntryData;
use App\Modules\Ledger\DTOs\JournalPostingData;
use App\Modules\Ledger\Enums\JournalEntryType;
use App\Modules\Ledger\Exceptions\LedgerAccountInvalidException;
use App\Modules\Ledger\Models\LedgerAccount;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Exceptions\DomainException;
use App\Modules\Shared\Idempotency\IdempotencyOutcome;
use App\Modules\Shared\Idempotency\IdempotencyResult;
use App\Modules\Shared\Idempotency\IdempotencyService;
use App\Modules\Transfers\DTOs\TransferData;
use App\Modules\Transfers\Enums\TransferStatus;
use App\Modules\Transfers\Exceptions\SelfTransferException;
use App\Modules\Transfers\Http\Resources\TransferResource;
use App\Modules\Transfers\Models\Transfer;
use App\Modules\Wallets\Exceptions\WalletNotFoundException;
use App\Modules\Wallets\Exceptions\WalletNotUsableException;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Services\WalletBalanceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TransferService
{
    public const IDEMPOTENCY_SCOPE = 'transfer.create';

    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly LedgerPostingServiceInterface $ledger,
        private readonly WalletBalanceService $balances,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function transfer(TransferData $data): IdempotencyResult
    {
        return $this->idempotency->run(
            scope: self::IDEMPOTENCY_SCOPE,
            actorId: $data->actorId,
            key: $data->idempotencyKey,
            requestHash: $data->requestHash,
            callback: fn (): IdempotencyOutcome => $this->execute($data),
            attempts: self::MAX_ATTEMPTS,
        );
    }

    private function execute(TransferData $data): IdempotencyOutcome
    {
        if ($data->senderWalletId === $data->recipientWalletId) {
            throw new SelfTransferException($data->senderWalletId);
        }

        /** @var Collection<string, Wallet> $wallets */
        $wallets = Wallet::query()
            ->whereIn('id', [$data->senderWalletId, $data->recipientWalletId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $missing = array_values(array_diff(
            [$data->senderWalletId, $data->recipientWalletId],
            $wallets->keys()->all(),
        ));

        if ($missing !== []) {
            throw new WalletNotFoundException($missing);
        }

        $sender = $wallets->get($data->senderWalletId);
        $recipient = $wallets->get($data->recipientWalletId);

        // Ownership is enforced here (not just at the controller) so the rule
        // holds no matter which entry point calls the service. Hide the wallet
        // id rather than leaking that it belongs to someone else.
        if ($sender->user_id !== $data->actorId) {
            throw new WalletNotFoundException($sender->id);
        }

        $this->assertCurrency($data, $sender, $recipient);

        $balanceRows = $this->balances->lockMany([$sender->id, $recipient->id]);
        $senderBalance = $balanceRows->get($sender->id) ?? $this->balances->ensureFor($sender);

        $this->balances->assertCanDebit($sender, $senderBalance, $data->amount + $data->fee);

        if (! $recipient->status->canCredit()) {
            throw WalletNotUsableException::forCredit($recipient);
        }

        $accounts = LedgerAccount::query()
            ->whereIn('wallet_id', [$sender->id, $recipient->id])
            ->get()
            ->keyBy('wallet_id');

        if (! $accounts->has($sender->id) || ! $accounts->has($recipient->id)) {
            throw new LedgerAccountInvalidException(['reason' => 'wallet_ledger_account_missing']);
        }

        $transfer = Transfer::create([
            'reference' => 'TRF'.strtoupper((string) Str::ulid()),
            'sender_wallet_id' => $sender->id,
            'recipient_wallet_id' => $recipient->id,
            'currency_code' => $data->currencyCode,
            'amount' => $data->amount,
            'fee' => $data->fee,
            'status' => TransferStatus::Pending,
            'initiated_by' => $data->actorId,
            'occurred_at' => now(),
        ]);

        $postings = [
            JournalPostingData::debit($accounts->get($sender->id)->id, $data->amount + $data->fee, $sender->id),
            JournalPostingData::credit($accounts->get($recipient->id)->id, $data->amount, $recipient->id),
        ];

        if ($data->fee > 0) {
            $postings[] = JournalPostingData::credit($this->feeRevenueAccount($data->currencyCode)->id, $data->fee);
        }

        $result = $this->ledger->post(new JournalEntryData(
            currencyCode: $data->currencyCode,
            entryType: JournalEntryType::Transfer,
            postings: $postings,
            sourceType: 'transfer',
            sourceId: $transfer->id,
            description: $data->description ?? 'Internal transfer',
            occurredAt: null,
            createdBy: $data->actorId,
        ));

        $transfer->forceFill([
            'status' => TransferStatus::Succeeded,
            'journal_entry_id' => $result->journal->id,
        ])->save();

        return new IdempotencyOutcome(
            body: (new TransferResource($transfer))->resolve(),
            status: 201,
            resourceType: 'transfer',
            resourceId: $transfer->id,
        );
    }

    private function assertCurrency(TransferData $data, Wallet $sender, Wallet $recipient): void
    {
        if ($sender->currency_code !== $data->currencyCode || $recipient->currency_code !== $data->currencyCode) {
            throw new DomainException(
                ErrorCode::CurrencyMismatch,
                'Sender and recipient wallets must match the transfer currency.',
                [
                    'transfer_currency' => $data->currencyCode,
                    'sender_currency' => $sender->currency_code,
                    'recipient_currency' => $recipient->currency_code,
                ],
            );
        }
    }

    private function feeRevenueAccount(string $currencyCode): LedgerAccount
    {
        $account = LedgerAccount::query()
            ->where('code', 'FEE_REVENUE')
            ->whereNull('wallet_id')
            ->where('currency_code', $currencyCode)
            ->first();

        if (! $account) {
            throw new LedgerAccountInvalidException([
                'reason' => 'fee_revenue_account_missing',
                'currency' => $currencyCode,
            ]);
        }

        return $account;
    }
}
