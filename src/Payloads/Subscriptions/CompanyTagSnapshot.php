<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Payloads\Subscriptions;

/**
 * W0.2 — the THREE additive fields the Subscription-v3 tag adds to the existing
 * ORG `company.created` / `company.updated` snapshot payload
 * (CompanyEventPublisher::snapshot). Additive-only (landmine §5.10): merge these
 * keys into the full snapshot, never rename or remove existing keys.
 *
 * - pricing_model  immutable: 'v2_1' | 'v3' (CD-07). Absent ⇒ consumers treat as 'v2_1'
 *                  for render/seat, but Qoyod push SUPPRESSES on absent (CD-33).
 * - segment        mutable:   'sme' | 'enterprise' (CD-07 REQ-10).
 * - effective_flow derived (ORG is the single derivation authority — CD-16):
 *                  f(pricing_model, segment) ∈
 *                    'v2_1' | 'v3_sme_selfserve' | 'v3_enterprise_manual'.
 *                  ORG also STORES this so UM reads it live (CD-32) without re-deriving.
 *
 * This is a payload FRAGMENT, not a standalone event: callers spread
 * `toArray()` into the full company snapshot before publishing.
 */
final class CompanyTagSnapshot
{
    public const PRICING_MODEL_V2_1 = 'v2_1';
    public const PRICING_MODEL_V3 = 'v3';

    public const SEGMENT_SME = 'sme';
    public const SEGMENT_ENTERPRISE = 'enterprise';

    public const FLOW_V2_1 = 'v2_1';
    public const FLOW_V3_SME_SELFSERVE = 'v3_sme_selfserve';
    public const FLOW_V3_ENTERPRISE_MANUAL = 'v3_enterprise_manual';

    public function __construct(
        private readonly string $pricingModel,
        private readonly string $segment,
        private readonly string $effectiveFlow,
    ) {
    }

    /**
     * Derive effective_flow once, in ORG, the single authority (CD-16).
     * v2_1 pricing ⇒ always v2_1; v3 ⇒ enterprise segment runs the manual flow.
     */
    public static function derive(string $pricingModel, string $segment): self
    {
        if ($pricingModel === self::PRICING_MODEL_V2_1) {
            $flow = self::FLOW_V2_1;
        } elseif ($segment === self::SEGMENT_ENTERPRISE) {
            $flow = self::FLOW_V3_ENTERPRISE_MANUAL;
        } else {
            $flow = self::FLOW_V3_SME_SELFSERVE;
        }

        return new self($pricingModel, $segment, $flow);
    }

    /**
     * The additive fields to merge into the full company snapshot.
     *
     * @return array{pricing_model: string, segment: string, effective_flow: string}
     */
    public function toArray(): array
    {
        return [
            'pricing_model' => $this->pricingModel,
            'segment' => $this->segment,
            'effective_flow' => $this->effectiveFlow,
        ];
    }
}
