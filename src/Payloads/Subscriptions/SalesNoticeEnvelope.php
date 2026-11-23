<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Payloads\Subscriptions;

/**
 * W0.13 / CD-31 — the FROZEN Slack-notice envelope for every v3 sales/ops event
 * on the SubscriptionEvents::EXCHANGE_SALES_EVENTS topic exchange.
 *
 * Every sales-notification event's payload is this envelope (P-7 REQ-2 fields):
 *   company, workspace, event, tier, user_count, admin_link.
 *
 * Emitters: UM, BILL, ORG, ADM (S3b, S4b, S8c, S9-bill/be2/adm, S11, S12, S13).
 * All emit async,
 * AFTER commit; a broker/Slack failure never blocks the action (P-7 REQ-1/4).
 * The consumer HOST is deferred (logged stub, S15 / CD-34); the exchange and
 * this envelope are frozen now so every emitter can publish from Wave 1 on.
 *
 * FIELD-OWNERSHIP / ENRICHMENT RULE (RB4 — "async never block" forbids sync
 * reads at emit time, so NO emitter holds every field):
 *   - `tier`       nullable: BILL trial/payment/renewal events hold it; UM
 *                  tier_limit_blocked / cap_250_hit do NOT (they have count-not-tier).
 *   - `user_count` nullable: UM events hold it; BILL trial/payment events may not.
 *   - `admin_link` nullable: emitters that don't hold the back-office URL leave it null.
 * The CONSUMER (S15) enriches any null field from its OWN local projections
 * (billing tier projection, UM seat usage, a link template) — it never calls
 * back synchronously to the emitter. The per-routing-key ownership matrix is in
 * docs/subscription-v3/contract/EVENTS.md; this class only guarantees the shape
 * and that unknown fields serialize as null (never omitted), so the consumer can
 * tell "emitter has no value" from "field missing".
 *
 * `event` repeats the routing key inside the payload for consumer-side logging
 * and idempotent routing when a single queue binds `#`.
 */
final class SalesNoticeEnvelope
{
    /**
     * @param  string  $event  the routing key / event type (a SubscriptionEvents::* sales value)
     * @param  int  $companyId  central company id (also envelope tenant_id)
     * @param  string  $workspace  company workspace slug (also envelope workspace)
     * @param  string|null  $companyName  display name; null ⇒ consumer enriches
     * @param  string|null  $tier  tier code (T1..T5) or null ⇒ consumer enriches
     * @param  int|null  $userCount  active+pending seat usage or null ⇒ consumer enriches
     * @param  string|null  $adminLink  back-office deep link or null ⇒ consumer builds from template
     * @param  array<string, mixed>  $context  event-specific extras (e.g. blocked_tier for
     *                                          tier_limit_blocked, amount for payment.*); kept
     *                                          OUT of the frozen top level so additions never
     *                                          touch the envelope shape
     */
    public function __construct(
        private readonly string $event,
        private readonly int $companyId,
        private readonly string $workspace,
        private readonly ?string $companyName = null,
        private readonly ?string $tier = null,
        private readonly ?int $userCount = null,
        private readonly ?string $adminLink = null,
        private readonly array $context = [],
    ) {
    }

    /**
     * @return array{
     *     event: string,
     *     company_id: int,
     *     company_name: string|null,
     *     workspace: string,
     *     tier: string|null,
     *     user_count: int|null,
     *     admin_link: string|null,
     *     context: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'company_id' => $this->companyId,
            'company_name' => $this->companyName,
            'workspace' => $this->workspace,
            'tier' => $this->tier,
            'user_count' => $this->userCount,
            'admin_link' => $this->adminLink,
            'context' => $this->context,
        ];
    }
}
