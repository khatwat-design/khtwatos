<?php

namespace App\Support;

use App\Models\User;

final class GoodsMetaLeadWhatsAppMessage
{
    public static function intro(?string $customerName, ?User $employee): string
    {
        $employeeName = trim((string) ($employee?->name ?? 'فريق خطوات'));
        $customerName = trim((string) $customerName);
        $honorific = $customerName !== '' ? "استاذ {$customerName}" : 'استاذ';

        return "السلام عليكم شلون صحتك {$honorific} ان شاء الله تكون بخير وياك {$employeeName} "
            .'علي موظف مبيعات في شركة خطوات سبق وان تواصل ويانا';
    }
}
