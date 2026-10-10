<?php

namespace App\Modules\Reconciliation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reconciliation\Enums\MismatchClassification;
use App\Modules\Reconciliation\Enums\ReconciliationItemStatus;
use App\Modules\Reconciliation\Enums\ReconciliationLayer;
use App\Modules\Reconciliation\Enums\ReconciliationRunStatus;
use App\Modules\Reconciliation\Http\Requests\ResolveReconciliationItemRequest;
use App\Modules\Reconciliation\Http\Requests\RunReconciliationRequest;
use App\Modules\Reconciliation\Http\Resources\ReconciliationItemResource;
use App\Modules\Reconciliation\Http\Resources\ReconciliationRunResource;
use App\Modules\Reconciliation\Models\ReconciliationItem;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use App\Modules\Reconciliation\Services\ReconciliationResolutionService;
use App\Modules\Reconciliation\Services\ReconciliationRunner;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminReconciliationController extends Controller
{
    public function __construct(
        private readonly ReconciliationRunner $runner,
        private readonly ReconciliationResolutionService $resolution,
    ) {}

    public function runs(Request $request): JsonResponse
    {
        $this->authorize('reconciliation.view');

        $filters = $request->validate([
            'layer' => ['nullable', Rule::enum(ReconciliationLayer::class)],
            'status' => ['nullable', Rule::enum(ReconciliationRunStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $runs = ReconciliationRun::query()
            ->when($filters['layer'] ?? null, fn ($q, $layer) => $q->where('layer', $layer))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($filters['per_page'] ?? 25));

        return ApiResponse::success(
            ReconciliationRunResource::collection($runs),
            meta: ['pagination' => [
                'per_page' => $runs->perPage(),
                'next_cursor' => $runs->nextCursor()?->encode(),
                'prev_cursor' => $runs->previousCursor()?->encode(),
            ]],
        );
    }

    public function show(ReconciliationRun $run): JsonResponse
    {
        $this->authorize('reconciliation.view');

        return ApiResponse::success(new ReconciliationRunResource($run->load('items')));
    }

    public function items(Request $request): JsonResponse
    {
        $this->authorize('reconciliation.view');

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReconciliationItemStatus::class)],
            'layer' => ['nullable', Rule::enum(ReconciliationLayer::class)],
            'classification' => ['nullable', Rule::enum(MismatchClassification::class)],
            'provider' => ['nullable', 'string', 'max:32'],
            'run_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $items = ReconciliationItem::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['layer'] ?? null, fn ($q, $layer) => $q->where('layer', $layer))
            ->when($filters['classification'] ?? null, fn ($q, $c) => $q->where('classification', $c))
            ->when($filters['provider'] ?? null, fn ($q, $provider) => $q->where('provider', $provider))
            ->when($filters['run_id'] ?? null, fn ($q, $runId) => $q->where('run_id', $runId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($filters['per_page'] ?? 25));

        return ApiResponse::success(
            ReconciliationItemResource::collection($items),
            meta: ['pagination' => [
                'per_page' => $items->perPage(),
                'next_cursor' => $items->nextCursor()?->encode(),
                'prev_cursor' => $items->previousCursor()?->encode(),
            ]],
        );
    }

    public function run(RunReconciliationRequest $request): JsonResponse
    {
        $layer = ReconciliationLayer::from($request->validated('layer'));

        $run = $this->runner->run($layer, $request->scope(), $request->user());

        return ApiResponse::success(new ReconciliationRunResource($run), status: 201);
    }

    public function resolve(ResolveReconciliationItemRequest $request, ReconciliationItem $item): JsonResponse
    {
        $resolved = $this->resolution->resolve(
            item: $item,
            actor: $request->user(),
            status: ReconciliationItemStatus::from($request->validated('status')),
            note: $request->validated('note'),
            resolution: $request->validated('resolution') ?? [],
        );

        return ApiResponse::success(new ReconciliationItemResource($resolved));
    }
}
