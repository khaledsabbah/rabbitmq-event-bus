<?php

declare(strict_types=1);

namespace Idaratech\EventBus\Payloads\Subscriptions;

/**
 * Wave-0 frozen catalogue of Subscription-v3 integration events: exchanges,
 * routing keys (= event types — RabbitMqEventBus publishes with the event type
 * as the routing key) and payload versions.
 *
 * These are protocol identifiers shared with the broker and every service on
 * the stream; the string values must match EXACTLY across repos and languages
 * (no normalization). The authoritative contract is
 * docs/subscription-v3/contract/EVENTS.md.
 *
 * Payload changes are additive-only: add fields, never rename/remove, unless a
 * human approves a version bump. Consumers default any absent field per the
 * split fail-safe (CD-33): render/seat ⇒ v2_1; Qoyod push ⇒ SUPPRESS+alert.
 */
final class SubscriptionEvents
{
    // ---- Exchanges (topic) ------------------------------------------------

    /**
     * Existing company snapshot stream (ORG → ADM). W0.2 rides this additively.
     * BILL joins as a NEW consumer (W0.10a / CD-23); it is publish-only today.
     */
    public const EXCHANGE_ORG_EVENTS = 'org-events';

    /**
     * NET-NEW topic exchange for billing/entitlement integration events
     * (BILL → reconciliation consumers). W0.3 `entitlement.changed` rides this.
     * Not to be overloaded onto hr-events (RESEARCH §6.4 / CD-14).
     */
    public const EXCHANGE_BILLING_EVENTS = 'billing-events';

    /**
     * NET-NEW topic exchange for ADM→BILL subscription-clock transitions.
     * W0.6 transfer.pending/resolved ride this (ADM approval → billing crons).
     */
    public const EXCHANGE_SUBSCRIPTION_EVENTS = 'subscription-events';

    /**
     * NET-NEW topic exchange carrying every v3 sales/ops Slack-notice event
     * (W0.13 / CD-31). Emitters: UM, BILL, ORG, ADM. The consumer HOST is
     * deferred (logged stub, S15 / CD-34) but the exchange + envelope are frozen.
     */
    public const EXCHANGE_SALES_EVENTS = 'sales-events';

    /**
     * NET-NEW topic exchange for the cross-service erase saga fan-out
     * (W0.7 / CD-25). SHAPE ONLY — implementation deferred to Wave 5.
     */
    public const EXCHANGE_ERASE_EVENTS = 'erase-events';

    // ---- W0.2 snapshot (rides org-events) ---------------------------------

    public const COMPANY_CREATED = 'company.created';
    public const COMPANY_UPDATED = 'company.updated';

    // ---- W0.3 entitlement (billing-events) --------------------------------

    public const ENTITLEMENT_CHANGED = 'entitlement.changed';

    // ---- W0.6 transfer clock (subscription-events) ------------------------

    public const TRANSFER_PENDING = 'transfer.pending';
    public const TRANSFER_RESOLVED = 'transfer.resolved';

    // ---- W0.7 erase saga (erase-events) — SHAPE ONLY ----------------------

    public const COMPANY_ERASE_REQUESTED = 'company.erase_requested';
    public const COMPANY_ERASE_COMPLETED = 'company.erase_completed';

    // ---- W0.13 sales notifications (sales-events) -------------------------

    public const SUBSCRIPTION_TIER_LIMIT_BLOCKED = 'subscription.tier_limit_blocked';
    public const SUBSCRIPTION_CAP_250_HIT = 'subscription.cap_250_hit';

    public const TRIAL_STARTED = 'trial.started';
    public const TRIAL_LOCKED = 'trial.locked';
    public const TRIAL_PRE_DELETE_NOTICE = 'trial.pre_delete_notice';

    public const PAYMENT_FIRST_CONFIRMED = 'payment.first_confirmed';

    public const RENEWAL_SUBMITTED = 'renewal.submitted';
    public const RENEWAL_CONFIRMED = 'renewal.confirmed';
    public const RENEWAL_UNPAID = 'renewal.unpaid';

    public const UPGRADE_SUBMITTED = 'upgrade.submitted';
    public const UPGRADE_CONFIRMED = 'upgrade.confirmed';

    public const SUBSCRIPTION_CONVERTED = 'subscription.converted';
    public const SIGNUP_CONTACT_US = 'signup.contact_us';
    public const CONTACT_NEEDS_MORE_FEATURES = 'contact.needs_more_features';

    // ---- Payload version (envelope `version`, matches existing "1.0") ------

    public const VERSION = '1.0';

    private function __construct()
    {
    }
}
