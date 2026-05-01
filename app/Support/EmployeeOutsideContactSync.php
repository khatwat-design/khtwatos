<?php

namespace App\Support;

use App\Models\OutsideContact;
use App\Models\OutsideConversation;

/**
 * يربط رقم الموظف بقسم الخارج ويوسم الجهة كموظف حتى لا تُنشأ لهم سجلات بضاعة تلقائية عند الرد على واتساب.
 */
final class EmployeeOutsideContactSync
{
    public static function sync(int $userId, string $phoneDigits, string $displayName): void
    {
        $digits = preg_replace('/\D+/', '', $phoneDigits) ?: $phoneDigits;
        if ($digits === '') {
            return;
        }

        $name = mb_substr(trim($displayName), 0, 255);

        /** @var OutsideContact $contact */
        $contact = OutsideContact::query()->firstOrCreate(
            ['phone' => $digits],
            [
                'channel' => 'whatsapp',
                'name' => $name,
            ]
        );

        $meta = array_merge($contact->meta ?? [], [
            'employee_user_id' => $userId,
            'source' => 'employee_provision',
        ]);

        $contact->forceFill([
            'name' => $name !== '' ? $name : $contact->name,
            'meta' => $meta,
        ])->save();

        OutsideConversation::query()->firstOrCreate(
            ['outside_contact_id' => $contact->id],
        );
    }
}
