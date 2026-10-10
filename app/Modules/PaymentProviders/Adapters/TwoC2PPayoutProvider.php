<?php

namespace App\Modules\PaymentProviders\Adapters;

use App\Modules\PaymentProviders\Contracts\PayoutProviderInterface;
use App\Modules\PaymentProviders\DTOs\PayoutResult;
use App\Modules\PaymentProviders\Exceptions\ProviderConfigurationException;
use App\Modules\PaymentProviders\Exceptions\ProviderUnavailableException;
use App\Modules\PaymentProviders\Support\ProviderHttp;
use App\Modules\PaymentProviders\Support\Signature\TwoC2PJwt;
use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use App\Modules\Withdrawals\Models\Withdrawal;

/**
 * 2C2P disbursement adapter.
 *
 * Config-gated: refuses to run until `payment.providers.2c2p.payout.endpoint`
 * is configured. Requests are wrapped in the same HS256 JWT envelope used for
 * pay-in; the exact disbursement field names must be confirmed against the
 * merchant Payout Gateway agreement before going live.
 */
class TwoC2PPayoutProvider implements PayoutProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly TwoC2PJwt $jwt = new TwoC2PJwt,
    ) {}

    public function name(): string
    {
        return '2c2p';
    }

    public function initiate(Withdrawal $withdrawal): PayoutResult
    {
        $endpoint = $this->string('payout', 'endpoint');

        if ($endpoint === '') {
            throw new ProviderUnavailableException('2c2p');
        }

        /** @var array<string, mixed> $destination */
        $destination = $withdrawal->destination;

        $body = [
            'merchantID' => $this->merchantId(),
            'invoiceNo' => $withdrawal->reference,
            'amount' => (string) $withdrawal->amount,
            'currencyCode' => $withdrawal->currency_code,
            'description' => (string) ($destination['name'] ?? 'Payout'),
            'recipient' => (string) ($destination['account'] ?? $destination['phone'] ?? ''),
            'locale' => $this->string('locale', '', 'en'),
        ];

        $response = $this->http()->post(
            $endpoint,
            ['payload' => $this->jwt->encode($body, $this->secretKey())],
            ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            $this->timeout(),
        );

        $token = is_string($response['payload'] ?? null) ? (string) $response['payload'] : '';
        $data = $token !== '' ? $this->jwt->decode($token, $this->secretKey()) : $response;

        $respCode = (string) ($data['respCode'] ?? '');

        if (in_array($respCode, ['0000', '1000', '1001', '1002', '1003'], true)) {
            return new PayoutResult(
                status: WithdrawalStatus::Processing,
                providerReference: $withdrawal->reference,
                providerTransactionId: (string) ($data['paymentID'] ?? $data['paymentToken'] ?? '') !== ''
                    ? (string) ($data['paymentID'] ?? $data['paymentToken'] ?? '')
                    : null,
            );
        }

        return new PayoutResult(status: WithdrawalStatus::Failed, failureCode: 'PROVIDER_REJECTED');
    }

    private function merchantId(): string
    {
        $merchantId = (string) ($this->config['merchant_id'] ?? '');

        if ($merchantId === '') {
            throw new ProviderConfigurationException('2C2P merchant_id is not configured.', 'TWOC2P_MERCHANT_ID_MISSING');
        }

        return $merchantId;
    }

    private function secretKey(): string
    {
        $secret = (string) ($this->config['secret_key'] ?? '');

        if ($secret === '') {
            throw new ProviderConfigurationException('2C2P secret_key is not configured.', 'TWOC2P_SECRET_KEY_MISSING');
        }

        return $secret;
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    private function http(): ProviderHttp
    {
        return new ProviderHttp('2c2p');
    }

    private function string(string $key, string $index = '', string $default = ''): string
    {
        $value = $index === ''
            ? ($this->config[$key] ?? $default)
            : ($this->config[$key][$index] ?? $default);

        return (string) ($value ?? $default);
    }
}
