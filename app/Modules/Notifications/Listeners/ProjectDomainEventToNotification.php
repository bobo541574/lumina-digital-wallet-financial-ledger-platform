<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Outbox\Events\DomainEvent;
use App\Modules\Shared\Support\Money;
use App\Modules\Wallets\Models\Wallet;

/**
 * Projects committed domain events into customer notifications. Registered on
 * the outbox `DomainEvent`, so notifications are only ever created after the
 * financial transaction that produced the event has committed.
 */
class ProjectDomainEventToNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(DomainEvent $event): void
    {
        match ($event->eventType) {
            'deposit.succeeded' => $this->onDepositSucceeded($event),
            'transfer.completed' => $this->onTransferCompleted($event),
            'withdrawal.succeeded' => $this->onWithdrawalSucceeded($event),
            'withdrawal.failed' => $this->onWithdrawalFailed($event),
            default => null,
        };
    }

    private function onDepositSucceeded(DomainEvent $event): void
    {
        $payload = $event->payload;

        $this->notifyWalletOwner(
            event: $event,
            walletId: (string) ($payload['wallet_id'] ?? ''),
            type: 'deposit.succeeded',
            title: 'Deposit received',
            body: sprintf(
                '%s was credited to your wallet (%s).',
                Money::format((int) ($payload['amount'] ?? 0), (string) ($payload['currency'] ?? '')),
                (string) ($payload['reference'] ?? ''),
            ),
        );
    }

    private function onTransferCompleted(DomainEvent $event): void
    {
        $payload = $event->payload;
        $amount = Money::format((int) ($payload['amount'] ?? 0), (string) ($payload['currency'] ?? ''));
        $reference = (string) ($payload['reference'] ?? '');

        $this->notifyWalletOwner(
            event: $event,
            walletId: (string) ($payload['sender_wallet_id'] ?? ''),
            type: 'transfer.sent',
            title: 'Transfer sent',
            body: sprintf('%s was sent to another wallet (%s).', $amount, $reference),
        );

        $this->notifyWalletOwner(
            event: $event,
            walletId: (string) ($payload['recipient_wallet_id'] ?? ''),
            type: 'transfer.received',
            title: 'Transfer received',
            body: sprintf('%s was received in your wallet (%s).', $amount, $reference),
        );
    }

    private function onWithdrawalSucceeded(DomainEvent $event): void
    {
        $payload = $event->payload;

        $this->notifyWalletOwner(
            event: $event,
            walletId: (string) ($payload['wallet_id'] ?? ''),
            type: 'withdrawal.succeeded',
            title: 'Withdrawal completed',
            body: sprintf(
                '%s was paid out (%s).',
                Money::format((int) ($payload['amount'] ?? 0), (string) ($payload['currency'] ?? '')),
                (string) ($payload['reference'] ?? ''),
            ),
        );
    }

    private function onWithdrawalFailed(DomainEvent $event): void
    {
        $payload = $event->payload;

        $this->notifyWalletOwner(
            event: $event,
            walletId: (string) ($payload['wallet_id'] ?? ''),
            type: 'withdrawal.failed',
            title: 'Withdrawal failed',
            body: sprintf(
                '%s could not be paid out (%s). The hold was released.',
                Money::format((int) ($payload['amount'] ?? 0), (string) ($payload['currency'] ?? '')),
                (string) ($payload['reference'] ?? ''),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notifyWalletOwner(
        DomainEvent $event,
        string $walletId,
        string $type,
        string $title,
        string $body,
    ): void {
        if ($walletId === '' || $event->id === null) {
            return;
        }

        $owner = Wallet::query()
            ->whereKey($walletId)
            ->with('user')
            ->first()
            ?->user;

        if (! $owner instanceof User) {
            return;
        }

        $this->notifications->record(
            user: $owner,
            type: $type,
            title: $title,
            body: $body,
            eventId: $event->id,
            data: $event->payload,
        );
    }
}
