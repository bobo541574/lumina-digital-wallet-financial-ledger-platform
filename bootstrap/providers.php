<?php

use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Ledger\Providers\LedgerServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    LedgerServiceProvider::class,
];
