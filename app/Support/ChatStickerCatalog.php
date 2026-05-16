<?php

namespace App\Support;

final class ChatStickerCatalog
{
    /**
     * @return list<array{id: string, label: string, subtitle: string, stickers: list<array{key: string, label: string, url: string}>}>
     */
    public static function packsForFrontend(): array
    {
        $packs = config('chat_stickers.packs', []);

        return array_values(array_filter(array_map(function (mixed $pack): ?array {
            if (! is_array($pack)) {
                return null;
            }

            $packId = (string) ($pack['id'] ?? '');
            $label = (string) ($pack['label'] ?? '');
            $subtitle = (string) ($pack['subtitle'] ?? '');
            $stickers = [];

            foreach ($pack['stickers'] ?? [] as $sticker) {
                if (! is_array($sticker)) {
                    continue;
                }
                $stickerId = (string) ($sticker['id'] ?? '');
                if ($packId === '' || $stickerId === '') {
                    continue;
                }
                $key = self::composeKey($packId, $stickerId);
                $url = self::urlForPackAndId($packId, $stickerId);
                if ($url === null) {
                    continue;
                }
                $stickers[] = [
                    'key' => $key,
                    'label' => (string) ($sticker['label'] ?? $stickerId),
                    'url' => $url,
                ];
            }

            if ($packId === '' || $stickers === []) {
                return null;
            }

            return [
                'id' => $packId,
                'label' => $label !== '' ? $label : $packId,
                'subtitle' => $subtitle,
                'stickers' => $stickers,
            ];
        }, $packs)));
    }

    public static function composeKey(string $packId, string $stickerId): string
    {
        return $packId.':'.$stickerId;
    }

    public static function urlForKey(?string $key): ?string
    {
        $parsed = self::parseKey($key);
        if ($parsed === null) {
            return null;
        }

        return self::urlForPackAndId($parsed['pack'], $parsed['id']);
    }

    public static function labelForKey(?string $key): ?string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }

        foreach (config('chat_stickers.packs', []) as $pack) {
            if (! is_array($pack)) {
                continue;
            }
            $packId = (string) ($pack['id'] ?? '');
            foreach ($pack['stickers'] ?? [] as $sticker) {
                if (! is_array($sticker)) {
                    continue;
                }
                $stickerId = (string) ($sticker['id'] ?? '');
                if (self::composeKey($packId, $stickerId) === $key) {
                    $label = trim((string) ($sticker['label'] ?? ''));

                    return $label !== '' ? $label : $stickerId;
                }
            }
        }

        return null;
    }

    public static function isValidKey(?string $key): bool
    {
        return self::labelForKey($key) !== null;
    }

    /**
     * @return array{key: string, url: string, label: string, pack_id: string}|null
     */
    public static function stickerPayload(?string $key): ?array
    {
        $key = trim((string) $key);
        $parsed = self::parseKey($key);

        if ($parsed === null) {
            return null;
        }

        $url = self::urlForPackAndId($parsed['pack'], $parsed['id']);
        if ($url === null) {
            return null;
        }

        return [
            'key' => $key,
            'url' => $url,
            'label' => self::labelForKey($key) ?? $parsed['id'],
            'pack_id' => $parsed['pack'],
        ];
    }

    /**
     * @return array{pack: string, id: string}|null
     */
    private static function parseKey(?string $key): ?array
    {
        $key = trim((string) $key);
        if ($key === '' || ! str_contains($key, ':')) {
            return null;
        }

        [$pack, $id] = explode(':', $key, 2);
        $pack = trim($pack);
        $id = trim($id);

        if ($pack === '' || $id === '') {
            return null;
        }

        return ['pack' => $pack, 'id' => $id];
    }

    private static function urlForPackAndId(string $packId, string $stickerId): ?string
    {
        $base = 'chat/stickers/'.$packId.'/'.$stickerId;

        foreach (['webp', 'png', 'gif', 'svg'] as $ext) {
            $relative = $base.'.'.$ext;
            if (is_file(public_path($relative))) {
                return asset($relative);
            }
        }

        // مسار متوقع حتى لو لم يُرفع الملف بعد (يتجنب اختفاء الملصق بعد الإرسال)
        return asset($base.'.png');
    }
}
