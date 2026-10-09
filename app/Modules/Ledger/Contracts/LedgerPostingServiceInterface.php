<?php

namespace App\Modules\Ledger\Contracts;

use App\Modules\Ledger\DTOs\JournalEntryData;
use App\Modules\Ledger\Results\PostedJournalResult;

/**
 * Central boundary for every financial posting. No module may write
 * journal_entries or ledger_postings directly; they must go through post().
 */
interface LedgerPostingServiceInterface
{
    public function post(JournalEntryData $entry): PostedJournalResult;
}
