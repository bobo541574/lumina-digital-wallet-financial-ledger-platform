<?php

namespace App\Modules\PaymentProviders\Adapters;

use App\Modules\PaymentProviders\Contracts\PayoutProviderInterface;
use App\Modules\PaymentProviders\DTOs\PayoutResult;
use App\Modules\PaymentProviders\Exceptions\ProviderUnavailableException;
use App\Modules\PaymentProviders\Support\ProviderHttp;
use App\Modules\PaymentProviders\Support\Signature\WaveMoneyHash;
use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use App\Modules\Withdrawals\Models\Withdrawal;

/**
 * WaveMoney disbursement adapter.
 *
 * Config-gated: refuses to run until `payment.providers.wavemoney.payout.endpoint`
 * is configured. Signed with the same fixed-order HMAC used for pay-in; the
 * exact disbursement field names must be confirmed against the merchant
 * disbursement agreement before going live.
 */
class WaveMoneyPayoutProvider implements PayoutProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly WaveMoneyHash $hash = new WaveMoneyHash,
    ) {}

    public function name(): string
    {
        return 'wavemoney';
    }

    public function initiate(Withdrawal $withdrawal): PayoutResult
    {
        $endpoint = $this->string('payout', 'endpoint');

        if ($endpoint === '') {
            throw new ProviderUnavailableException('wavemoney');
        }

        /** @var array<string, mixed> $destination */
        $destination = $withdrawal->destination;

        $payload = [
            'time_to_live_in_seconds' => '60',
            'merchant_id' => (string) ($this->config['merchant_id'] ?? ''),
            'order_id' => $withdrawal->reference,
            'merchant_reference_id' => $withdrawal->reference,
            'backend_result_url' => (string) ($this->config['payout']['callback_url'] ?? ''),
            'amount' => (string) $withdrawal->amount,
            'payment_description' => 'Payout '.$withdrawal->reference,
            'wallet' => (string) ($destination['phone'] ?? $destination['wallet'] ?? ''),
            'customer_name' => (string) ($destination['name'] ?? ''),
        ];

        $payload['hash'] = $this->hash->sign([
            $payload['time_to_live_in_seconds'],
            $payload['merchant_id'],
            $payload['order_id'],
            $payload['amount'],
            $payload['backend_result_url'],
            $payload['merchant_reference_id'],
        ], (string) ($this->config['secret_key'] ?? ''));

        $response = $this->http()->postForm(
            $endpoint,
            $payload,
            ['Accept' => 'application/json'],
            $this->timeout(),
        );

        $transactionId = (string) ($response['transaction_id'] ?? '');

        $message = strtolower((string) ($response['message'] ?? ''));

        if ($transactionId === '' || in_array($message, ['invalid_hash', 'no record found'], true)) {
            return new PayoutResult(status: WithdrawalStatus::Failed, failureCode: 'PROVIDER_REJECTED');
        }

        return new PayoutResult(
            status: WithdrawalStatus::Processing,
            providerReference: $withdrawal->reference,
            providerTransactionId: $transactionId,
        );
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    private function http(): ProviderHttp
    {
        return new ProviderHttp('wavemoney');
    }

    private function string(string $key, string $index = '', string $default = ''): string
    {
        $value = $index === ''
            ? ($this->config[$key] ?? $default)
            : ($this->config[$key][$index] ?? $default);

        return (string) ($value ?? $default);
    }
}
