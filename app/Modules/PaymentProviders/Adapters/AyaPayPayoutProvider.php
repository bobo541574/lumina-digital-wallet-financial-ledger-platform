<?php

namespace App\Modules\PaymentProviders\Adapters;

use App\Modules\PaymentProviders\Contracts\PayoutProviderInterface;
use App\Modules\PaymentProviders\DTOs\PayoutResult;
use App\Modules\PaymentProviders\Exceptions\ProviderConfigurationException;
use App\Modules\PaymentProviders\Exceptions\ProviderUnavailableException;
use App\Modules\PaymentProviders\Support\ProviderHttp;
use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use App\Modules\Withdrawals\Models\Withdrawal;

/**
 * AYA Pay disbursement adapter.
 *
 * Config-gated: refuses to run until `payment.providers.aya.payout.endpoint`
 * is configured. Uses the same bearer-token login flow as pay-in; the exact
 * disbursement field names must be confirmed against the merchant agreement
 * before going live.
 */
class AyaPayPayoutProvider implements PayoutProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ProviderHttp $http = new ProviderHttp('aya'),
    ) {}

    public function name(): string
    {
        return 'aya';
    }

    public function initiate(Withdrawal $withdrawal): PayoutResult
    {
        $endpoint = $this->string('payout', 'endpoint');

        if ($endpoint === '') {
            throw new ProviderUnavailableException('aya');
        }

        /** @var array<string, mixed> $destination */
        $destination = $withdrawal->destination;

        $payload = [
            'amount' => (string) $withdrawal->amount,
            'currency' => $withdrawal->currency_code,
            'externalTransactionId' => $withdrawal->reference,
            'customerPhone' => (string) ($destination['phone'] ?? $destination['account'] ?? ''),
            'customerName' => (string) ($destination['name'] ?? ''),
            'description' => 'Payout '.$withdrawal->reference,
        ];

        $response = $this->http->post(
            $endpoint,
            $payload,
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$this->login(),
            ],
            $this->timeout(),
        );

        $reference = (string) ($response['referenceNumber'] ?? $response['referenceNo'] ?? '');

        if ($reference === '') {
            return new PayoutResult(status: WithdrawalStatus::Failed, failureCode: 'PROVIDER_REJECTED');
        }

        return new PayoutResult(
            status: WithdrawalStatus::Processing,
            providerReference: $withdrawal->reference,
            providerTransactionId: $reference,
        );
    }

    private function login(): string
    {
        $basicToken = (string) ($this->config['basic_token'] ?? '');

        if ($basicToken === '') {
            throw new ProviderConfigurationException('AYA basic_token is not configured.', 'AYA_BASIC_TOKEN_MISSING');
        }

        $token = $this->http->post(
            $this->string('endpoints', 'login'),
            [
                'phone' => (string) ($this->config['phone'] ?? ''),
                'password' => (string) ($this->config['password'] ?? ''),
            ],
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'apikey' => 'Basic '.$basicToken,
            ],
            $this->timeout(),
        );

        $value = $token['token']['token'] ?? null;

        if (! is_string($value) || $value === '') {
            throw new ProviderConfigurationException('AYA login failed: token missing from response.', 'AYA_LOGIN_FAILED');
        }

        return $value;
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    private function string(string $key, string $index = '', string $default = ''): string
    {
        $value = $index === ''
            ? ($this->config[$key] ?? $default)
            : ($this->config[$key][$index] ?? $default);

        return (string) ($value ?? $default);
    }
}
