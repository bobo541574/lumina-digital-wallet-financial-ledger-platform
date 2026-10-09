<?php

namespace App\Modules\PaymentProviders;

use App\Modules\PaymentProviders\Adapters\FakeProvider;
use App\Modules\PaymentProviders\Contracts\PaymentProviderInterface;
use App\Modules\PaymentProviders\Exceptions\ProviderUnavailableException;

class ProviderManager
{
    public function resolve(string $provider): PaymentProviderInterface
    {
        return match ($provider) {
            'fake' => new FakeProvider((string) config('payment.providers.fake.secret')),
            default => throw new ProviderUnavailableException($provider),
        };
    }
}
