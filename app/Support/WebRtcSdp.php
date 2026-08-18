<?php

namespace App\Support;

/**
 * Normalize WebRTC session descriptions stored or relayed through JSON/DB.
 */
class WebRtcSdp
{
    /**
     * @param  array<string, mixed>|null  $data
     * @return array{type: string, sdp: string}|null
     */
    public static function normalize(?array $data, string $defaultType = 'offer'): ?array
    {
        if ($data === null || $data === []) {
            return null;
        }

        for ($depth = 0; $depth < 6; $depth++) {
            if (isset($data['sdp']) && is_array($data['sdp'])) {
                $data = $data['sdp'];

                continue;
            }

            if (! isset($data['type'], $data['sdp'])) {
                return null;
            }

            $type = is_string($data['type']) && $data['type'] !== ''
                ? $data['type']
                : $defaultType;

            if (! is_string($data['sdp'])) {
                return null;
            }

            $text = trim($data['sdp']);
            if ($text === '') {
                return null;
            }

            if (str_starts_with($text, '{')) {
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    $data = $decoded;

                    continue;
                }
            }

            $sdp = self::repairText($text);
            if (! self::isValid($sdp)) {
                return null;
            }

            return ['type' => $type, 'sdp' => $sdp];
        }

        return null;
    }

    public static function repairText(string $sdp): string
    {
        $sdp = trim($sdp);
        if ($sdp === '') {
            return '';
        }

        if (! str_contains($sdp, "\n") && str_contains($sdp, '\\n')) {
            $sdp = str_replace(['\\r\\n', '\\n'], ["\r\n", "\n"], $sdp);
        }

        $sdp = str_replace("\r\n", "\n", $sdp);
        $sdp = preg_replace("/\n{3,}/", "\n\n", $sdp) ?? $sdp;

        return trim($sdp)."\n";
    }

    public static function isValid(string $sdp): bool
    {
        return str_contains($sdp, 'v=0') && str_contains($sdp, "\nm=");
    }
}
