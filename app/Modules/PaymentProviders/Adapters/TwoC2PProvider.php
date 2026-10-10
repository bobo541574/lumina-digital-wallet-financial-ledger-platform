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
use App\Modules\PaymentProviders\Support\Signature\TwoC2PJwt;
use Throwable;

/**
 * 2C2P (`2c2p`) pay-in adapter.
 *
 * Collect: `paymentToken` — the request body is an HS256 JWT envelope; the
 * response's `payload` is another JWT holding `webPaymentUrl` for the hosted
 * page. Settlement arrives via a signed callback that is also a JWT, so
 * verification is just "decode it and check merchantID".
 *
 * @see https://github.com/hakhant21/myanmar-payments
 */
class TwoC2PProvider implements CollectableProvider, PaymentProviderInterface
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

    public function createPayment(CollectRequest $request): PaymentIntentResult
    {
        $payload = [
            'merchantID' => $this->merchantId(),
            'invoiceNo' => $request->merchantReference,
            'description' => $this->description($request),
            'amount' => (string) $request->amount,
            'currencyCode' => $request->currency,
            'frontendReturnUrl' => $request->redirectUrl,
            'backendReturnUrl' => $request->callbackUrl,
            'locale' => $this->locale($request),
        ];

        foreach ($this->metadataAliases() as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $request->metadata)) {
                    $payload[$field] = $request->metadata[$alias];

                    break;
                }
            }
        }

        $data = $this->call('payment_token', $payload);

        return new PaymentIntentResult(
            provider: '2c2p',
            status: $this->mapStatus($data),
            providerReference: $request->merchantReference,
            providerTransactionId: $this->transactionId($data, $request->merchantReference),
            paymentUrl: isset($data['webPaymentUrl']) ? (string) $data['webPaymentUrl'] : null,
            raw: $data,
        );
    }

    public function queryStatus(string $providerTransactionId): PaymentIntentResult
    {
        $data = $this->call('transaction_status', [
            'paymentToken' => $providerTransactionId,
            'locale' => $this->locale(),
            'additionalInfo' => false,
        ]);

        return new PaymentIntentResult(
            provider: '2c2p',
            status: $this->mapStatus($data),
            providerReference: (string) ($data['invoiceNo'] ?? $providerTransactionId),
            providerTransactionId: $this->transactionId($data, $providerTransactionId),
            paymentUrl: isset($data['webPaymentUrl']) ? (string) $data['webPaymentUrl'] : null,
            raw: $data,
        );
    }

    public function verifySignature(string $rawPayload, ?string $signature): bool
    {
        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return false;
        }

        $token = $this->extractToken($payload, $signature);

        if ($token === '') {
            return false;
        }

        try {
            $decoded = $this->jwt->decode($token, $this->secretKey());
        } catch (Throwable) {
            return false;
        }

        return (string) ($decoded['merchantID'] ?? '') === $this->merchantId();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseEvent(array $payload): ProviderWebhookEvent
    {
        $data = $this->decode($payload);

        return new ProviderWebhookEvent(
            eventId: (string) ($data['paymentID'] ?? $data['txnId'] ?? $data['invoiceNo'] ?? ''),
            eventType: 'payment',
            reference: (string) ($data['invoiceNo'] ?? ''),
            providerTransactionId: $this->transactionId($data, ''),
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currencyCode'] ?? 'MMK')),
            status: $this->mapStatus($data)->value,
            raw: $payload,
        );
    }

    /**
     * The webhook body wraps the actual payment result in a `payload` JWT.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function decode(array $payload): array
    {
        $token = $this->extractToken($payload, null);

        if ($token === '') {
            return $payload;
        }

        try {
            return $this->jwt->decode($token, $this->secretKey());
        } catch (Throwable) {
            return $payload;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractToken(array $payload, ?string $fallback): string
    {
        foreach (['payload', 'Payload'] as $key) {
            if (is_string($payload[$key] ?? null) && $payload[$key] !== '') {
                return (string) $payload[$key];
            }
        }

        return (string) ($fallback ?? '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapStatus(array $data): PaymentStatus
    {
        $result = $data['paymentResultDetails']['code'] ?? null;
        $resultCode = is_scalar($result) ? (string) $result : '';
        $respCode = (string) ($data['respCode'] ?? '');

        if ($resultCode !== '') {
            return match ($resultCode) {
                '00' => PaymentStatus::Succeeded,
                '02' => PaymentStatus::Processing,
                default => PaymentStatus::Failed,
            };
        }

        return match (true) {
            in_array($respCode, ['0000', '1000', '1001', '1002', '1003'], true) => PaymentStatus::Processing,
            default => PaymentStatus::Failed,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transactionId(array $data, string $default): string
    {
        foreach (['paymentID', 'paymentToken', 'txnId'] as $key) {
            $value = $data[$key] ?? null;

            if (is_scalar($value) && $value !== '') {
                return (string) $value;
            }
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function call(string $endpoint, array $body): array
    {
        $response = $this->http()->post(
            $this->string('endpoints', $endpoint),
            ['payload' => $this->jwt->encode($body, $this->secretKey())],
            $this->headers(),
            $this->timeout(),
        );

        $token = $response['payload'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ProviderConfigurationException('2C2P response payload is missing.', 'TWOC2P_PAYLOAD_MISSING');
        }

        return $this->jwt->decode($token, $this->secretKey());
    }

    private function description(CollectRequest $request): string
    {
        $value = $request->metadata['description'] ?? $request->metadata['title'] ?? 'Payment';

        return (string) $value;
    }

    private function locale(?CollectRequest $request = null): string
    {
        $value = $request?->metadata['locale'] ?? $this->config['locale'] ?? 'en';

        return (string) $value;
    }

    /**
     * @return array<string, list<string>>
     */
    private function metadataAliases(): array
    {
        return [
            'paymentChannel' => ['paymentChannel', 'payment_channel'],
            'request3DS' => ['request3DS', 'request_3ds'],
            'userDefined1' => ['userDefined1', 'user_defined_1'],
            'userDefined2' => ['userDefined2', 'user_defined_2'],
            'nonceStr' => ['nonceStr', 'nonce_str'],
            'idempotencyID' => ['idempotencyID', 'idempotency_id'],
            'paymentExpiry' => ['paymentExpiry', 'payment_expiry'],
            'immediatePayment' => ['immediatePayment', 'immediate_payment'],
        ];
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
