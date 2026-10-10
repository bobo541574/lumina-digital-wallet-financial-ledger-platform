<?php

namespace App\Modules\PaymentProviders\Adapters;

use App\Modules\PaymentProviders\Contracts\CollectableProvider;
use App\Modules\PaymentProviders\Contracts\PaymentProviderInterface;
use App\Modules\PaymentProviders\DTOs\CollectRequest;
use App\Modules\PaymentProviders\DTOs\PaymentIntentResult;
use App\Modules\PaymentProviders\DTOs\ProviderWebhookEvent;
use App\Modules\PaymentProviders\Enums\PaymentStatus;
use App\Modules\PaymentProviders\Exceptions\ProviderConfigurationException;
use App\Modules\PaymentProviders\Support\ProviderHttp;
use App\Modules\PaymentProviders\Support\Signature\KbzPaySignature;

/**
 * KBZPay (`kbzpay`) pay-in adapter.
 *
 * Collect: `kbz.payment.precreate` with a SHA256-signed `Request` envelope.
 * A PWA redirect URL is derived from the returned `prepay_id` so the customer
 * can complete payment in the hosted KBZPay page.
 *
 * Webhook: the callback body carries its own `sign` over the flat field set.
 *
 * @see https://github.com/hakhant21/myanmar-payments
 */
