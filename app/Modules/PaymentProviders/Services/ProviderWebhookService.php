<?php

namespace App\Modules\PaymentProviders\Services;

use App\Modules\Deposits\Enums\DepositStatus;
use App\Modules\Deposits\Models\Deposit;
use App\Modules\Deposits\Services\DepositService;
use App\Modules\Ledger\Exceptions\LedgerDuplicateSourceException;
use App\Modules\PaymentProviders\DTOs\ProviderWebhookEvent;
use App\Modules\PaymentProviders\DTOs\WebhookResult;
use App\Modules\PaymentProviders\Models\WebhookInbox;
use App\Modules\PaymentProviders\ProviderManager;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Webhook pipeline (see docs/transaction-state-machines.md §4):
 *   verify signature -> dedupe on (provider, event_id) -> verify expected
 *   amount/currency/reference -> post atomically -> acknowledge 2xx.
 *
 * Signature failures and mismatches never post; duplicates acknowledge safely.
 */
class ProviderWebhookService
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly DepositService $deposits,
    ) {}

    public function handle(string $provider, string $rawBody, ?string $signature): WebhookResult
    {
        $adapter = $this->providers->resolve($provider);
        $payload = json_decode($rawBody, true);
        $payload = is_array($payload) ? $payload : [];

        if (! $adapter->verifySignature($rawBody, $signature)) {
            $this->record($provider, $this->eventId($payload, $rawBody), null, false, 'rejected', $payload, 'signature_invalid');

            return new WebhookResult(400, $this->error(ErrorCode::WebhookSignatureInvalid, 'Webhook signature verification failed.'));
        }

        $event = $adapter->parseEvent($payload);
        $inbox = $this->record($provider, $event->eventId, $event->eventType, true, 'received', $payload);

        if ($inbox === null) {
            return new WebhookResult(200, ['data' => ['status' => 'duplicate'], 'error' => ['code' => ErrorCode::WebhookDuplicate->value]]);
        }

        $deposit = $this->findDeposit($provider, $event);

        if (! $deposit) {
            $this->finalize($inbox, 'quarantined', 'deposit_not_found');

            return new WebhookResult(202, $this->error(ErrorCode::WebhookQuarantined, 'No matching deposit for this event.'));
        }

        if ($deposit->amount !== $event->amount || $deposit->currency_code !== $event->currency) {
            // Quarantine only: never fail or post a mismatched movement. A human
            // resolves it via reconciliation.
            $deposit->forceFill([
                'status' => DepositStatus::Reconciling,
                'failure_code' => 'WEBHOOK_MISMATCH',
            ])->save();
            $this->finalize($inbox, 'quarantined', 'amount_or_currency_mismatch', $deposit->id);

            return new WebhookResult(202, $this->error(ErrorCode::WebhookQuarantined, 'Amount or currency mismatch.'));
        }

        return match ($event->status) {
            'succeeded' => $this->succeed($inbox, $deposit, $event),
            'failed' => $this->fail($inbox, $deposit, $event),
            default => $this->acknowledgePending($inbox, $deposit, $event),
        };
    }

    private function succeed(WebhookInbox $inbox, Deposit $deposit, ProviderWebhookEvent $event): WebhookResult
    {
        try {
            $this->deposits->markSucceededFromProvider($deposit, $event->providerTransactionId);
        } catch (LedgerDuplicateSourceException) {
            // Already posted by a concurrent delivery; safe to acknowledge.
        }

        $this->finalize($inbox, 'processed', null, $deposit->id);

        return new WebhookResult(200, [
            'data' => ['status' => DepositStatus::Succeeded->value, 'deposit_id' => $deposit->id],
        ]);
    }

    private function fail(WebhookInbox $inbox, Deposit $deposit, ProviderWebhookEvent $event): WebhookResult
    {
        $this->deposits->markFailed($deposit, 'PROVIDER_DECLINED');
        $this->finalize($inbox, 'processed', null, $deposit->id);

        return new WebhookResult(200, [
            'data' => ['status' => DepositStatus::Failed->value, 'deposit_id' => $deposit->id],
        ]);
    }

    private function acknowledgePending(WebhookInbox $inbox, Deposit $deposit, ProviderWebhookEvent $event): WebhookResult
    {
        $deposit->forceFill(['status' => DepositStatus::Processing])->save();
        $this->finalize($inbox, 'processed', null, $deposit->id);

        return new WebhookResult(200, [
            'data' => ['status' => DepositStatus::Processing->value, 'deposit_id' => $deposit->id],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(
        string $provider,
        string $eventId,
        ?string $eventType,
        bool $signatureValid,
        string $status,
        array $payload,
        ?string $note = null,
    ): ?WebhookInbox {
        $now = Date::now();

        $inserted = WebhookInbox::insertOrIgnore([
            'id' => (string) Str::uuid(),
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'signature_valid' => $signatureValid,
            'status' => $status,
            'payload' => json_encode($payload),
            'note' => $note,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return null;
        }

        return WebhookInbox::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();
    }

    private function finalize(WebhookInbox $inbox, string $status, ?string $note, ?string $depositId = null): void
    {
        $inbox->forceFill([
            'status' => $status,
            'note' => $note ?? $inbox->note,
            'deposit_id' => $depositId ?? $inbox->deposit_id,
            'processed_at' => Date::now(),
        ])->save();
    }

    private function findDeposit(string $provider, ProviderWebhookEvent $event): ?Deposit
    {
        return Deposit::query()
            ->where('provider', $provider)
            ->where(function ($query) use ($event) {
                $query->where('provider_reference', $event->reference);

                if ($event->providerTransactionId !== null) {
                    $query->orWhere('provider_transaction_id', $event->providerTransactionId);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventId(array $payload, string $rawBody): string
    {
        $id = $payload['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : hash('sha256', $rawBody);
    }

    /**
     * @return array<string, mixed>
     */
    private function error(ErrorCode $code, string $message): array
    {
        return ['error' => ['code' => $code->value, 'message' => $message]];
    }
}
