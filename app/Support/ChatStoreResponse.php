<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ChatStoreResponse
{
    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $extra
     */
    public static function created(Request $request, array $message, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(array_merge(['message' => $message], $extra));
        }

        return redirect()->back();
    }
}
