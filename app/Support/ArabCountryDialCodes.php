<?php

namespace App\Support;

/**
 * دول عربية مع رمز الاتصال (بدون +) للتحقق وتجميع الرقم المخزَّن.
 */
final class ArabCountryDialCodes
{
    /**
     * @return array<string, string> dial_code => label_ar
     */
    public static function options(): array
    {
        return [
            '964' => 'العراق (+964)',
            '966' => 'السعودية (+966)',
            '971' => 'الإمارات (+971)',
            '973' => 'البحرين (+973)',
            '974' => 'قطر (+974)',
            '965' => 'الكويت (+965)',
            '968' => 'عُمان (+968)',
            '967' => 'اليمن (+967)',
            '962' => 'الأردن (+962)',
            '961' => 'لبنان (+961)',
            '963' => 'سوريا (+963)',
            '970' => 'فلسطين (+970)',
            '20' => 'مصر (+20)',
            '249' => 'السودان (+249)',
            '211' => 'جنوب السودان (+211)',
            '218' => 'ليبيا (+218)',
            '216' => 'تونس (+216)',
            '213' => 'الجزائر (+213)',
            '212' => 'المغرب (+212)',
            '222' => 'موريتانيا (+222)',
            '252' => 'الصومال (+252)',
            '253' => 'جيبوتي (+253)',
            '269' => 'جزر القمر (+269)',
        ];
    }

    /**
     * @return list<string>
     */
    public static function codesLongestFirst(): array
    {
        $codes = array_keys(self::options());
        usort($codes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $codes;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForFront(): array
    {
        $out = [];
        foreach (self::options() as $code => $label) {
            $out[] = ['value' => $code, 'label' => $label];
        }

        return $out;
    }

    public static function normalizeDigits(string $input): string
    {
        $map = [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ];
        $s = strtr($input, $map);

        return preg_replace('/\D+/', '', $s) ?? '';
    }
}
