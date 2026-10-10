<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deposits\Enums\DepositStatus;
use App\Modules\Deposits\Http\Resources\DepositResource;
use App\Modules\Deposits\Models\Deposit;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Transfers\Http\Resources\TransferResource;
use App\Modules\Transfers\Models\Transfer;
use App\Modules\Withdrawals\Enums\WithdrawalStatus;
use App\Modules\Withdrawals\Http\Resources\WithdrawalResource;
use App\Modules\Withdrawals\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function transfers(Request $request): JsonResponse
    {
        $this->authorize('ledger.view');

        $transfers = Transfer::query()
            ->when($request->query('wallet_id'), fn ($q, $walletId) => $q->where(
                fn ($w) => $w->where('sender_wallet_id', $walletId)->orWhere('recipient_wallet_id', $walletId),
            ))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('reference'), fn ($q, $reference) => $q->where('reference', 'like', "%{$reference}%"))
            ->when($request->query('from'), fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(25);

        return ApiResponse::success(
            TransferResource::collection($transfers),
            meta: ['pagination' => [
                'next_cursor' => $transfers->nextCursor()?->encode(),
                'prev_cursor' => $transfers->previousCursor()?->encode(),
            ]],
        );
    }

    public function journalEntries(Request $request): JsonResponse
    {
        $this->authorize('ledger.view');

        $entries = JournalEntry::query()
            ->with('postings')
            ->when($request->query('entry_type'), fn ($q, $type) => $q->where('entry_type', $type))
            ->when($request->query('source_type'), fn ($q, $type) => $q->where('source_type', $type))
            ->when($request->query('source_id'), fn ($q, $id) => $q->where('source_id', $id))
            ->when($request->query('currency'), fn ($q, $currency) => $q->where('currency_code', $currency))
            ->when($request->query('from'), fn ($q, $from) => $q->where('occurred_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('occurred_at', '<=', $to))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(25);

        return ApiResponse::success(
            $entries->getCollection()->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type->value,
                'status' => $entry->status->value,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'description' => $entry->description,
                'currency' => $entry->currency_code,
                'occurred_at' => $entry->occurred_at?->toIso8601String(),
                'postings' => $entry->postings->map(fn ($posting) => [
                    'id' => $posting->id,
                    'ledger_account_id' => $posting->ledger_account_id,
                    'wallet_id' => $posting->wallet_id,
                    'direction' => $posting->direction->value,
                    'amount' => $posting->amount,
                ])->values(),
            ])->values(),
            meta: ['pagination' => [
                'next_cursor' => $entries->nextCursor()?->encode(),
                'prev_cursor' => $entries->previousCursor()?->encode(),
            ]],
        );
    }

    public function deposits(Request $request): JsonResponse
    {
        $this->authorize('deposits.approve');

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(DepositStatus::class)],
            'provider' => ['nullable', 'string'],
            'reference' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $deposits = Deposit::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['provider'] ?? null, fn ($q, $provider) => $q->where('provider', $provider))
            ->when($filters['reference'] ?? null, fn ($q, $reference) => $q->where('reference', 'like', "%{$reference}%"))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($filters['per_page'] ?? 25));

        return ApiResponse::success(
            DepositResource::collection($deposits),
            meta: ['pagination' => [
                'per_page' => $deposits->perPage(),
                'next_cursor' => $deposits->nextCursor()?->encode(),
                'prev_cursor' => $deposits->previousCursor()?->encode(),
            ]],
        );
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $this->authorize('withdrawals.approve');

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(WithdrawalStatus::class)],
            'provider' => ['nullable', 'string'],
            'reference' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $withdrawals = Withdrawal::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['provider'] ?? null, fn ($q, $provider) => $q->where('provider', $provider))
            ->when($filters['reference'] ?? null, fn ($q, $reference) => $q->where('reference', 'like', "%{$reference}%"))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($filters['per_page'] ?? 25));

        return ApiResponse::success(
            WithdrawalResource::collection($withdrawals),
            meta: ['pagination' => [
                'per_page' => $withdrawals->perPage(),
                'next_cursor' => $withdrawals->nextCursor()?->encode(),
                'prev_cursor' => $withdrawals->previousCursor()?->encode(),
            ]],
        );
    }

    public function users(Request $request): JsonResponse
    {
        $this->authorize('users.manage');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->with('roles')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"),
            ))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate((int) ($filters['per_page'] ?? 25));

        return ApiResponse::success(
            $users->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'roles' => $user->roles->pluck('name')->values(),
                'created_at' => $user->created_at?->toIso8601String(),
            ])->values(),
            meta: ['pagination' => [
                'per_page' => $users->perPage(),
                'next_cursor' => $users->nextCursor()?->encode(),
                'prev_cursor' => $users->previousCursor()?->encode(),
            ]],
        );
    }

    public function providerTransactions(Request $request): JsonResponse
    {
        $this->authorize('reconciliation.view');

        $provider = $request->query('provider');
        $reference = $request->query('reference');
        $transactionId = $request->query('transaction_id');

        $deposits = Deposit::query()
            ->when($provider, fn ($q, $p) => $q->where('provider', $p))
            ->when($transactionId, fn ($q, $id) => $q->where('provider_transaction_id', $id))
            ->when($reference, fn ($q, $ref) => $q->where(
                fn ($w) => $w->where('provider_reference', $ref)->orWhere('reference', $ref),
            ))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Deposit $deposit) => [
                'type' => 'deposit',
                'id' => $deposit->id,
                'reference' => $deposit->reference,
                'provider' => $deposit->provider,
                'provider_reference' => $deposit->provider_reference,
                'provider_transaction_id' => $deposit->provider_transaction_id,
                'status' => $deposit->status->value,
                'amount' => $deposit->amount,
                'currency' => $deposit->currency_code,
                'created_at' => $deposit->created_at?->toIso8601String(),
            ]);

        $withdrawals = Withdrawal::query()
            ->when($provider, fn ($q, $p) => $q->where('provider', $p))
            ->when($transactionId, fn ($q, $id) => $q->where('provider_transaction_id', $id))
            ->when($reference, fn ($q, $ref) => $q->where(
                fn ($w) => $w->where('provider_reference', $ref)->orWhere('reference', $ref),
            ))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Withdrawal $withdrawal) => [
                'type' => 'withdrawal',
                'id' => $withdrawal->id,
                'reference' => $withdrawal->reference,
                'provider' => $withdrawal->provider,
                'provider_reference' => $withdrawal->provider_reference,
                'provider_transaction_id' => $withdrawal->provider_transaction_id,
                'status' => $withdrawal->status->value,
                'amount' => $withdrawal->amount,
                'currency' => $withdrawal->currency_code,
                'created_at' => $withdrawal->created_at?->toIso8601String(),
            ]);

        return ApiResponse::success($deposits->concat($withdrawals)->sortByDesc('created_at')->values());
    }
}
