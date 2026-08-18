<?php

namespace App\Support;

use App\Models\User;

final class EmployeeUsername
{
    /**
     * اسم مستخدم لاتيني: حرفان من الاسم العربي + آخر 4 أرقام من الجوال، مع ضمان التفرد.
     */
    public static function uniqueFromArabicNameAndPhone(string $arabicName, string $phoneDigits, ?int $ignoreUserId = null): string
    {
        $suffix = substr(preg_replace('/\D+/', '', $phoneDigits) ?: '0000', -4);
        $two = self::twoLetterLatinKey($arabicName);
        $base = strtolower(preg_replace('/[^a-z0-9]/', '', $two.$suffix) ?: 'u'.$suffix);

        $candidate = $base;
        $n = 0;
        while (self::usernameTaken($candidate, $ignoreUserId)) {
            $n++;
            $candidate = $base.$n;
        }

        return $candidate;
    }

    private static function usernameTaken(string $username, ?int $ignoreUserId): bool
    {
        $q = User::query()->where('username', $username);
        if ($ignoreUserId !== null) {
            $q->where('id', '!=', $ignoreUserId);
        }

        return $q->exists();
    }

    private static function twoLetterLatinKey(string $arabicName): string
    {
        $name = trim($arabicName);
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) >= 2) {
            $a = mb_substr($parts[0], 0, 1);
            $b = mb_substr($parts[1], 0, 1);
        } else {
            $single = $parts[0] ?? $name;
            $a = mb_substr($single, 0, 1);
            $b = mb_substr($single, 1, 1) ?: $a;
        }

        return self::translitChar($a).self::translitChar($b);
    }

    private static function translitChar(string $ch): string
    {
        static $map = [
            'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ا' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 's',
            'ج' => 'j', 'ح' => 'h', 'خ' => 'k', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z',
            'س' => 's', 'ش' => 's', 'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
            'غ' => 'g', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
            'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'h', 'ؤ' => 'w', 'ئ' => 'y',
            'ء' => 'a',
        ];

        return $map[$ch] ?? 'x';
    }
}
