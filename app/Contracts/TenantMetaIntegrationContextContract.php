<?php

namespace App\Contracts;

/**
 * Preparation seam for tenant-scoped Meta (Facebook/Instagram) integrations.
 *
 * Today: tokens and Graph calls are effectively scoped per `client_id` row, while the Meta *app*
 * identity (OAuth client id/secret, webhook verify tokens) is typically deployment-global — which
 * can blur "who owns the business" in multi-tenant or white-label futures.
 *
 * Future implementations should supply: app credentials, redirect URIs, token encryption context,
 * and optional per-tenant business/ad-account allowlists — without changing call-site contracts
 * on first rollout.
 *
 * Do not wire this interface everywhere yet; `ClientMetaConnectionService` remains the façade.
 */
interface TenantMetaIntegrationContextContract
{
    /** Logical tenant key (today: single deployment → fixed string). */
    public function tenantKey(): string;

    /**
     * Whether outbound Graph requests must be attributed to the end-customer token only
     * (portal OAuth) vs mixed system keys.
     */
    public function requiresEndCustomerTokenForAdsRead(): bool;
}
