<?php

namespace App\Modules\Ledger\DTOs;

use App\Modules\Ledger\Enums\JournalEntryType;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class JournalEntryData
{
    /**
     * @param  array<int, JournalPostingData>  $postings
     */
    public function __construct(
        public string $currencyCode,
        public JournalEntryType $entryType,
        public array $postings,
        public ?string $sourceType = null,
        public ?string $sourceId = null,
        public ?string $description = null,
        public ?string $reversesJournalEntryId = null,
        public ?CarbonImmutable $occurredAt = null,
        public ?string $createdBy = null,
    ) {}

    public function occurredAtOrNow(): CarbonImmutable
    {
        return $this->occurredAt ?? CarbonImmutable::now();
    }

    /**
     * Build a reversal of the given postings. Caller links the source journal.
     *
     * @param  array<int, JournalPostingData>  $postings
     */
    public static function reversal(
        string $currencyCode,
        array $postings,
        string $reversesJournalEntryId,
        ?string $description = null,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        return new self(
            currencyCode: $currencyCode,
            entryType: JournalEntryType::Reversal,
            postings: $postings,
            description: $description ?? 'Reversal',
            reversesJournalEntryId: $reversesJournalEntryId,
            occurredAt: $occurredAt ? CarbonImmutable::instance($occurredAt) : null,
        );
    }
}
