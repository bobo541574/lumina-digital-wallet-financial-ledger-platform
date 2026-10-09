<?php

namespace App\Modules\Wallets\Services;

use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Models\LedgerPosting;
use App\Modules\Wallets\Models\Wallet;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class WalletHistoryService
{
    /**
     * Unified history: every movement on the wallet, regardless of whether it
     * originated as a transfer, deposit, withdrawal, fee or reversal. Derived
     * from the ledger so nothing can be missing.
     *
     * Ordered by a stable key (created_at, id) so cursor pagination never skips
     * or duplicates rows when timestamps collide.
     *
     * @param  array{from?: string|null, to?: string|null, type?: string|null}  $filters
     */
    public function history(Wallet $wallet, array $filters = [], int $perPage = 25): CursorPaginator
    {
        return LedgerPosting::query()
            ->with('journalEntry')
            ->where('wallet_id', $wallet->id)
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->whereHas(
                'journalEntry',
                fn ($j) => $j->where('entry_type', $type),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /**
     * Statement over [from, to]: opening balance, the movements in range and the
     * closing balance. All figures are in the wallet's currency, minor units.
     *
     * @return array{opening_balance: int, closing_balance: int, movements: Collection<int, LedgerPosting>, currency: string, from: string, to: string}
     */
    public function statement(Wallet $wallet, CarbonInterface $from, CarbonInterface $to): array
    {
        $opening = $this->signedSum($wallet, null, $from);

        $movements = LedgerPosting::query()
            ->with('journalEntry')
            ->where('wallet_id', $wallet->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $net = $movements->sum(fn (LedgerPosting $posting) => $this->signedAmount($posting));

        return [
            'currency' => $wallet->currency_code,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'opening_balance' => $opening,
            'closing_balance' => $opening + $net,
            'movements' => $movements,
        ];
    }

    /**
     * Signed effect of a posting on the wallet's liability account: credits
     * increase the customer balance, debits reduce it.
     */
    public function signedAmount(LedgerPosting $posting): int
    {
        return $posting->direction->signedAmountFor(AccountType::Liability, $posting->amount);
    }

    private function signedSum(Wallet $wallet, ?CarbonInterface $from, ?CarbonInterface $to): int
    {
        $query = LedgerPosting::query()->where('wallet_id', $wallet->id);

        if ($from !== null) {
            $query->where('created_at', '<', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get()->sum(fn (LedgerPosting $posting) => $this->signedAmount($posting));
    }
}
