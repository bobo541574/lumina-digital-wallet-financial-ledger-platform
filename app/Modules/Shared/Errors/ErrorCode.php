<?php

namespace App\Modules\Shared\Errors;

/**
 * Stable, machine-readable API error codes.
 *
 * @see docs/architecture/api/error-codes.md
 */
enum ErrorCode: string
{
    // Authentication & authorization
    case Unauthenticated = 'UNAUTHENTICATED';
    case TokenExpired = 'TOKEN_EXPIRED';
    case Forbidden = 'FORBIDDEN';
    case NotOwner = 'NOT_OWNER';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
    case MfaRequired = 'MFA_REQUIRED';
    case TwoFactorNotEnabled = 'TWO_FACTOR_NOT_ENABLED';
    case TwoFactorAlreadyEnabled = 'TWO_FACTOR_ALREADY_ENABLED';
    case TwoFactorInvalidCode = 'TWO_FACTOR_INVALID_CODE';

    // Validation
    case ValidationFailed = 'VALIDATION_FAILED';
    case UnsupportedCurrency = 'UNSUPPORTED_CURRENCY';
    case CurrencyMismatch = 'CURRENCY_MISMATCH';
    case InvalidAmount = 'INVALID_AMOUNT';
    case AmountTooLarge = 'AMOUNT_TOO_LARGE';

    // Wallet & balance
    case WalletNotFound = 'WALLET_NOT_FOUND';
    case WalletFrozen = 'WALLET_FROZEN';
    case WalletClosed = 'WALLET_CLOSED';
    case InsufficientFunds = 'INSUFFICIENT_FUNDS';
    case SelfTransfer = 'SELF_TRANSFER';

    // Idempotency
    case IdempotencyKeyRequired = 'IDEMPOTENCY_KEY_REQUIRED';
    case IdempotencyKeyReused = 'IDEMPOTENCY_KEY_REUSED';
    case IdempotencyInProgress = 'IDEMPOTENCY_IN_PROGRESS';
    case IdempotencyKeyInvalid = 'IDEMPOTENCY_KEY_INVALID';

    // Ledger
    case LedgerUnbalanced = 'LEDGER_UNBALANCED';
    case LedgerAccountInactive = 'LEDGER_ACCOUNT_INACTIVE';
    case LedgerAccountInvalid = 'LEDGER_ACCOUNT_INVALID';
    case LedgerDuplicateSource = 'LEDGER_DUPLICATE_SOURCE';
    case LedgerImmutable = 'LEDGER_IMMUTABLE';
    case ReversalAlreadyExists = 'REVERSAL_ALREADY_EXISTS';
    case JournalNotReversible = 'JOURNAL_NOT_REVERSIBLE';

    // Deposits
    case DepositNotFound = 'DEPOSIT_NOT_FOUND';
    case DepositInvalidState = 'DEPOSIT_INVALID_STATE';
    case DepositSelfApproval = 'DEPOSIT_SELF_APPROVAL';
    case DepositAlreadySettled = 'DEPOSIT_ALREADY_SETTLED';

    // Withdrawals
    case WithdrawalNotFound = 'WITHDRAWAL_NOT_FOUND';
    case WithdrawalInvalidState = 'WITHDRAWAL_INVALID_STATE';
    case HoldReleaseUnsafe = 'HOLD_RELEASE_UNSAFE';
    case PayoutAlreadySettled = 'PAYOUT_ALREADY_SETTLED';
    case HoldExpired = 'HOLD_EXPIRED';

    // Providers & webhooks
    case WebhookSignatureInvalid = 'WEBHOOK_SIGNATURE_INVALID';
    case WebhookDuplicate = 'WEBHOOK_DUPLICATE';
    case WebhookQuarantined = 'WEBHOOK_QUARANTINED';
    case ProviderUnavailable = 'PROVIDER_UNAVAILABLE';
    case ProviderTimeout = 'PROVIDER_TIMEOUT';
    case ProviderRejected = 'PROVIDER_REJECTED';

    // Reconciliation
    case ReconciliationItemNotFound = 'RECONCILIATION_ITEM_NOT_FOUND';
    case ReconciliationItemAlreadyResolved = 'RECONCILIATION_ITEM_ALREADY_RESOLVED';

    // Concurrency & infrastructure
    case ConcurrentModification = 'CONCURRENT_MODIFICATION';
    case TemporaryFailure = 'TEMPORARY_FAILURE';
    case RateLimited = 'RATE_LIMITED';
    case InternalError = 'INTERNAL_ERROR';

    /**
     * Default HTTP status for this error code.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::Unauthenticated, self::TokenExpired, self::MfaRequired,
            self::TwoFactorInvalidCode => 401,
            self::Forbidden, self::NotOwner,
            self::DepositSelfApproval => 403,
            self::ResourceNotFound, self::WalletNotFound,
            self::DepositNotFound, self::WithdrawalNotFound,
            self::ReconciliationItemNotFound => 404,
            self::IdempotencyKeyReused, self::IdempotencyInProgress,
            self::WalletClosed, self::LedgerDuplicateSource,
            self::LedgerImmutable, self::ReversalAlreadyExists,
            self::JournalNotReversible, self::ReconciliationItemAlreadyResolved,
            self::DepositInvalidState, self::DepositAlreadySettled,
            self::WithdrawalInvalidState, self::HoldReleaseUnsafe,
            self::PayoutAlreadySettled, self::HoldExpired,
            self::ConcurrentModification, self::TwoFactorNotEnabled,
            self::TwoFactorAlreadyEnabled,
            self::WebhookDuplicate => 409,
            self::WalletFrozen => 423,
            self::ProviderUnavailable, self::TemporaryFailure => 503,
            self::ProviderTimeout, self::WebhookQuarantined => 202,
            self::IdempotencyKeyRequired, self::IdempotencyKeyInvalid,
            self::WebhookSignatureInvalid => 400,
            self::RateLimited => 429,
            self::InternalError => 500,
            default => 422,
        };
    }
}
