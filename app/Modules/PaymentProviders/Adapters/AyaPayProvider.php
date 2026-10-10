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
use App\Modules\PaymentProviders\Support\Signature\AyaCrypto;

/**
 * AYA Pay (`aya`) pay-in adapter.
 *
 * Collect: pay is synchronous — a QR session (`requestQRPayment`) or a push
 * payment (`requestPushPayment`) to a `customerPhone`. Push moves money
 * immediately; QR waits for the customer. Responses may carry `paymentResult`,
 * an AES-256-ECB encrypted payload decrypted with the merchant's `encrypt_key`.
 *
 * AYA exposes no callback signature scheme, so `verifySignature()` stays false:
 * settlement is confirmed via `checkRequestPayment` (query) or reconciliation,
 * never from an unsigned webhook.
 *
 * @see https://github.com/hakhant21/myanmar-payments
 * @see https://github.com/hakhant21/myanmar-payments
 */
class AyaPayProvider implements CollectableProvider, PaymentProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ProviderHttp $http = new ProviderHttp('aya'),
        private readonly AyaCrypto $crypto = new AyaCrypto,
    ) {}

    public function name(): string
    {
        return 'aya';
    }

    public function createPayment(CollectRequest $request): PaymentIntentResult
    {
        $push = $this->isPushPayment($request);

        if ($push && $this->customerPhone($request) === '') {
            throw new ProviderConfigurationException('AYA push payment requires customer_phone.', 'AYA_CUSTOMER_PHONE_MISSING');
        }

        $url = $push ? $this->pushUrl() : $this->qrUrl();
        $payload = $this->basePayload($request);

        if ($push) {
            $payload['customerPhone'] = $this->customerPhone($request);

            if ($this->serviceCode() !== '') {
                $payload['serviceCode'] = $this->serviceCode();

                $timeLimit = $request->metadata['time_limit'] ?? $this->config['time_limit'] ?? null;
                if ($timeLimit !== null && $timeLimit !== '') {
                    $payload['timelimit'] = (int) $timeLimit;
                }
            }
        }

        $response = $this->http->post($url, $payload, $this->authorizedHeaders(), $this->timeout());

        return $this->intent($response, $request->merchantReference, $request->currency);
    }

    public function queryStatus(string $providerTransactionId): PaymentIntentResult
    {
        $response = $this->http->post(
            $this->string('endpoints', 'query_payment'),
            ['externalTransactionId' => $providerTransactionId],
            $this->authorizedHeaders(),
            $this->timeout(),
        );

        return $this->intent($response, $providerTransactionId, 'MMK');
    }

    public function verifySignature(string $rawPayload, ?string $signature): bool
    {
        // AYA does not sign callbacks. Never trust one as settlement.
        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseEvent(array $payload): ProviderWebhookEvent
    {
        $data = $this->data($payload);

        $reference = (string) ($data['externalTransactionId'] ?? $payload['externalTransactionId'] ?? '');
        $transactionId = (string) ($data['referenceNumber'] ?? $data['referenceNo'] ?? '');

        return new ProviderWebhookEvent(
            eventId: $transactionId !== '' ? $transactionId : hash('sha256', json_encode($payload)),
            eventType: 'payment',
            reference: $reference,
            providerTransactionId: $transactionId !== '' ? $transactionId : null,
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'MMK')),
            status: $this->mapStatus($data['status'] ?? $payload['status'] ?? '')->value,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function intent(array $response, string $reference, string $currency): PaymentIntentResult
    {
        $data = $this->data($response);
        $status = $this->dataStatus($response);

        return new PaymentIntentResult(
            provider: 'aya',
            status: $status,
            providerReference: (string) ($data['externalTransactionId'] ?? $reference),
            providerTransactionId: (string) ($data['referenceNumber'] ?? $data['referenceNo'] ?? '') !== ''
                ? (string) ($data['referenceNumber'] ?? $data['referenceNo'] ?? '')
                : null,
            paymentUrl: null,
            extra: [
                'qr_code' => (string) ($data['qrdata'] ?? ''),
                'expires_at' => $data['expiredAt'] ?? null,
            ],
            raw: $response,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function dataStatus(array $response): PaymentStatus
    {
        $data = $this->data($response);

        // The meaningful status may live in the encrypted `paymentResult`.
        if (($response['paymentResult'] ?? null) !== null && $this->encryptKey() !== '') {
            try {
                $decoded = $this->crypto->decrypt((string) $response['paymentResult'], $this->encryptKey());
                $parsed = json_decode($decoded, true);

                if (is_array($parsed) && ($parsed['status'] ?? null) !== null) {
                    return $this->mapStatus($parsed['status']);
                }
            } catch (ProviderConfigurationException) {
                // Fall through to the top-level status.
            }
        }

        if ($data['status'] !== null) {
            return $this->mapStatus($data['status']);
        }

        if ($response['status'] !== null) {
            return $this->mapStatus($response['status']);
        }

        return PaymentStatus::Processing;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function basePayload(CollectRequest $request): array
    {
        $payload = [
            'amount' => (string) $request->amount,
            'currency' => $request->currency,
            'externalTransactionId' => $request->merchantReference,
        ];

        $description = $request->metadata['external_additional_data'] ?? $request->metadata['description'] ?? $request->metadata['title'] ?? '';

        if (is_string($description) && $description !== '') {
            $payload['externalAdditionalData'] = $description;
        }

        return $payload;
    }

    private function isPushPayment(CollectRequest $request): bool
    {
        return array_key_exists('customer_phone', $request->metadata)
            || array_key_exists('customerPhone', $request->metadata)
            || $this->serviceCode() !== '';
    }

    private function customerPhone(CollectRequest $request): string
    {
        $phone = $request->metadata['customer_phone'] ?? $request->metadata['customerPhone'] ?? null;

        if (! is_string($phone) || $phone === '') {
            throw new ProviderConfigurationException('AYA push payment requires customer_phone.', 'AYA_CUSTOMER_PHONE_MISSING');
        }

        return $phone;
    }

    private function serviceCode(): string
    {
        return (string) ($this->config['service_code'] ?? '');
    }

    private function encryptKey(): string
    {
        return (string) ($this->config['encrypt_key'] ?? '');
    }

    /**
     * Login to AYA and return the merchant token, mirroring the reference flow.
     */
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

    /**
     * @return array<string, string>
     */
    private function authorizedHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->login(),
        ];
    }

    private function pushUrl(): string
    {
        return $this->serviceCode() === ''
            ? $this->string('endpoints', 'push_payment')
            : $this->string('endpoints', 'push_payment_v2');
    }

    private function qrUrl(): string
    {
        return $this->string('endpoints', 'qr_payment');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function data(array $payload): array
    {
        $data = $payload['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    private function mapStatus(mixed $status): PaymentStatus
    {
        if (is_int($status) || is_float($status) || (is_string($status) && is_numeric($status))) {
            return (int) $status === 0 ? PaymentStatus::Processing : PaymentStatus::Processing;
        }

        if (! is_string($status) || $status === '') {
            return PaymentStatus::Processing;
        }

        return match (strtolower($status)) {
            'success', 'succeeded', 'paid', 'completed', 'done' => PaymentStatus::Succeeded,
            'pending', 'processing', 'requested', 'created' => PaymentStatus::Processing,
            'failed', 'error', 'rejected', 'declined', 'cancelled', 'canceled',
            'expired', 'timeout' => PaymentStatus::Failed,
            default => PaymentStatus::Processing,
        };
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
