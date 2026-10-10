<?php

use App\Modules\PaymentProviders\Adapters\AyaPayPayoutProvider;
use App\Modules\PaymentProviders\Adapters\KbzPayPayoutProvider;
use App\Modules\PaymentProviders\Adapters\TwoC2PPayoutProvider;
use App\Modules\PaymentProviders\Adapters\WaveMoneyPayoutProvider;
use App\Modules\PaymentProviders\Exceptions\ProviderUnavailableException;
use App\Modules\Withdrawals\Models\Withdrawal;

dataset('unconfigured payouts', [
    'kbzpay' => [fn () => new KbzPayPayoutProvider([])],
    'wavemoney' => [fn () => new WaveMoneyPayoutProvider([])],
    'aya' => [fn () => new AyaPayPayoutProvider([])],
    '2c2p' => [fn () => new TwoC2PPayoutProvider([])],
]);

it('refuses to pay out when the provider payout endpoint is not configured', function (callable $factory) {
    $withdrawal = new Withdrawal([
        'reference' => 'WD-1',
        'amount' => 1000,
        'currency_code' => 'MMK',
        'destination' => ['phone' => '09900000000'],
    ]);

    expect(fn () => $factory()->initiate($withdrawal))->toThrow(ProviderUnavailableException::class);
})->with('unconfigured payouts');
