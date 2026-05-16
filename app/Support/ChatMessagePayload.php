<?php

namespace App\Support;

/**
 * حقول مشتركة لتحويل رسائل الدردشة إلى مصفوفة للواجهة.
 */
final class ChatMessagePayload
{
    /**
     * @return array<string, mixed>|null
     */
    public static function replyPreview(?object $message): ?array
    {
        if ($message === null) {
            return null;
        }

        $id = (int) ($message->id ?? 0);
        if ($id <= 0) {
            return null;
        }

        $user = $message->user ?? null;
        $body = trim((string) ($message->body ?? ''));
        $stickerKey = trim((string) ($message->sticker_key ?? ''));
        $sticker = ChatStickerCatalog::stickerPayload($stickerKey !== '' ? $stickerKey : null);

        $preview = $body;
        if ($preview === '' && $sticker) {
            $preview = $sticker['emoji'];
        }
        if ($preview === '' && ! empty($message->attachment_path)) {
            $preview = 'مرفق';
        }
        if ($preview === '') {
            $preview = 'رسالة';
        }

        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 117).'…';
        }

        return [
            'id' => $id,
            'user_name' => $user?->name ?? 'عضو',
            'preview' => $preview,
            'sticker' => $sticker,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forwardMeta(?string $fromUserName, ?string $fromContext): ?array
    {
        $name = trim((string) $fromUserName);
        $context = trim((string) $fromContext);

        if ($name === '' && $context === '') {
            return null;
        }

        return [
            'from_user_name' => $name !== '' ? $name : null,
            'from_context' => $context !== '' ? $context : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function attachmentPayload(
        ?string $path,
        ?string $name,
        ?string $mime,
        ?int $size,
    ): ?array {
        if ($path === null || $path === '') {
            return null;
        }

        $mimeStr = is_string($mime) ? strtolower($mime) : '';
        $nameStr = is_string($name) ? $name : '';
        $isAudio = ChatAttachmentRules::looksLikeVoiceNote($mimeStr !== '' ? $mimeStr : null, $nameStr);
        $resolvedMime = $isAudio
            ? ChatAttachmentRules::normalizeVoiceMime($mimeStr !== '' ? $mimeStr : null, $nameStr)
            : $mime;

        $url = ChatAttachmentRules::attachmentPublicUrl($path);
        if ($isAudio) {
            $url = route('chat.attachments.show', ['path' => $path], false)
                .'?mime='.rawurlencode((string) $resolvedMime);
        }

        return [
            'url' => $url,
            'name' => $isAudio ? 'رسالة صوتية' : $name,
            'mime' => $resolvedMime,
            'size' => $size,
            'is_image' => str_starts_with($mimeStr, 'image/'),
            'is_audio' => $isAudio,
        ];
    }
}
