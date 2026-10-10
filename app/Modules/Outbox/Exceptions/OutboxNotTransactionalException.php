<?php

namespace App\Modules\Outbox\Exceptions;

use RuntimeException;

/**
 * Thrown when an outbox message is recorded outside a database transaction.
 *
 * The outbox guarantee only holds when the message row commits together with
 * the financial write it describes; recording it in autocommit would allow the
 * money to move without the event, or the event without the money.
 */
class OutboxNotTransactionalException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Outbox messages must be recorded inside a database transaction.');
    }
}
