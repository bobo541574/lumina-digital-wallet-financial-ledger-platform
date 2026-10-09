<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deposits\Models\Deposit;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Transfers\Http\Resources\TransferResource;
use App\Modules\Transfers\Models\Transfer;
use App\Modules\Withdrawals\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
