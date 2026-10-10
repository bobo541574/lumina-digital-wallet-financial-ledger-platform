<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Models\User;
use App\Modules\Shared\Support\Redactor;

class AuditLogger
{
    public function __construct(private readonly Redactor $redactor) {}

    /**
     * Append an immutable audit record. The current authenticated user (if any)
     * is used as the actor; the request id becomes the correlation id so an
     * action can be traced across the API, outbox and ledger.
     *
     * Sensitive values are stripped from the before/after payloads before they
     * are persisted.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $before = [],
        array $after = [],
        ?User $actor = null,
    ): AuditEvent {
        $actor ??= auth()->user();
        $request = request();

        return AuditEvent::create([
            'actor_type' => $actor ? 'user' : 'system',
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before ? $this->redactor->redact($before) : null,
            'after' => $after ? $this->redactor->redact($after) : null,
            'correlation_id' => $request?->header('X-Request-Id'),
            'ip_address' => $request?->ip(),
            'occurred_at' => now(),
        ]);
    }
}
