<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * `payment_events` is a high-volume, append-only event log and is partitioned
 * monthly by RANGE(occurred_at) (BLUEPRINT "7.2 Partition strategy").
 *
 * PostgreSQL requires the partition key to be part of every primary key on a
 * partitioned table, so the key is `(id, occurred_at)`. The message id remains
 * globally unique by application convention (UUID); a global unique constraint
 * cannot be expressed on a partitioned table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE payment_events (
                id             uuid         NOT NULL,
                aggregate_type varchar(50)  NOT NULL,
                aggregate_id   uuid         NOT NULL,
                event_type     varchar(100) NOT NULL,
                occurred_at    timestamptz  NOT NULL,
                payload        jsonb        NOT NULL DEFAULT '{}'::jsonb,
                created_at     timestamptz  NOT NULL DEFAULT now(),
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at)
        SQL);

        // Declared on the parent so every current and future partition
        // inherits them automatically (partition-aware indexes).
        DB::statement('CREATE INDEX payment_events_aggregate_idx ON payment_events (aggregate_type, aggregate_id)');
        DB::statement('CREATE INDEX payment_events_event_type_idx ON payment_events (event_type, occurred_at DESC)');

        // A default partition guarantees writes always land somewhere, even if
        // monthly partitions have not been pre-created. PartitionManager moves
        // rows out of it when the matching monthly partition is created.
        DB::statement('CREATE TABLE payment_events_default PARTITION OF payment_events DEFAULT');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS payment_events CASCADE');
    }
};
