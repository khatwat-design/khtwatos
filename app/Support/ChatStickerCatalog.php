<?php

namespace App\Support;

final class ChatStickerCatalog
{
    /**
     * @return list<array{id: string, label: string, stickers: list<array{key: string, emoji: string}>}>
     */
    public static function packsForFrontend(): array
    {
        $packs = config('chat_stickers.packs', []);

        return array_values(array_filter(array_map(function (mixed $pack): ?array {
            if (! is_array($pack)) {
                return null;
            }

            $id = (string) ($pack['id'] ?? '');
            $label = (string) ($pack['label'] ?? '');
            $stickers = [];
            foreach ($pack['stickers'] ?? [] as $sticker) {
                if (! is_array($sticker)) {
                    continue;
                }
                $key = (string) ($sticker['key'] ?? '');
                $emoji = (string) ($sticker['emoji'] ?? '');
                if ($key !== '' && $emoji !== '') {
                    $stickers[] = ['key' => $key, 'emoji' => $emoji];
                }
            }

            if ($id === '' || $stickers === []) {
                return null;
            }

            return [
                'id' => $id,
                'label' => $label !== '' ? $label : $id,
                'stickers' => $stickers,
            ];
        }, $packs)));
    }

    public static function isValidKey(?string $key): bool
    {
        $key = trim((string) $key);
        if ($key === '') {
            return false;
        }

        foreach (self::packsForFrontend() as $pack) {
            foreach ($pack['stickers'] as $sticker) {
                if ($sticker['key'] === $key) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function emojiForKey(?string $key): ?string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }

        foreach (self::packsForFrontend() as $pack) {
            foreach ($pack['stickers'] as $sticker) {
                if ($sticker['key'] === $key) {
                    return $sticker['emoji'];
                }
            }
        }

        return null;
    }

    /**
     * @return array{key: string, emoji: string}|null
     */
    public static function stickerPayload(?string $key): ?array
    {
        $emoji = self::emojiForKey($key);
        $key = trim((string) $key);

        if ($emoji === null || $key === '') {
            return null;
        }

        return [
            'key' => $key,
            'emoji' => $emoji,
        ];
    }
}
