<?php

namespace App\Http\Controllers;

use App\Models\DevicePushToken;
use App\Support\EffectiveSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicePushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (! EffectiveSettings::firebaseMobilePushEnabled()) {
            return response()->json(['ok' => false, 'reason' => 'firebase_mobile_push_disabled'], 422);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios'],
        ]);

        DevicePushToken::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $data['token'],
            ],
            [
                'platform' => $data['platform'],
                'last_used_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DevicePushToken::query()
            ->where('user_id', $user->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['ok' => true]);
    }
}
