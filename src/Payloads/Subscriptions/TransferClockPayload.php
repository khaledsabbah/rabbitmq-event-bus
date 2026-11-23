<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Payloads\Subscriptions;

/**
 * W0.6 — `transfer.pending` / `transfer.resolved`
 * (exchange SubscriptionEvents::EXCHANGE_SUBSCRIPTION_EVENTS).
 *
 * Publisher: ADM, on the bank-transfer-proof approval path, emitted AFTER the
 * payment state transition commits. Consumers: BILL crons (S9-bill / S12) that
 * pause/resume the subscription clock while a transfer is pending (D-76/D-91).
 *
 * remaining_days carries the frozen clock position so BILL resumes from the
 * exact day count ADM paused at — counted from the request (D-66), divisor 365,
 * truncated (D-96 convention), not recomputed by the consumer.
 *
 * IDEMPOTENCY (the sharp bit — CD-18.6 / D-91): the pause/resume must be
 * idempotent WITH the payment transition so a replayed provider webhook cannot
 * double-pause. The key is `idempotency_key` = the W0.5 payment idempotency key
 * (provider txn id ∪ payment-request id — the SAME key that gates the charge
 * exactly once). A consumer that already applied this key is a no-op. Envelope
 * event_id is the transport-level dedupe; idempotency_key is the business dedupe
 * that unifies webhook replays and the approval event onto one charge.
 */
final class TransferClockPayload
{
    public const STATE_PENDING = 'pending';
    public const STATE_RESOLVED = 'resolved';

    /** How a pending transfer resolved (only set on transfer.resolved). */
    public const RESOLUTION_APPROVED = 'approved';
    public const RESOLUTION_REJECTED = 'rejected';
    public const RESOLUTION_EXPIRED = 'expired';

    /**
     * @param  int  $companyId  central company id (also envelope tenant_id)
     * @param  string  $state  self::STATE_* — matches the routing key
     * @param  int  $remainingDays  frozen clock position to pause at / resume with
     * @param  string  $idempotencyKey  W0.5 payment key (provider txn id ∪ payment-request id);
     *                                   unifies webhook replays + approval onto one charge
     * @param  string|null  $paymentRequestId  originating payment-request id, for correlation
     * @param  string|null  $resolution  self::RESOLUTION_* — only on transfer.resolved
     */
    public function __construct(
        private readonly int $companyId,
        private readonly string $state,
        private readonly int $remainingDays,
        private readonly string $idempotencyKey,
        private readonly ?string $paymentRequestId = null,
        private readonly ?string $resolution = null,
    ) {
    }

    public static function pending(
        int $companyId,
        int $remainingDays,
        string $idempotencyKey,
        ?string $paymentRequestId = null,
    ): self {
        return new self(
            companyId: $companyId,
            state: self::STATE_PENDING,
            remainingDays: $remainingDays,
            idempotencyKey: $idempotencyKey,
            paymentRequestId: $paymentRequestId,
        );
    }

    public static function resolved(
        int $companyId,
        int $remainingDays,
        string $idempotencyKey,
        string $resolution,
        ?string $paymentRequestId = null,
    ): self {
        return new self(
            companyId: $companyId,
            state: self::STATE_RESOLVED,
            remainingDays: $remainingDays,
            idempotencyKey: $idempotencyKey,
            paymentRequestId: $paymentRequestId,
            resolution: $resolution,
        );
    }

    /** The routing key / event type this payload belongs to. */
    public function eventType(): string
    {
        return $this->state === self::STATE_PENDING
            ? SubscriptionEvents::TRANSFER_PENDING
            : SubscriptionEvents::TRANSFER_RESOLVED;
    }

    /**
     * @return array{
     *     company_id: int,
     *     state: string,
     *     remaining_days: int,
     *     idempotency_key: string,
     *     payment_request_id: string|null,
     *     resolution: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'state' => $this->state,
            'remaining_days' => $this->remainingDays,
            'idempotency_key' => $this->idempotencyKey,
            'payment_request_id' => $this->paymentRequestId,
            'resolution' => $this->resolution,
        ];
    }
}