class KbzPayProvider implements CollectableProvider, PaymentProviderInterface
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

    public function createPayment(CollectRequest $request): PaymentIntentResult
    {
        $bizContent = [
            'appid' => $this->string('app_id'),
            'merch_code' => $this->merchantCode(),
            'merch_order_id' => $request->merchantReference,
            'trade_type' => $this->tradeType(),
            'title' => $this->title($request),
            'total_amount' => (string) $request->amount,
            'trans_currency' => $request->currency,
            'timeout_express' => $this->timeoutExpress($request),
        ];

        $envelope = $this->envelope('kbz.payment.precreate', $this->string('versions', 'precreate', '1.0'), $bizContent, $request->callbackUrl);

        $response = $this->http()->post(
            $this->string('endpoints', 'precreate', 'https://api.kbzpay.com/payment/gateway/precreate'),
            ['Request' => $envelope],
            $this->headers(),
            $this->timeout(),
        );

        return $this->intent($response, $request->merchantReference, $request->currency, $request->amount);
    }

    public function queryStatus(string $providerTransactionId): PaymentIntentResult
    {
        $bizContent = [
            'appid' => $this->string('app_id'),
            'merch_code' => $this->merchantCode(),
            'merch_order_id' => $providerTransactionId,
        ];

        $envelope = $this->envelope('kbz.payment.queryorder', $this->string('versions', 'queryorder', '3.0'), $bizContent);

        $response = $this->http()->post(
            $this->string('endpoints', 'queryorder', 'https://api.kbzpay.com/payment/gateway/queryorder'),
            ['Request' => $envelope],
            $this->headers(),
            $this->timeout(),
        );

        return $this->intent($response, $providerTransactionId, 'MMK', 0);
    }

    public function verifySignature(string $rawPayload, ?string $signature): bool
    {
        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return false;
        }

        $fields = $this->unwrap($payload);

        $provided = is_scalar($fields['sign'] ?? null) ? (string) $fields['sign'] : (string) $signature;

        if ($provided === '') {
            return false;
        }

        return $this->signature->verify($fields, $provided, (string) $this->config['secret']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseEvent(array $payload): ProviderWebhookEvent
    {
        $data = $this->unwrap($payload);

        $status = $this->mapPaymentStatus($data);
        $transactionId = (string) ($data['prepay_id'] ?? $data['kbz_pay_order_id'] ?? '');
        $reference = (string) ($data['merch_order_id'] ?? '');

        return new ProviderWebhookEvent(
            eventId: $this->eventId($data, $reference, $transactionId),
            eventType: 'payment',
            reference: $reference,
            providerTransactionId: $transactionId !== '' ? $transactionId : null,
            amount: (int) ($data['total_amount'] ?? 0),
            currency: strtoupper((string) ($data['trans_currency'] ?? 'MMK')),
            status: $status->value,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrap(array $payload): array
    {
        foreach (['Response', 'Request'] as $wrapper) {
            if (isset($payload[$wrapper]) && is_array($payload[$wrapper])) {
                $payload = $payload[$wrapper];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function intent(array $response, string $reference, string $currency, int $amount): PaymentIntentResult
    {
        $data = $this->unwrap($response);

        $prepayId = (string) ($data['prepay_id'] ?? '');
        $transactionId = (string) ($data['merch_order_id'] ?? '') !== '' ? (string) $data['merch_order_id'] : $prepayId;
        $status = $this->mapPaymentStatus($data);

        return new PaymentIntentResult(
            provider: 'kbzpay',
            status: $status,
            providerReference: $reference,
            providerTransactionId: $transactionId !== '' ? $transactionId : null,
            paymentUrl: $this->paymentUrl($data, $prepayId),
            extra: [
                'qr_code' => (string) ($data['qr_code'] ?? ''),
                'prepay_id' => $prepayId,
            ],
            raw: $response,
        );
    }

    /**
     * KBZPay's PWA page needs a signed redirect built from `prepay_id`.
     *
     * @param  array<string, mixed>  $data
     */
    private function paymentUrl(array $data, string $prepayId): ?string
    {
        if ($prepayId === '' || $this->payType() !== 'pwa') {
            return null;
        }

        $fields = [
            'appid' => $this->string('app_id'),
            'merch_code' => $this->merchantCode(),
            'nonce_str' => bin2hex(random_bytes(16)),
            'prepay_id' => $prepayId,
            'timestamp' => (string) now()->timestamp,
        ];

        $pairs = [];
        foreach ($fields as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        $signingBase = implode('&', $pairs).'&key='.(string) $this->config['secret'];
        $sign = strtoupper(hash('sha256', $signingBase));

        $base = rtrim($this->string('endpoints', 'pwa', 'https://static.kbzpay.com/pgw/pwa'), '/');

        return $base.'/#/?'.implode('&', $pairs).'&sign='.$sign;
    }

    /**
     * @param  array<string, mixed>  $bizContent
     * @return array<string, mixed>
     */
    private function envelope(string $method, string $version, array $bizContent, ?string $notifyUrl = null): array
    {
        $request = [
            'timestamp' => (string) now()->timestamp,
            'method' => $method,
            'nonce_str' => bin2hex(random_bytes(16)),
            'sign_type' => 'SHA256',
            'version' => $version,
            'biz_content' => $bizContent,
        ];

        if ($notifyUrl !== null) {
            $request['notify_url'] = $notifyUrl;
        }

        $fields = $this->signableFields($request);

        $request['sign'] = $this->signature->sign($fields, (string) ($this->config['secret'] ?? ''));

        return $request;
    }

    /**
     * KBZPay signs the top-level envelope fields plus the flattened
     * `biz_content` values as one parameter set.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function signableFields(array $request): array
    {
        $fields = $request;
        unset($fields['biz_content']);

        $bizContent = $request['biz_content'] ?? [];

        if (is_array($bizContent)) {
            foreach ($bizContent as $key => $value) {
                $fields[(string) $key] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function eventId(array $data, string $reference, string $transactionId): string
    {
        $timestamp = (string) ($data['timestamp'] ?? '');

        return 'kbzpay:'.hash('sha256', implode('|', [$reference, $transactionId, $timestamp]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapPaymentStatus(array $data): PaymentStatus
    {
        $tradeStatus = $data['trade_status'] ?? null;

        if (is_scalar($tradeStatus) && $tradeStatus !== '') {
            return $this->toNormalizedStatus((string) $tradeStatus);
        }

        $result = strtoupper((string) ($data['result'] ?? ''));

        if ($result === '') {
            return PaymentStatus::Processing;
        }

        // `result: SUCCESS` only means the API call was accepted; the payment
        // itself is still awaiting the customer.
        if (in_array($result, ['SUCCESS', 'PAY_SUCCESS', 'COMPLETED'], true)) {
            return PaymentStatus::Processing;
        }

        return $this->toNormalizedStatus($result);
    }

    private function toNormalizedStatus(string $status): PaymentStatus
    {
        return match (strtoupper($status)) {
            'SUCCESS', 'PAY_SUCCESS', 'COMPLETED' => PaymentStatus::Succeeded,
            'FAILED', 'PAY_FAILED', 'FAIL', 'ORDER_CLOSED', 'ORDER_EXPIRED',
            'CANCELLED', 'CANCELED', 'REFUND_FAILED' => PaymentStatus::Failed,
            default => PaymentStatus::Processing,
        };
    }

    private function title(CollectRequest $request): string
    {
        $configured = $this->config['title'] ?? null;
        $metadata = $request->metadata['title'] ?? $configured;

        return (string) ($metadata !== null && $metadata !== '' ? $metadata : 'Payment');
    }

    private function timeoutExpress(CollectRequest $request): string
    {
        $metadata = $request->metadata['timeout_express'] ?? $this->config['timeout_express'] ?? '120m';

        return (string) $metadata;
    }

    private function tradeType(): string
    {
        return $this->payType() === 'qr' ? 'PAY_BY_QRCODE' : 'PWAAPP';
    }

    private function payType(): string
    {
        return $this->string('pay_type', '', 'pwa');
    }

    private function merchantCode(): string
    {
        $merchant = $this->config['merchant_code'] ?? $this->config['merchant_id'] ?? '';

        if ($merchant === '') {
            throw new ProviderConfigurationException('KBZPay merchant_code is not configured.', 'KBZPAY_MERCHANT_CODE_MISSING');
        }

        return (string) $merchant;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
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
