<?php

use App\Modules\Ledger\Enums\AccountType;
use App\Modules\Ledger\Enums\PostingDirection;

it('treats debits as positive for debit-normal accounts', function () {
    expect(PostingDirection::Debit->signedAmountFor(AccountType::Asset, 500))->toBe(500)
        ->and(PostingDirection::Credit->signedAmountFor(AccountType::Asset, 500))->toBe(-500);
});

it('treats credits as positive for credit-normal accounts', function () {
    expect(PostingDirection::Credit->signedAmountFor(AccountType::Liability, 500))->toBe(500)
        ->and(PostingDirection::Debit->signedAmountFor(AccountType::Liability, 500))->toBe(-500);
});

it('knows the opposite direction', function () {
    expect(PostingDirection::Debit->opposite())->toBe(PostingDirection::Credit)
        ->and(PostingDirection::Credit->opposite())->toBe(PostingDirection::Debit);
});
