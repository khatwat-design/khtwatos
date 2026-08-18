<?php

namespace App\Support;

/**
 * روابط عامة لعرض صفحة فيسبوك/إنستغرام من قيمة مخزّنة (معرّف، اسم مستخدم، أو رابط كامل).
 */
final class SocialProfileUrl
{
    public static function facebook(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $s)) {
            return $s;
        }
        if (preg_match('#(facebook|fb)\.com#i', $s)) {
            return 'https://'.ltrim(preg_replace('#^//+#', '', preg_replace('#^https?://#i', '', $s)), '/');
        }
        if (ctype_digit($s)) {
            return 'https://www.facebook.com/'.$s;
        }

        return 'https://www.facebook.com/'.ltrim($s, '/');
    }

    public static function instagram(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $s = ltrim($s, '@');
        if (preg_match('#^https?://#i', $s)) {
            return $s;
        }
        if (preg_match('#instagram\.com#i', $s)) {
            return 'https://'.ltrim(preg_replace('#^https?://#i', '', $s), '/');
        }
        if (ctype_digit($s)) {
            return 'https://www.instagram.com/'.$s.'/';
        }

        return 'https://www.instagram.com/'.rawurlencode($s).'/';
    }
}
