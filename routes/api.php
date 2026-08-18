<?php

use App\Http\Controllers\Api\AiBotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| بوت المبيعات - مسارات API للواجهة الأمامية
|
*/

// Auth routes (public)
Route::post('/auth/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['بيانات الدخول غير صحيحة.'],
        ]);
    }

    $token = $user->createToken('bot-dashboard-token')->plainTextToken;

    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? 'admin',
        ],
        'token' => $token,
    ]);
});

Route::post('/auth/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'تم تسجيل الخروج']);
});

Route::get('/auth/me', function (Request $request) {
    $user = $request->user();
    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role ?? 'admin',
    ]);
})->middleware('auth:sanctum');

// Bot routes (authenticated)
Route::middleware('auth:sanctum')->prefix('bot')->group(function () {
    Route::get('/dashboard', [AiBotController::class, 'dashboard']);
    Route::get('/conversations', [AiBotController::class, 'conversations']);
    Route::get('/conversations/{id}/messages', [AiBotController::class, 'conversationMessages']);
    Route::post('/conversations/{id}/toggle-bot', [AiBotController::class, 'toggleBot']);
    Route::get('/leads', [AiBotController::class, 'leads']);
    Route::get('/meetings', [AiBotController::class, 'meetings']);
    Route::get('/settings', [AiBotController::class, 'settings']);
    Route::put('/settings', [AiBotController::class, 'updateSettings']);
});
