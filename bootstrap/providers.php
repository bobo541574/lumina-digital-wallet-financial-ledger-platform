<?php

use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Ledger\Providers\LedgerServiceProvider;
use App\Modules\Outbox\Providers\OutboxServiceProvider;
use App\Modules\PaymentEvents\Providers\PaymentEventsServiceProvider;
use App\Modules\Reconciliation\Providers\ReconciliationServiceProvider;
use App\Modules\Shared\Providers\SecurityServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SecurityServiceProvider::class,
    IdentityServiceProvider::class,
    LedgerServiceProvider::class,
    OutboxServiceProvider::class,
    PaymentEventsServiceProvider::class,
    ReconciliationServiceProvider::class,
];
