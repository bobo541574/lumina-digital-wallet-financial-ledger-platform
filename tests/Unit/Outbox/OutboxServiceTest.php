<?php

use App\Modules\Outbox\Exceptions\OutboxNotTransactionalException;
use App\Modules\Outbox\Services\OutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('refuses to record an outbox message outside a transaction', function () {
    DB::shouldReceive('transactionLevel')->once()->andReturn(0);

    app(OutboxService::class)->record('transfer', (string) Str::uuid(), 'transfer.completed');
})->throws(OutboxNotTransactionalException::class);
