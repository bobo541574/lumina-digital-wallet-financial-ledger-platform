<?php

namespace App\Modules\Shared\Logging;

use App\Modules\Shared\Support\Redactor;
use Monolog\LogRecord;

/**
 * Monolog processor that strips sensitive values from every log record before
 * it is written. Registered on the application log channels.
 */
class RedactingProcessor
{
    public function __construct(private readonly Redactor $redactor) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return new LogRecord(
            datetime: $record->datetime,
            channel: $record->channel,
            level: $record->level,
            message: $record->message,
            context: $this->redactor->redact($record->context),
            extra: $this->redactor->redact($record->extra),
            formatted: $record->formatted,
        );
    }
}
