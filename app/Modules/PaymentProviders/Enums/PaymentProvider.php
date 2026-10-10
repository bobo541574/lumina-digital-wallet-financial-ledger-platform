<?php

namespace App\Modules\PaymentProviders\Enums;

/**
 * The payment providers the platform can talk to. Values are the stable keys
 * used in `config/payment.php`, deposit rows, and webhook routes.
 */
enum PaymentProvider: string
{
    case Fake = 'fake';
    case KbzPay = 'kbzpay';
    case WaveMoney = 'wavemoney';
    case Aya = 'aya';
    case TwoC2P = '2c2p';

    /**
     * Provider keys a customer can initiate a deposit against.
     *
     * @return list<string>
     */
    public static function collectable(): array
    {
        return array_map(static fn (self $provider): string => $provider->value, self::cases());
    }
}
