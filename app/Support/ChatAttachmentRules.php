<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

final class ChatAttachmentRules
{
    public const MAX_KILOBYTES = 16384;

    /**
     * @return list<string|File>
     */
    public static function attachmentValidation(): array
    {
        // المحتوى (نص / ملصق / مرفق) يُتحقق منه في ChatReplyResolver::messageHasContent
        return [
            'nullable',
            File::types([
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf',
                'webm', 'ogg', 'oga', 'mp4', 'm4a', 'mpeg', 'mp3', 'wav',
            ])->max(self::MAX_KILOBYTES),
        ];
    }

    /**
     * @return array{path: string, name: string, mime: string|null, size: int|null}
     */
    public static function storeUploadedFile(UploadedFile $file, bool $asVoiceNote = false): array
    {
        $mime = $file->getMimeType();
        $name = $file->getClientOriginalName();
        if ($name === '' || $name === 'blob') {
            $name = self::defaultNameForMime($mime);
        }

        if ($asVoiceNote || self::looksLikeVoiceNote($mime, $name)) {
            $mime = self::normalizeVoiceMime($mime, $name);
            $name = self::voiceDisplayName($mime);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: self::extensionForMime($mime));

        return [
            'path' => $file->storeAs(
                'chat-attachments',
                Str::uuid().'.'.$extension,
                'public',
            ),
            'name' => $name,
            'mime' => $mime,
            'size' => $file->getSize(),
        ];
    }

    public static function extensionForMime(?string $mime): string
    {
        $mimeStr = strtolower((string) $mime);

        return match (true) {
            str_contains($mimeStr, 'ogg') => 'ogg',
            str_contains($mimeStr, 'mp4') => 'm4a',
            str_contains($mimeStr, 'mpeg') => 'mp3',
            str_contains($mimeStr, 'wav') => 'wav',
            str_contains($mimeStr, 'pdf') => 'pdf',
            str_contains($mimeStr, 'png') => 'png',
            str_contains($mimeStr, 'gif') => 'gif',
            str_contains($mimeStr, 'webp') => 'webp',
            str_contains($mimeStr, 'jpeg') => 'jpg',
            default => 'webm',
        };
    }

    public static function attachmentPublicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }

    public static function looksLikeVoiceNote(?string $mime, string $name): bool
    {
        $mimeStr = strtolower((string) $mime);
        $nameStr = strtolower($name);

        if (str_starts_with($mimeStr, 'audio/')) {
            return true;
        }

        if (str_starts_with($nameStr, 'voice-')) {
            return true;
        }

        if (preg_match('/\.(webm|ogg|oga|m4a|mp3|wav|opus)$/i', $nameStr)) {
            return in_array($mimeStr, ['video/webm', 'application/octet-stream', ''], true)
                || str_starts_with($mimeStr, 'audio/')
                || str_starts_with($mimeStr, 'video/');
        }

        return false;
    }

    public static function normalizeVoiceMime(?string $mime, string $name): string
    {
        $mimeStr = strtolower((string) $mime);
        if (str_starts_with($mimeStr, 'audio/')) {
            return $mimeStr;
        }

        $nameStr = strtolower($name);
        if (str_contains($nameStr, '.ogg') || str_contains($mimeStr, 'ogg')) {
            return 'audio/ogg';
        }
        if (str_contains($nameStr, '.m4a') || str_contains($mimeStr, 'mp4')) {
            return 'audio/mp4';
        }
        if (str_contains($nameStr, '.mp3') || str_contains($mimeStr, 'mpeg')) {
            return 'audio/mpeg';
        }
        if (str_contains($nameStr, '.wav')) {
            return 'audio/wav';
        }

        return 'audio/webm';
    }

    private static function voiceDisplayName(string $mime): string
    {
        $ext = match (true) {
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'mp4') => 'm4a',
            str_contains($mime, 'mpeg') => 'mp3',
            str_contains($mime, 'wav') => 'wav',
            default => 'webm',
        };

        return 'voice-'.now()->format('Ymd-His').'.'.$ext;
    }

    private static function defaultNameForMime(?string $mime): string
    {
        if (is_string($mime) && str_starts_with($mime, 'audio/')) {
            $ext = match (true) {
                str_contains($mime, 'webm') => 'webm',
                str_contains($mime, 'ogg') => 'ogg',
                str_contains($mime, 'mpeg') => 'mp3',
                str_contains($mime, 'wav') => 'wav',
                default => 'm4a',
            };

            return 'voice-'.now()->format('Ymd-His').'.'.$ext;
        }

        return 'attachment-'.now()->format('Ymd-His');
    }
}
