<?php

namespace App\Modules\PaymentProviders\Adapters;

use App\Modules\PaymentProviders\Contracts\PayoutProviderInterface;
use App\Modules\PaymentProviders\DTOs\PayoutResult;
use App\Modules\PaymentProviders\Exceptions\ProviderConfigurationException;
use App\Modules\PaymentProviders\Exceptions\ProviderUnavailableException;
use App\Modules\PaymentProviders\Support\ProviderHttp;
use App\Modules\PaymentProviders\Support\Signature\KbzPaySignature;
use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use App\Modules\Withdrawals\Models\Withdrawal;

/**
 * KBZPay disbursement adapter.
 *
 * Config-gated: refuses to run until `payment.providers.kbzpay.payout.endpoint`
 * is configured. The request is a SHA256-signed `Request` envelope consistent
 * with KBZPay's gateway signing scheme; exact disbursement field names must be
 * confirmed against the merchant disbursement agreement before going live.
 */
class KbzPayPayoutProvider implements PayoutProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly KbzPaySignature $signature = new KbzPaySignature,
    ) {}

    public function name(): string
    {
        return 'kbzpay';
    }

    public function initiate(Withdrawal $withdrawal): PayoutResult
    {
        $endpoint = $this->string('payout', 'endpoint');

        if ($endpoint === '') {
            throw new ProviderUnavailableException('kbzpay');
        }

        /** @var array<string, mixed> $destination */
        $destination = $withdrawal->destination;

        $bizContent = [
            'appid' => $this->string('app_id'),
            'merch_code' => $this->merchantCode(),
            'merch_order_id' => $withdrawal->reference,
            'transfer_type' => (string) ($destination['transfer_type'] ?? 'PAY'),
            'payee_phone' => (string) ($destination['phone'] ?? $destination['payee_phone'] ?? ''),
            'title' => (string) ($destination['title'] ?? 'Payout'),
            'total_amount' => (string) $withdrawal->amount,
            'trans_currency' => $withdrawal->currency_code,
        ];

        $request = [
            'timestamp' => (string) now()->timestamp,
            'method' => 'kbz.payment.payout',
            'nonce_str' => bin2hex(random_bytes(16)),
            'sign_type' => 'SHA256',
            'version' => $this->string('versions', 'queryorder', '3.0'),
            'biz_content' => $bizContent,
        ];

        $fields = $request;
        unset($fields['biz_content']);
        foreach ($bizContent as $key => $value) {
            $fields[$key] = $value;
        }

        $request['sign'] = $this->signature->sign($fields, (string) ($this->config['secret'] ?? ''));

        $response = $this->http()->post(
            $endpoint,
            ['Request' => $request],
            ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            $this->timeout(),
        );

        return $this->map($response, $withdrawal->reference);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function map(array $response, string $reference): PayoutResult
    {
        $data = $response['Response'] ?? $response['response'] ?? $response;
        $data = is_array($data) ? $data : $response;

        $result = strtoupper((string) ($data['result'] ?? $data['trade_status'] ?? ''));
        $transactionId = (string) ($data['kbz_pay_order_id'] ?? $data['prepay_id'] ?? '');

        if (in_array($result, ['FAILED', 'PAY_FAILED', 'FAIL', 'ORDER_CLOSED', 'ORDER_EXPIRED'], true)) {
            return new PayoutResult(
                status: WithdrawalStatus::Failed,
                failureCode: 'PROVIDER_REJECTED',
            );
        }

        return new PayoutResult(
            status: WithdrawalStatus::Processing,
            providerReference: $reference,
            providerTransactionId: $transactionId !== '' ? $transactionId : null,
        );
    }

    private function merchantCode(): string
    {
        $merchant = $this->config['merchant_code'] ?? $this->config['merchant_id'] ?? '';

        if ($merchant === '') {
            throw new ProviderConfigurationException('KBZPay merchant_code is not configured.', 'KBZPAY_MERCHANT_CODE_MISSING');
        }

        return (string) $merchant;
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    private function http(): ProviderHttp
    {
        return new ProviderHttp('kbzpay');
    }

    private function string(string $key, string $index = '', string $default = ''): string
    {
        $value = $index === ''
            ? ($this->config[$key] ?? $default)
            : ($this->config[$key][$index] ?? $default);

        return (string) ($value ?? $default);
    }
}
