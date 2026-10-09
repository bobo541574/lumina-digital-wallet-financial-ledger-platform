<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Http\Resources\AuditEventResource;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('audit.view');

        $events = AuditEvent::query()
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->query('actor_id'), fn ($q, $actor) => $q->where('actor_id', $actor))
            ->when($request->query('subject_type'), fn ($q, $type) => $q->where('subject_type', $type))
            ->when($request->query('subject_id'), fn ($q, $id) => $q->where('subject_id', $id))
            ->when($request->query('from'), fn ($q, $from) => $q->where('occurred_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('occurred_at', '<=', $to))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return ApiResponse::success(
            AuditEventResource::collection($events),
            meta: ['pagination' => [
                'per_page' => $events->perPage(),
                'next_cursor' => $events->nextCursor()?->encode(),
                'prev_cursor' => $events->previousCursor()?->encode(),
            ]],
        );
    }
}
