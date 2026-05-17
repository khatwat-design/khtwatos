<?php

namespace App\Http\Controllers;

use App\Services\GoodsMetaLeadSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoodsMetaLeadWebhookController extends Controller
{
    public function __construct(
        private readonly GoodsMetaLeadSyncService $sync,
    ) {}

    public function receive(Request $request): JsonResponse
    {
        $secret = (string) config('services.goods.meta_leads_webhook_secret', '');
        if ($secret === '') {
            return response()->json(['message' => 'Webhook غير مفعّل على السيرفر.'], 503);
        }

        $provided = (string) ($request->header('X-Goods-Meta-Leads-Secret')
            ?: $request->input('secret')
            ?: '');

        if (! hash_equals($secret, $provided)) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $rows = $request->input('rows');
        if (! is_array($rows)) {
            $single = $request->input('row');
            $rows = is_array($single) ? [$single] : [];
        }

        if ($rows === []) {
            return response()->json(['message' => 'لا توجد صفوف للمزامنة.'], 422);
        }

        try {
            $stats = $this->sync->upsertMany($rows);
        } catch (\Throwable $e) {
            Log::error('goods_meta_leads.webhook_failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'فشلت المزامنة.'], 500);
        }

        return response()->json([
            'ok' => true,
            'stats' => $stats,
        ]);
    }
}
