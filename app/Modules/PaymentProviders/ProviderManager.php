<?php

namespace App\Modules\PaymentProviders;

use App\Modules\PaymentProviders\Adapters\AyaPayProvider;
use App\Modules\PaymentProviders\Adapters\FakePayoutProvider;
use App\Modules\PaymentProviders\Adapters\FakeProvider;
use App\Modules\PaymentProviders\Adapters\KbzPayPayoutProvider;
use App\Modules\PaymentProviders\Adapters\KbzPayProvider;
use App\Modules\PaymentProviders\Adapters\TwoC2PPayoutProvider;
use App\Modules\PaymentProviders\Adapters\TwoC2PProvider;
use App\Modules\PaymentProviders\Adapters\WaveMoneyPayoutProvider;
use App\Modules\PaymentProviders\Adapters\WaveMoneyProvider;
use App\Modules\PaymentProviders\Contracts\CollectableProvider;
use App\Modules\PaymentProviders\Contracts\PaymentProviderInterface;
use App\Modules\PaymentProviders\Contracts\PayoutProviderInterface;
use App\Modules\PaymentProviders\Exceptions\ProviderUnavailableException;

class ProviderManager
{
    public function resolve(string $provider): PaymentProviderInterface
    {
        return match ($provider) {
            'fake' => new FakeProvider((string) config('payment.providers.fake.secret')),
            'kbzpay' => new KbzPayProvider((array) config('payment.providers.kbzpay')),
            'wavemoney' => new WaveMoneyProvider((array) config('payment.providers.wavemoney')),
            'aya' => new AyaPayProvider((array) config('payment.providers.aya')),
            '2c2p' => new TwoC2PProvider((array) config('payment.providers.2c2p')),
            default => throw new ProviderUnavailableException($provider),
        };
    }

    public function collectProvider(string $provider): CollectableProvider
    {
        $adapter = $this->resolve($provider);

        if (! $adapter instanceof CollectableProvider) {
            throw new ProviderUnavailableException($provider);
        }

        return $adapter;
    }

    public function payoutProvider(string $provider): PayoutProviderInterface
    {
        return match ($provider) {
            'fake' => new FakePayoutProvider,
            'kbzpay' => new KbzPayPayoutProvider((array) config('payment.providers.kbzpay')),
            'wavemoney' => new WaveMoneyPayoutProvider((array) config('payment.providers.wavemoney')),
            'aya' => new AyaPayPayoutProvider((array) config('payment.providers.aya')),
            '2c2p' => new TwoC2PPayoutProvider((array) config('payment.providers.2c2p')),
            default => throw new ProviderUnavailableException($provider),
        };
    }
}
