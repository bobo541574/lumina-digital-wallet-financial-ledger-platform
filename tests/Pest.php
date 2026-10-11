<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Concurrency tests fork worker processes, so the seeded data must be committed
// and visible to every process. They opt into DatabaseTruncation inside the test
// file instead of the transactional RefreshDatabase used elsewhere.
pest()->extend(TestCase::class)
    ->in('Concurrency');

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Performance');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
