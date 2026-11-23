<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Payloads\Subscriptions;

/**
 * W0.7 / CD-25 — cross-service erase-saga contract, SHAPE ONLY.
 *
 * Implementation is DEFERRED to Wave 5 (S16); it is gated on Q-44 (the per-service
 * erase list) and on cloning CRM/PM/SCM/accounting/rental/waqi3 (CD-34). This class
 * freezes only the wire shape so emitters/consumers agree when build begins.
 *
 * Orchestrator: BILL. On a company 30 days after deactivation (never-paid only),
 * BILL publishes `company.erase_requested` (SubscriptionEvents::COMPANY_ERASE_REQUESTED)
 * to SubscriptionEvents::EXCHANGE_ERASE_EVENTS, fanned out per target service. Each
 * target erases its company-scoped data and replies `company.erase_completed`
 * (SubscriptionEvents::COMPANY_ERASE_COMPLETED) into the completion ledger.
 *
 * Completion-ledger schema (one row per saga × target service):
 *   saga_id, company_id, service, status ∈ {pending, acked, failed}, acked_at, error.
 * The workspace name is released ONLY when EVERY target row is `acked` — via the
 * W0.14 held-name accessor, never directly (RESTORE-LOCK rule: once any erase has
 * begun the company cannot be restored, and the name stays held until all-acked).
 *
 * Idempotency key: saga_id (per target: saga_id + service). A target that has
 * already acked a saga_id is a no-op; a re-published request is safe to re-ack.
 */
final class EraseSagaPayload
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACKED = 'acked';
    public const STATUS_FAILED = 'failed';

    /**
     * @param  string  $sagaId  erase-saga correlation id (idempotency key across all targets)
     * @param  int  $companyId  central company id being erased (also envelope tenant_id)
     * @param  string  $workspace  workspace slug whose name is held until all-acked
     * @param  string|null  $service  target service for a fanned-out request / completing service
     *                                for a completion; null on the orchestrator's initial broadcast
     * @param  string|null  $status  self::STATUS_* on completion events; null on request
     * @param  string|null  $error  failure detail when status = failed
     */
    public function __construct(
        private readonly string $sagaId,
        private readonly int $companyId,
        private readonly string $workspace,
        private readonly ?string $service = null,
        private readonly ?string $status = null,
        private readonly ?string $error = null,
    ) {
    }

    public static function requested(string $sagaId, int $companyId, string $workspace, ?string $service = null): self
    {
        return new self($sagaId, $companyId, $workspace, $service);
    }

    public static function completed(
        string $sagaId,
        int $companyId,
        string $workspace,
        string $service,
        string $status,
        ?string $error = null,
    ): self {
        return new self($sagaId, $companyId, $workspace, $service, $status, $error);
    }

    public function eventType(): string
    {
        return $this->status === null
            ? SubscriptionEvents::COMPANY_ERASE_REQUESTED
            : SubscriptionEvents::COMPANY_ERASE_COMPLETED;
    }

    /**
     * @return array{
     *     saga_id: string,
     *     company_id: int,
     *     workspace: string,
     *     service: string|null,
     *     status: string|null,
     *     error: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'saga_id' => $this->sagaId,
            'company_id' => $this->companyId,
            'workspace' => $this->workspace,
            'service' => $this->service,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }
}
