<?php

namespace App\Modules\Ledger\Results;

use App\Modules\Ledger\Models\JournalEntry;
use App\Modules\Ledger\Models\LedgerPosting;

final readonly class PostedJournalResult
{
    /**
     * @param  array<int, LedgerPosting>  $postings
     */
    public function __construct(
        public JournalEntry $journal,
        public array $postings,
    ) {}
}
