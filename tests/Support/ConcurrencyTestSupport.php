<?php

namespace Tests\Support;

use App\Modules\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Runs a closure in several forked child processes so financial writes truly
 * race against the same rows. This is the evidence net for ADR-0003 (deterministic
 * row locking): the assertion is the invariant (no overspend, no lost funds), not
 * an HTTP status.
 *
 * Requirements:
 * - the `pcntl` extension (guarded by the calling test with `skipped` otherwise);
 * - the test must NOT wrap the database in a transaction, so children see committed
 *   state — use `DatabaseMigrations`, not `RefreshDatabase`.
 */
trait ConcurrencyTestSupport
{
    /**
     * @param  callable(int): array<string, mixed>  $job
     * @return array<int, array<string, mixed>>
     */
    protected function runConcurrently(int $workers, callable $job): array
    {
        $dir = sys_get_temp_dir().'/lumina-concurrency-'.Str::uuid();
        mkdir($dir, 0700, true);

        $pids = [];

        for ($index = 0; $index < $workers; $index++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('Unable to fork a worker process.');
            }

            if ($pid === 0) {
                $this->runWorker($dir, $index, $job);
            }

            $pids[$pid] = $index;
        }

        foreach (array_keys($pids) as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $results = [];

        foreach ($pids as $pid => $index) {
            $results[$index] = $this->readWorkerResult($dir, $index);
        }

        foreach (glob($dir.'/*.json') ?: [] as $file) {
            unlink($file);
        }

        rmdir($dir);
        ksort($results);

        return $results;
    }

    /**
     * Child-process body. Disconnects the inherited PDO handle so the worker owns
     * its own connection (otherwise both processes would share one socket and the
     * row locks would not be exercised).
     */
    private function runWorker(string $dir, int $index, callable $job): never
    {
        DB::disconnect();

        try {
            $result = $job($index);
            file_put_contents($this->workerFile($dir, $index), json_encode([
                'ok' => true,
                'result' => $result,
            ]) ?: '{}');
        } catch (Throwable $exception) {
            file_put_contents($this->workerFile($dir, $index), json_encode([
                'ok' => false,
                'error' => $exception::class,
                'code' => $exception instanceof DomainException ? $exception->errorCode->value : null,
                'message' => $exception->getMessage(),
            ]) ?: '{}');
        }

        exit(0);
    }

    /**
     * @return array<string, mixed>
     */
    private function readWorkerResult(string $dir, int $index): array
    {
        $file = $this->workerFile($dir, $index);

        if (! is_file($file)) {
            return ['ok' => false, 'error' => 'no_output'];
        }

        return json_decode((string) file_get_contents($file), true) ?? ['ok' => false, 'error' => 'invalid_output'];
    }

    private function workerFile(string $dir, int $index): string
    {
        return $dir.'/'.$index.'.json';
    }
}
