<?php

namespace App\Modules\PaymentProviders\DTOs;

use App\Modules\PaymentProviders\Exceptions\ProviderConfigurationException;

final readonly class CollectRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $merchantReference,
        public int $amount,
        public string $currency,
        public string $callbackUrl,
        public string $redirectUrl,
        public array $metadata = [],
    ) {
        if ($merchantReference === '') {
            throw new ProviderConfigurationException('merchantReference is required.', 'COLLECT_INVALID_MERCHANT_REFERENCE');
        }

        if ($amount <= 0) {
            throw new ProviderConfigurationException('amount must be greater than zero.', 'COLLECT_INVALID_AMOUNT');
        }

        if ($currency === '') {
            throw new ProviderConfigurationException('currency is required.', 'COLLECT_INVALID_CURRENCY');
        }

        if (! filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            throw new ProviderConfigurationException('callbackUrl must be a valid URL.', 'COLLECT_INVALID_CALLBACK_URL');
        }

        if ($redirectUrl !== '' && ! filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            throw new ProviderConfigurationException('redirectUrl must be a valid URL.', 'COLLECT_INVALID_REDIRECT_URL');
        }
    }
}
