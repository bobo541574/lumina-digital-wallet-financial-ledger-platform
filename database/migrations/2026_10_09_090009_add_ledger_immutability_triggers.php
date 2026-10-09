<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_ledger_modification()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'ledger_immutable: % on % is not permitted', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'raise_exception';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(
            'CREATE TRIGGER journal_entries_immutable
             BEFORE UPDATE OR DELETE ON journal_entries
             FOR EACH ROW EXECUTE FUNCTION prevent_ledger_modification();'
        );

        DB::unprepared(
            'CREATE TRIGGER ledger_postings_immutable
             BEFORE UPDATE OR DELETE ON ledger_postings
             FOR EACH ROW EXECUTE FUNCTION prevent_ledger_modification();'
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS journal_entries_immutable ON journal_entries;');
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_postings_immutable ON ledger_postings;');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_ledger_modification();');
    }
};
