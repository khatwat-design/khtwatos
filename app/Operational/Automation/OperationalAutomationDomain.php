<?php

namespace App\Operational\Automation;

enum OperationalAutomationDomain: string
{
    case Crm = 'crm';
    case Messaging = 'messaging';
    case Campaigns = 'campaigns';
    case Workforce = 'workforce';
    case Notifications = 'notifications';
    case Operations = 'operations';
}
