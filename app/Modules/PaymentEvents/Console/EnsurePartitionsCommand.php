<?php

namespace App\Modules\PaymentEvents\Console;

use App\Modules\PaymentEvents\Partitioning\PartitionManager;
use Illuminate\Console\Command;

class EnsurePartitionsCommand extends Command
{
    protected $signature = 'partitions:ensure
        {--table=payment_events : Partitioned table to manage}
        {--column=occurred_at : RANGE partition key column}
        {--months=3 : Number of future months to pre-create}
        {--check : Only report missing partitions without creating them}';

    protected $description = 'Pre-create monthly partitions for append-only event tables';

    public function handle(PartitionManager $partitions): int
    {
        $table = (string) $this->option('table');
        $column = (string) $this->option('column');
        $months = (int) $this->option('months');

        if ($this->option('check')) {
            $missing = $partitions->missing($table, $months);

            if ($missing === []) {
                $this->info("All partitions present for {$table}.");

                return self::SUCCESS;
            }

            $this->warn('Missing partitions: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $created = $partitions->ensure($table, $column, $months);

        if ($created === []) {
            $this->info("No new partitions required for {$table}.");
        } else {
            $this->info('Created partitions: '.implode(', ', $created));
        }

        return self::SUCCESS;
    }
}
