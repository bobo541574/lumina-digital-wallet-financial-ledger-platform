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
use App\Modules\PaymentProviders\Support\Signature\WaveMoneyHash;

/**
 * WaveMoney (`wavemoney`) pay-in adapter.
 *
 * Collect: form-encoded `POST /payment` carrying an HMAC over the fixed field
 * order [ttl, merchant_id, order_id, amount, backend_result_url,
 * merchant_reference_id]; the customer is sent to
 * `/authenticate?transaction_id=...`.
 *
 * Webhook: the callback carries `hashValue` over the same fixed field list plus
 * the returned transaction identifiers.
 *
 * @see https://github.com/hakhant21/myanmar-payments
 */
class WaveMoneyProvider implements CollectableProvider, PaymentProviderInterface
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

    public function createPayment(CollectRequest $request): PaymentIntentResult
    {
        $timeToLive = (string) ($request->metadata['time_to_live_in_seconds'] ?? $this->config['time_to_live_in_seconds'] ?? 60);
        $orderId = (string) ($request->metadata['order_id'] ?? $request->merchantReference);

        $payload = [
            'time_to_live_in_seconds' => $timeToLive,
            'merchant_id' => $this->merchantId(),
            'order_id' => $orderId,
            'merchant_reference_id' => $request->merchantReference,
            'frontend_result_url' => $request->redirectUrl,
            'backend_result_url' => $request->callbackUrl,
            'amount' => (string) $request->amount,
            'payment_description' => $this->description($request),
            'merchant_name' => $this->merchantName($request),
            'items' => $this->itemsJson($request),
        ];

        $payload['hash'] = $this->hash->sign([
            $payload['time_to_live_in_seconds'],
            $payload['merchant_id'],
            $payload['order_id'],
            $payload['amount'],
            $payload['backend_result_url'],
            $payload['merchant_reference_id'],
        ], $this->secretKey());

        $response = $this->http()->postForm(
            $this->string('endpoints', 'payment', 'https://testpayments.wavemoney.io:8107/payment'),
            $payload,
            ['Accept' => 'application/json'],
            $this->timeout(),
        );

        $transactionId = (string) ($response['transaction_id'] ?? '');

        return new PaymentIntentResult(
            provider: 'wavemoney',
            status: $this->mapCreateStatus($response, $transactionId),
            providerReference: $request->merchantReference,
            providerTransactionId: $transactionId !== '' ? $transactionId : null,
            paymentUrl: $transactionId !== '' ? $this->authenticateUrl($transactionId) : null,
            raw: $response,
        );
    }

    public function queryStatus(string $providerTransactionId): PaymentIntentResult
    {
        // WaveMoney deliberately has no status query; settlement arrives on the
        // backend callback, which is what the webhook pipeline consumes.
        throw new ProviderConfigurationException(
            'WaveMoney does not support status queries; use the backend callback.',
            'WAVEMONEY_STATUS_QUERY_UNSUPPORTED',
        );
    }

    public function verifySignature(string $rawPayload, ?string $signature): bool
    {
        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return false;
        }

        $provided = (string) ($payload['hashValue'] ?? $signature);

        if ($provided === '') {
            return false;
        }

        if ((string) ($payload['merchantId'] ?? '') !== $this->merchantId()) {
            return false;
        }

        return $this->hash->verify([
            $payload['status'] ?? null,
            $payload['timeToLiveSeconds'] ?? null,
            $payload['merchantId'] ?? null,
            $payload['orderId'] ?? null,
            $payload['amount'] ?? null,
            $payload['backendResultUrl'] ?? null,
            $payload['merchantReferenceId'] ?? null,
            $payload['initiatorMsisdn'] ?? null,
            $payload['transactionId'] ?? null,
            $payload['paymentRequestId'] ?? null,
            $payload['requestTime'] ?? null,
        ], $provided, $this->secretKey());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseEvent(array $payload): ProviderWebhookEvent
    {
        $transactionId = (string) ($payload['transactionId'] ?? '');

        return new ProviderWebhookEvent(
            eventId: (string) ($payload['paymentRequestId'] ?? ($transactionId !== '' ? $transactionId : hash('sha256', json_encode($payload)))),
            eventType: 'payment',
            reference: (string) ($payload['merchantReferenceId'] ?? $payload['orderId'] ?? ''),
            providerTransactionId: $transactionId !== '' && $transactionId !== 'null' ? $transactionId : null,
            amount: (int) ($payload['amount'] ?? 0),
            currency: strtoupper((string) ($payload['currency'] ?? 'MMK')),
            status: $this->mapCallbackStatus((string) ($payload['status'] ?? ''))->value,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function mapCreateStatus(array $response, string $transactionId): PaymentStatus
    {
        $message = strtolower((string) ($response['message'] ?? ''));

        if ($transactionId === '' || in_array($message, ['invalid_hash', 'no record found'], true)) {
            return PaymentStatus::Failed;
        }

        return PaymentStatus::Processing;
    }

    private function mapCallbackStatus(string $status): PaymentStatus
    {
        return match (strtoupper($status)) {
            'PAYMENT_CONFIRMED' => PaymentStatus::Succeeded,
            'TRANSACTION_TIMED_OUT', 'ACCOUNT_LOCKED', 'BILL_COLLECTION_FAILED',
            'PAYMENT_REQUEST_CANCELLED', 'SCHEDULER_TRANSACTION_TIMED_OUT',
            'INSUFFICIENT_BALANCE' => PaymentStatus::Failed,
            default => PaymentStatus::Processing,
        };
    }

    private function authenticateUrl(string $transactionId): string
    {
        $base = rtrim($this->string('endpoints', 'authenticate', 'https://testpayments.wavemoney.io/authenticate'), '/');

        return $base.'?transaction_id='.urlencode($transactionId);
    }

    private function description(CollectRequest $request): string
    {
        $value = $request->metadata['payment_description'] ?? $request->metadata['description'] ?? $this->config['payment_description'] ?? 'Payment';

        return (string) $value;
    }

    private function merchantName(CollectRequest $request): string
    {
        $value = $request->metadata['merchant_name'] ?? $this->config['merchant_name'] ?? 'Merchant';

        return (string) $value;
    }

    private function itemsJson(CollectRequest $request): string
    {
        $items = $request->metadata['items'] ?? [[
            'name' => (string) ($request->metadata['item_name'] ?? 'Order'),
            'amount' => $request->amount,
        ]];

        $encoded = json_encode($items, JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            throw new ProviderConfigurationException('WaveMoney items metadata could not be encoded.', 'WAVEMONEY_ITEMS_INVALID');
        }

        return $encoded;
    }

    private function merchantId(): string
    {
        $merchantId = (string) ($this->config['merchant_id'] ?? '');

        if ($merchantId === '') {
            throw new ProviderConfigurationException('WaveMoney merchant_id is not configured.', 'WAVEMONEY_MERCHANT_ID_MISSING');
        }

        return $merchantId;
    }

    private function secretKey(): string
    {
        $secret = (string) ($this->config['secret_key'] ?? '');

        if ($secret === '') {
            throw new ProviderConfigurationException('WaveMoney secret_key is not configured.', 'WAVEMONEY_SECRET_KEY_MISSING');
        }

        return $secret;
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
