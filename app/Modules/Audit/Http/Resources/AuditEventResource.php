<?php

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditEvent
 */
class AuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'action' => $this->action,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'before' => $this->before,
            'after' => $this->after,
            'correlation_id' => $this->correlation_id,
            'ip_address' => $this->ip_address,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
