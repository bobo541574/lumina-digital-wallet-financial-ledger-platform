<?php

namespace App\Modules\PaymentEvents\Partitioning;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates and inspects monthly RANGE partitions for append-only event tables
 * (BLUEPRINT "7.2 Partition strategy"). Partitions are named
 * `<table>_<YYYY>_<MM>` and every operation is idempotent.
 *
 * A pre-created table becomes a partition only after rows that had previously
 * been routed to the table's DEFAULT partition are moved across, so attaching a
 * new monthly range never fails on overlapping rows.
 */
class PartitionManager
{
    /**
     * Ensure a partition exists for the current month and the following
     * $monthsAhead months.
     *
     * @return list<string> names of the partitions actually created
     */
    public function ensure(string $table, string $column, int $monthsAhead = 3, ?CarbonImmutable $from = null): array
    {
        $anchor = ($from ?? CarbonImmutable::now())->startOfMonth();
        $created = [];

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $month = $anchor->addMonths($i);

            if ($this->createPartition($table, $column, $month)) {
                $created[] = $this->partitionName($table, $month);
            }
        }

        return $created;
    }

    public function createPartition(string $table, string $column, CarbonImmutable $month): bool
    {
        $name = $this->partitionName($table, $month);

        if ($this->exists($name)) {
            return false;
        }

        $from = $month->startOfMonth()->toDateTimeString();
        $to = $month->addMonth()->startOfMonth()->toDateTimeString();

        DB::transaction(function () use ($table, $column, $name, $from, $to): void {
            DB::statement("CREATE TABLE {$name} (LIKE {$table} INCLUDING DEFAULTS INCLUDING CONSTRAINTS)");

            DB::statement(
                "INSERT INTO {$name} SELECT * FROM {$table} WHERE {$column} >= ? AND {$column} < ?",
                [$from, $to],
            );
            DB::statement(
                "DELETE FROM {$table} WHERE {$column} >= ? AND {$column} < ?",
                [$from, $to],
            );

            DB::statement("ALTER TABLE {$table} ATTACH PARTITION {$name} FOR VALUES FROM ('{$from}') TO ('{$to}')");
        });

        return true;
    }

    public function partitionName(string $table, CarbonImmutable $month): string
    {
        return sprintf('%s_%s', $table, $month->format('Y_m'));
    }

    public function exists(string $name): bool
    {
        return DB::table('pg_class')
            ->where('relname', $name)
            ->whereIn('relkind', ['r', 'p'])
            ->exists();
    }

    /**
     * @return list<string> partition relation names attached to the table
     */
    public function existing(string $table): array
    {
        $rows = DB::select(
            <<<'SQL'
                SELECT child.relname AS name
                FROM pg_inherits
                JOIN pg_class child ON child.oid = pg_inherits.inhrelid
                JOIN pg_class parent ON parent.oid = pg_inherits.inhparent
                WHERE parent.relname = ?
                ORDER BY child.relname
            SQL,
            [$table],
        );

        return array_map(fn ($row) => $row->name, $rows);
    }

    /**
     * Monthly partitions that should exist but do not.
     *
     * @return list<string>
     */
    public function missing(string $table, int $monthsAhead = 3, ?CarbonImmutable $from = null): array
    {
        $anchor = ($from ?? CarbonImmutable::now())->startOfMonth();
        $existing = $this->existing($table);
        $missing = [];

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $name = $this->partitionName($table, $anchor->addMonths($i));

            if (! in_array($name, $existing, true)) {
                $missing[] = $name;
            }
        }

        return $missing;
    }
}
