<?php

namespace App\Services\Meta;

use App\Contracts\TenantMetaIntegrationContextContract;

/**
 * Default single-deployment context. Swap binding in a service provider when introducing
 * real tenant resolution (subdomain, team, organization row, etc.).
 */
final class DefaultTenantMetaIntegrationContext implements TenantMetaIntegrationContextContract
{
    public function tenantKey(): string
    {
        return 'default';
    }

    public function requiresEndCustomerTokenForAdsRead(): bool
    {
        return true;
    }
}
