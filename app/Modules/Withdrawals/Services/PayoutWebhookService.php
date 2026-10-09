<?php

namespace App\Modules\Withdrawals\Services;

use App\Modules\PaymentProviders\DTOs\WebhookResult;
use App\Modules\PaymentProviders\ProviderManager;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Withdrawals\Models\Withdrawal;

class PayoutWebhookService
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly WithdrawalService $withdrawals,
    ) {}

    public function handle(string $provider, string $rawBody, ?string $signature): WebhookResult
    {
        $adapter = $this->providers->resolve($provider);

        if (! $adapter->verifySignature($rawBody, $signature)) {
            return new WebhookResult(400, $this->error(ErrorCode::WebhookSignatureInvalid, 'Webhook signature verification failed.'));
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?: [];
        $event = $adapter->parseEvent($payload);

        $withdrawal = Withdrawal::query()
            ->where('provider', $provider)
            ->where('provider_reference', $event->reference)
            ->first();

        if (! $withdrawal) {
            return new WebhookResult(202, $this->error(ErrorCode::WebhookQuarantined, 'Unknown payout reference.'));
        }

        return match ($event->status) {
            'succeeded' => $this->succeeded($withdrawal, $event->providerTransactionId),
            'failed' => $this->failed($withdrawal, $event->raw['failure_code'] ?? 'PROVIDER_REJECTED'),
            default => new WebhookResult(202, ['data' => ['status' => 'processing']]),
        };
    }

    private function succeeded(Withdrawal $withdrawal, ?string $providerTransactionId): WebhookResult
    {
        $withdrawal = $this->withdrawals->markSucceeded($withdrawal, $providerTransactionId);

        return new WebhookResult(200, ['data' => ['status' => $withdrawal->status->value]]);
    }

    private function failed(Withdrawal $withdrawal, string $failureCode): WebhookResult
    {
        $withdrawal = $this->withdrawals->markFailed($withdrawal, $failureCode);

        return new WebhookResult(200, ['data' => ['status' => $withdrawal->status->value]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function error(ErrorCode $code, string $message): array
    {
        return ['error' => ['code' => $code->value, 'message' => $message]];
    }
}
