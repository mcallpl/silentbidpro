<?php
// ============================================================
// SAAS PLANS — single source of truth for tiers + server-side feature gating.
//
// Gate features by organizations.plan on the SERVER (the client is never
// trusted). The iOS app may read capabilities to adjust its UI but must never
// surface pricing or purchase — subscriptions are sold on the web only.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

/**
 * The plan catalog. `max_active_events`: null = unlimited. Prices are here for
 * the WEB billing page only; capability endpoints must not leak them to iOS.
 */
function planCatalog(): array {
    static $catalog = null;
    if ($catalog !== null) return $catalog;
    $catalog = [
        'free' => [
            'label'               => 'Seedling',
            'price_monthly_cents' => 0,
            'max_active_events'   => 1,
            'custom_branding'     => false,
            'csv_export'          => false,
            'bigscreen_display'   => false,
            'multi_chapter'       => false,
            'api_access'          => false,
            'sso'                 => false,
            'priority_support'    => false,
        ],
        'pro' => [
            'label'               => 'Pro',
            'price_monthly_cents' => 9900,
            'max_active_events'   => 3,
            'custom_branding'     => true,
            'csv_export'          => true,
            'bigscreen_display'   => true,
            'multi_chapter'       => false,
            'api_access'          => false,
            'sso'                 => false,
            'priority_support'    => true,
        ],
        'enterprise' => [
            'label'               => 'Enterprise',
            'price_monthly_cents' => 39900,
            'max_active_events'   => null, // unlimited
            'custom_branding'     => true,
            'csv_export'          => true,
            'bigscreen_display'   => true,
            'multi_chapter'       => true,
            'api_access'          => true,
            'sso'                 => true,
            'priority_support'    => true,
        ],
    ];
    return $catalog;
}

/** Coerce any value to a known plan key, defaulting to 'free'. */
function normalizePlan($plan): string {
    $plan = is_string($plan) ? strtolower(trim($plan)) : '';
    return isset(planCatalog()[$plan]) ? $plan : 'free';
}

/** The org's current plan key ('free' if the org is missing/unset). */
function getOrgPlan($org_id): string {
    if (!$org_id) return 'free';
    $row = dbGetRow("SELECT plan FROM organizations WHERE id = ?", [(int)$org_id]);
    return normalizePlan($row['plan'] ?? 'free');
}

/** The full capability set for a plan key. */
function planFeatures($plan): array {
    return planCatalog()[normalizePlan($plan)];
}

/** The org's capability set. */
function orgFeatures($org_id): array {
    return planFeatures(getOrgPlan($org_id));
}

/** Whether the org's plan includes a boolean feature (e.g. 'custom_branding'). */
function orgCan($org_id, string $feature): bool {
    $f = orgFeatures($org_id);
    return !empty($f[$feature]);
}

/** A plan's numeric limit (int) or null for unlimited. */
function planLimit(string $plan, string $key) {
    return planFeatures($plan)[$key] ?? null;
}

/** Count of an org's currently-active (open) events, optionally excluding one. */
function orgActiveEventCount($org_id, int $exclude_event_id = 0): int {
    return (int)dbGetValue(
        "SELECT COUNT(*) FROM events WHERE organization_id = ? AND status = 'open' AND id <> ?",
        [(int)$org_id, $exclude_event_id]
    );
}

/**
 * Whether the org may have one MORE active event (i.e. open `$event_id`).
 * Counts active events excluding `$event_id` itself so re-saving an already-open
 * event never trips the limit.
 */
function orgCanOpenAnotherEvent($org_id, int $event_id = 0): bool {
    $limit = planLimit(getOrgPlan($org_id), 'max_active_events');
    if ($limit === null) return true; // unlimited
    return orgActiveEventCount($org_id, $event_id) < $limit;
}

/** The lowest plan key that includes a boolean feature, or null if none. */
function minPlanForFeature(string $feature): ?string {
    foreach (['free', 'pro', 'enterprise'] as $p) {
        if (!empty(planCatalog()[$p][$feature])) return $p;
    }
    return null;
}

/** A friendly upgrade message for a gated feature. */
function upgradeMessage(string $feature, string $current_plan): string {
    $need = minPlanForFeature($feature);
    $label = $need ? planCatalog()[$need]['label'] : 'a higher';
    $pretty = ucwords(str_replace('_', ' ', $feature));
    return "{$pretty} requires the {$label} plan. Your organization is on the "
         . planCatalog()[normalizePlan($current_plan)]['label']
         . " plan. Upgrade on the web to enable it.";
}

/**
 * API guard: require a boolean feature for an org, else 403 with an upgrade
 * message. Returns void; dies on failure.
 */
function requireOrgFeature($org_id, string $feature): void {
    if (!orgCan($org_id, $feature)) {
        http_response_code(403);
        die(json_encode([
            'status'        => 'error',
            'code'          => 'upgrade_required',
            'feature'       => $feature,
            'current_plan'  => getOrgPlan($org_id),
            'required_plan' => minPlanForFeature($feature),
            'message'       => upgradeMessage($feature, getOrgPlan($org_id)),
        ]));
    }
}

/** Resolve the organization id that owns an event. */
function orgIdForEvent(int $event_id): int {
    return (int)dbGetValue("SELECT organization_id FROM events WHERE id = ?", [$event_id]);
}
