<?php

namespace App\Modules\PaymentProviders\Contracts;

use App\Modules\PaymentProviders\DTOs\CollectRequest;
use App\Modules\PaymentProviders\DTOs\PaymentIntentResult;

/**
 * A provider that can open a pay-in (collect) session. The result carries the
 * redirect/authenticate URL the customer is sent to, plus a normalized status:
 * providers may settle synchronously (succeeded/failed) or async (processing),
 * in which case settlement arrives via a signed webhook.
 */
interface CollectableProvider
{
    public function name(): string;

    public function createPayment(CollectRequest $request): PaymentIntentResult;

    public function queryStatus(string $providerTransactionId): PaymentIntentResult;
}
