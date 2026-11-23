<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Payloads\Subscriptions;

/**
 * W0.3 — `entitlement.changed` (routing key SubscriptionEvents::ENTITLEMENT_CHANGED,
 * exchange SubscriptionEvents::EXCHANGE_BILLING_EVENTS).
 *
 * Publisher: BILL (the SINGLE entitlement authority — CD-14), emitted AFTER the
 * transaction commits. Consumers: the flags-as-cache reconciliation consumers —
 * BE2 FeatureFlagRepository (S7b) and the Pennant projection — which re-converge
 * their caches (SLO 60s + alarm, CD-28). Money-costing opens read the sync
 * authority endpoint, never the cache, during the consistency window.
 *
 * Entitlement rule (CD-14): `entitlement = active modules ∪ purchased add-ons`.
 * `features` is the FULL resolved feature-key set (a snapshot, not a delta) so a
 * consumer can converge idempotently regardless of ordering or missed messages.
 *
 * Idempotency key: event_id (envelope) OR the natural key
 * (company_id, entitlement_version) — consumers dedupe on whichever they persist;
 * a stale entitlement_version is ignored.
 */
final class EntitlementChangedPayload
{
    /**
     * @param  int  $companyId  central company id (also the envelope tenant_id)
     * @param  list<string>  $features  full resolved feature-key set (modules ∪ add-ons)
     * @param  list<string>  $modules  active module keys contributing to the union
     * @param  list<string>  $addons  purchased, unexpired add-on keys contributing to the union
     * @param  int  $entitlementVersion  monotonic per-company version; consumers drop older
     * @param  string  $reason  what triggered the change (e.g. 'module_change', 'addon_purchased',
     *                          'addon_expired', 'conversion', 'reconciliation')
     */
    public function __construct(
        private readonly int $companyId,
        private readonly array $features,
        private readonly array $modules,
        private readonly array $addons,
        private readonly int $entitlementVersion,
        private readonly string $reason,
    ) {
    }

    /**
     * @return array{
     *     company_id: int,
     *     features: list<string>,
     *     modules: list<string>,
     *     addons: list<string>,
     *     entitlement_version: int,
     *     reason: string
     * }
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'features' => array_values($this->features),
            'modules' => array_values($this->modules),
            'addons' => array_values($this->addons),
            'entitlement_version' => $this->entitlementVersion,
            'reason' => $this->reason,
        ];
    }

    /**
     * Natural idempotency key for consumers that dedupe on content rather than
     * the envelope event_id.
     */
    public function idempotencyKey(): string
    {
        return $this->companyId . ':' . $this->entitlementVersion;
    }
}
