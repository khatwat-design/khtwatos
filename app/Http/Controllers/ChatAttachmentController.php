<?php

namespace App\Http\Controllers;

use App\Support\ChatAttachmentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بث مرفقات الدردشة بـ Content-Type صحيح (مهم للرسائل الصوتية خاصة الملفات القديمة بدون امتداد).
 */
class ChatAttachmentController extends Controller
{
    public function show(Request $request, string $path): StreamedResponse
    {
        $path = str_replace(['..', '\\'], ['', '/'], $path);
        if (! str_starts_with($path, 'chat-attachments/')) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        $mime = $request->query('mime');
        $name = (string) $request->query('name', '');
        if (! is_string($mime) || $mime === '') {
            $mime = ChatAttachmentRules::normalizeVoiceMime(null, $name !== '' ? $name : basename($path));
        }

        return $disk->response($path, basename($path), [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
