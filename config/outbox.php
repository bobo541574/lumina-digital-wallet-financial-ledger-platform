<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbox worker
    |--------------------------------------------------------------------------
    |
    | Attempts, batch size and retry backoff (in seconds, per attempt) for the
    | transactional outbox worker. After `max_attempts` a message is parked as
    | `failed` for operator review.
    |
    */

    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 5),

    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),

    /*
    | A message picked up by a worker is marked `processing`. If the worker dies
    | before confirming delivery, the message is reclaimed once it has been in
    | `processing` longer than this many seconds.
    */

    'processing_timeout' => (int) env('OUTBOX_PROCESSING_TIMEOUT', 300),

    'backoff' => [5, 30, 120, 600, 3600],

];
