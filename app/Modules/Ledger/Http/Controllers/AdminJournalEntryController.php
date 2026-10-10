<?php

namespace App\Modules\Ledger\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ledger\Http\Requests\ReverseJournalEntryRequest;
use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Services\LedgerReversalService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminJournalEntryController extends Controller
{
    public function __construct(private readonly LedgerReversalService $reversals) {}

    public function reverse(ReverseJournalEntryRequest $request, JournalEntry $journalEntry): JsonResponse
    {
        $result = $this->reversals->reverse(
            entry: $journalEntry,
            actor: $request->user(),
            reason: $request->validated('reason'),
        );

        return ApiResponse::success([
            'id' => $result->journal->id,
            'entry_type' => $result->journal->entry_type->value,
            'status' => $result->journal->status->value,
            'reverses_journal_entry_id' => $result->journal->reverses_journal_entry_id,
            'currency' => $result->journal->currency_code,
            'description' => $result->journal->description,
            'occurred_at' => $result->journal->occurred_at?->toIso8601String(),
            'postings' => collect($result->postings)->map(fn ($posting) => [
                'id' => $posting->id,
                'ledger_account_id' => $posting->ledger_account_id,
                'wallet_id' => $posting->wallet_id,
                'direction' => $posting->direction->value,
                'amount' => $posting->amount,
            ])->values(),
        ], status: 201);
    }
}
