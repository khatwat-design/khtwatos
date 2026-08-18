<?php

namespace Tests\Unit;

use App\Services\ClientMetaConnectionService;
use Tests\TestCase;

class ClientMetaConnectionServiceTest extends TestCase
{
    public function test_missing_required_scopes_detects_gaps(): void
    {
        $svc = new ClientMetaConnectionService;
        $missing = $svc->missingRequiredScopes(['ads_read', 'read_insights']);
        $this->assertContains('business_management', $missing);
        $this->assertContains('pages_messaging', $missing);
    }

    public function test_granted_permissions_from_me_permissions_payload(): void
    {
        $svc = new ClientMetaConnectionService;
        $granted = $svc->grantedPermissionsFromMePermissions([
            ['permission' => 'ads_read', 'status' => 'granted'],
            ['permission' => 'pages_messaging', 'status' => 'declined'],
        ]);
        $this->assertSame(['ads_read'], $granted);
    }
}
