<?php

namespace App\Modules\Outbox\Console;

use App\Modules\Outbox\Services\OutboxProcessor;
use Illuminate\Console\Command;

class ProcessOutboxCommand extends Command
{
    protected $signature = 'outbox:process
        {--limit= : Maximum number of messages to claim in this run}';

    protected $description = 'Deliver pending transactional outbox messages';

    public function handle(OutboxProcessor $processor): int
    {
        $limit = (int) ($this->option('limit') ?? config('outbox.batch_size', 100));

        $processed = $processor->process($limit);

        $this->info("Processed {$processed} outbox message(s).");

        return self::SUCCESS;
    }
}
