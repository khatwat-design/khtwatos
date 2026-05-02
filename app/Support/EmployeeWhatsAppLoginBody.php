<?php

namespace App\Support;

final class EmployeeWhatsAppLoginBody
{
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'مدير نظام',
            'lead' => 'قائد فريق',
            default => 'موظف',
        };
    }

    public static function build(
        string $userName,
        string $username,
        string $arabicDisplayName,
        string $title,
        string $loginUrl,
        string $plainPassword,
        string $role,
    ): string {
        $roleLabel = self::roleLabel($role);

        $lines = [
            "مرحبًا {$arabicDisplayName}،",
            '',
            'تم تجهيز بيانات الدخول لنظام خارج المخزون.',
        ];

        if ($title !== '') {
            $lines[] = "المنصب: {$title}";
            $lines[] = '';
        }

        $lines[] = 'رابط الدخول:';
        $lines[] = $loginUrl;
        $lines[] = '';
        $lines[] = 'اسم المستخدم:';
        $lines[] = $username;
        $lines[] = '';
        $lines[] = 'يمكنك أيضًا تسجيل الدخول بالاسم الكامل إن وُجد مطابقًا:';
        $lines[] = $userName;
        $lines[] = '';
        $lines[] = 'كلمة المرور الموحدة حاليًا:';
        $lines[] = $plainPassword;
        $lines[] = '';
        $lines[] = 'الدور في النظام: '.$roleLabel;
        $lines[] = '';
        $lines[] = 'يُفضّل تغيير كلمة المرور لاحقًا من الإعدادات.';

        return implode("\n", $lines);
    }
}
