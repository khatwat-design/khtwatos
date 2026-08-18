<?php

namespace App\Support;

final class IraqiPhone
{
    public static function digitsOnly(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    /** رقم جوال عراقي (07…) — غالباً يدعم واتساب */
    public static function isLikelyMobile(?string $phone): bool
    {
        $local = self::toLocal($phone);
        if ($local === '') {
            return false;
        }

        return (bool) preg_match('/^07[0-9]{9}$/', $local);
    }

    public static function toLocal(?string $phone): string
    {
        $d = self::digitsOnly($phone);
        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '964')) {
            $d = substr($d, 3);
        }
        if (str_starts_with($d, '0')) {
            return substr($d, 0, 11);
        }
        if (str_starts_with($d, '7')) {
            return '0'.substr($d, 0, 10);
        }

        return '0'.substr($d, 0, 10);
    }

    public static function toWhatsAppDigits(?string $phone): string
    {
        $d = self::digitsOnly($phone);
        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '964') && strlen($d) >= 12) {
            return substr($d, 0, 13);
        }
        if (str_starts_with($d, '0')) {
            $d = '964'.ltrim($d, '0');
        } elseif (str_starts_with($d, '7')) {
            $d = '964'.$d;
        } else {
            $d = '964'.$d;
        }

        return strlen($d) >= 12 ? $d : '';
    }

    public static function whatsAppUrl(?string $phone, ?string $message = null): string
    {
        $digits = self::toWhatsAppDigits($phone);
        if ($digits === '') {
            return '';
        }

        $url = 'https://wa.me/'.$digits;
        if ($message !== null && trim($message) !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }

    /** واتساب بزنس: whatsapp:// أو Intent أندرويد */
    public static function whatsAppBusinessUrl(?string $phone, ?string $message = null): string
    {
        $digits = self::toWhatsAppDigits($phone);
        if ($digits === '') {
            return '';
        }

        $textParam = '';
        if ($message !== null && trim($message) !== '') {
            $textParam = '&text='.rawurlencode($message);
        }

        return 'whatsapp://send?phone='.$digits.$textParam;
    }
}
