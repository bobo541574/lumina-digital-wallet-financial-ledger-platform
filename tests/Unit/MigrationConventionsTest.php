<?php

use Illuminate\Support\Facades\File;

/*
 * Guardrails for the identifier conventions documented in
 * docs/architecture/overview.md ("Column & reference conventions").
 *
 * Domain tables use real PostgreSQL `uuid` columns for primary keys and
 * foreign references. A UUID must never be stored in a `string`/`varchar`
 * column, and polymorphic pairs must use the `uuidMorphs` helpers. These
 * tests fail loudly if a future migration regresses either rule.
 */

/**
 * Non-UUID identifiers that legitimately live in string columns because they
 * originate outside the platform (provider or tracing systems).
 *
 * @return list<string>
 */
function externalStringIdAllowlist(): array
{
    return [
        'event_id',
        'provider_transaction_id',
        'correlation_id',
    ];
}

/**
 * Laravel-internal tables that intentionally keep their default bigint keys.
 *
 * @return list<string>
 */
function frameworkKeyMigrations(): array
{
    return [
        '0001_01_01_000000_create_users_table.php',
        '0001_01_01_000001_create_cache_table.php',
        '0001_01_01_000002_create_jobs_table.php',
        '2026_10_09_081138_create_personal_access_tokens_table.php',
    ];
}

it('never stores a UUID reference in a string column', function () {
    $allowlist = externalStringIdAllowlist();
    $violations = [];

    foreach (File::glob(database_path('migrations/*.php')) as $file) {
        preg_match_all("/->string\(\s*['\"]([a-zA-Z0-9_]+)['\"]/", File::get($file), $matches);

        foreach ($matches[1] as $column) {
            if (! str_ends_with($column, '_id') || in_array($column, $allowlist, true)) {
                continue;
            }

            $violations[] = basename($file).': '.$column;
        }
    }

    expect($violations)->toBe([]);
});

it('uses UUID primary keys for every domain table', function () {
    $framework = frameworkKeyMigrations();
    $violations = [];

    foreach (File::glob(database_path('migrations/*.php')) as $file) {
        if (in_array(basename($file), $framework, true)) {
            continue;
        }

        if (preg_match('/->id\(\)/', File::get($file)) === 1) {
            $violations[] = basename($file);
        }
    }

    expect($violations)->toBe([]);
});
