<?php

namespace App\Modules\Shared\Idempotency;

use App\Modules\Shared\Idempotency\Exceptions\IdempotencyException;
use App\Modules\Shared\Idempotency\Models\IdempotencyKey;
use App\Modules\Shared\Support\ResilientTransaction;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Actor + operation scoped idempotency.
 *
 * The record and the financial side effects run in one database transaction, so
 * a failed attempt leaves no record and may be safely retried with the same key.
 */
class IdempotencyService
{
    public const TTL_HOURS = 24;

    /**
     * @param  Closure(): IdempotencyOutcome  $callback
     */
    public function run(
        string $scope,
        ?string $actorId,
        ?string $key,
        string $requestHash,
        Closure $callback,
        int $attempts = ResilientTransaction::DEFAULT_ATTEMPTS,
    ): IdempotencyResult {
        $this->assertKey($key);

        return ResilientTransaction::run(function () use ($scope, $actorId, $key, $requestHash, $callback): IdempotencyResult {
            $now = Date::now();

            $inserted = IdempotencyKey::insertOrIgnore([
                'id' => (string) Str::uuid(),
                'scope' => $scope,
                'actor_id' => $actorId,
                'key' => $key,
                'request_hash' => $requestHash,
                'status' => IdempotencyStatus::InProgress->value,
                'locked_at' => $now,
                'expires_at' => $now->addHours(self::TTL_HOURS),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $record = IdempotencyKey::query()
                ->where('scope', $scope)
                ->where('actor_id', $actorId)
                ->where('key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inserted === 0) {
                return $this->replay($record, $requestHash);
            }

            $outcome = $callback();

            $record->forceFill([
                'status' => IdempotencyStatus::Completed,
                'request_hash' => $requestHash,
                'response_status' => $outcome->status,
                'response_body' => $outcome->body,
                'resource_type' => $outcome->resourceType,
                'resource_id' => $outcome->resourceId,
                'locked_at' => null,
            ])->save();

            return new IdempotencyResult($outcome->body, $outcome->status, false);
        }, $attempts);
    }

    private function replay(IdempotencyKey $record, string $requestHash): IdempotencyResult
    {
        if ($record->request_hash !== $requestHash) {
            throw IdempotencyException::reused();
        }

        if ($record->status === IdempotencyStatus::InProgress) {
            throw IdempotencyException::inProgress();
        }

        if ($record->status === IdempotencyStatus::Failed || $record->response_status === null) {
            throw IdempotencyException::reused();
        }

        return new IdempotencyResult($record->response_body ?? [], $record->response_status, true);
    }

    private function assertKey(?string $key): void
    {
        if ($key === null || trim($key) === '') {
            throw IdempotencyException::keyRequired();
        }

        if (strlen($key) < 8 || strlen($key) > 255) {
            throw IdempotencyException::keyInvalid();
        }
    }
}
