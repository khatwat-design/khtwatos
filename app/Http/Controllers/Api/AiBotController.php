<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotInteractionLog;
use App\Models\GoodsMetaLead;
use App\Models\Meeting;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AiBotController extends Controller
{
    /**
     * Dashboard statistics.
     */
    public function dashboard(): JsonResponse
    {
        $totalConversations = OutsideConversation::query()->count();

        $activeConversations = OutsideConversation::query()
            ->where('status', 'new')
            ->when(Schema::hasColumn('outside_conversations', 'bot_active'), function ($q) {
                $q->where('bot_active', true);
            })
            ->count();

        $totalLeads = GoodsMetaLead::query()->count();

        $meetingsScheduled = Schema::hasTable('meetings')
            ? Meeting::query()->where('status', '!=', 'cancelled')->count()
            : 0;

        $wonLeads = GoodsMetaLead::query()->where('workflow_status', 'won')->count();
        $conversionRate = $totalLeads > 0 ? round($wonLeads / $totalLeads * 100, 1) : 0;

        $totalBotMessages = Schema::hasTable('bot_interaction_logs')
            ? BotInteractionLog::query()->where('direction', 'outbound')->count()
            : 0;

        $totalInbound = OutsideMessage::query()->where('direction', 'inbound')->count();
        $responseRate = $totalInbound > 0 ? round($totalBotMessages / $totalInbound * 100, 1) : 0;

        $recentConversations = OutsideConversation::query()
            ->with('contact')
            ->orderByDesc('last_inbound_at')
            ->limit(10)
            ->get()
            ->map(fn (OutsideConversation $c) => [
                'id' => $c->id,
                'contact_name' => $c->contact?->name ?? 'غير معروف',
                'contact_phone' => $c->contact?->phone ?? '',
                'latest_message' => $c->latest_message_preview ?? '',
                'status' => $c->status ?? 'new',
                'flow_state' => Schema::hasColumn('outside_conversations', 'flow_state')
                    ? ($c->flow_state ?? 'new') : 'new',
                'last_message_at' => $c->last_inbound_at?->toIso8601String() ?? $c->created_at->toIso8601String(),
                'bot_active' => Schema::hasColumn('outside_conversations', 'bot_active')
                    ? (bool) $c->bot_active : true,
            ]);

        return response()->json([
            'total_conversations' => $totalConversations,
            'active_conversations' => $activeConversations,
            'total_leads' => $totalLeads,
            'meetings_scheduled' => $meetingsScheduled,
            'conversion_rate' => $conversionRate,
            'bot_response_rate' => $responseRate,
            'recent_conversations' => $recentConversations,
        ]);
    }

    /**
     * List all conversations with bot status.
     */
    public function conversations(): JsonResponse
    {
        $conversations = OutsideConversation::query()
            ->with('contact')
            ->orderByDesc('last_inbound_at')
            ->get()
            ->map(fn (OutsideConversation $c) => [
                'id' => $c->id,
                'contact_name' => $c->contact?->name ?? 'غير معروف',
                'contact_phone' => $c->contact?->phone ?? '',
                'latest_message' => $c->latest_message_preview ?? '',
                'status' => $c->status ?? 'new',
                'flow_state' => Schema::hasColumn('outside_conversations', 'flow_state')
                    ? ($c->flow_state ?? 'new') : 'new',
                'last_message_at' => $c->last_inbound_at?->toIso8601String() ?? $c->created_at->toIso8601String(),
                'unread_count' => $c->unread_count ?? 0,
                'bot_active' => Schema::hasColumn('outside_conversations', 'bot_active')
                    ? (bool) $c->bot_active : true,
                'intelligence_classification' => $c->intelligence_classification,
            ]);

        return response()->json($conversations);
    }

    /**
     * Get messages for a specific conversation.
     */
    public function conversationMessages(int $id): JsonResponse
    {
        $conversation = OutsideConversation::query()->findOrFail($id);

        $messages = OutsideMessage::query()
            ->where('outside_conversation_id', $id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (OutsideMessage $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'body' => $m->body,
                'message_type' => $m->message_type,
                'created_at' => $m->created_at->toIso8601String(),
                'sent_by_user_id' => $m->sent_by_user_id,
            ]);

        return response()->json($messages);
    }

    /**
     * Toggle bot active state for a conversation.
     */
    public function toggleBot(int $id, Request $request): JsonResponse
    {
        $request->validate(['active' => 'required|boolean']);

        $conversation = OutsideConversation::query()->findOrFail($id);

        if (Schema::hasColumn('outside_conversations', 'bot_active')) {
            $conversation->update(['bot_active' => $request->boolean('active')]);
        }

        return response()->json(['bot_active' => $request->boolean('active')]);
    }

    /**
     * List leads from GoodsMetaLead.
     */
    public function leads(): JsonResponse
    {
        $leads = GoodsMetaLead::query()
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (GoodsMetaLead $l) => [
                'id' => $l->id,
                'full_name' => $l->full_name,
                'phone' => $l->phone,
                'company' => null,
                'service_interest' => $l->goal_answer,
                'workflow_status' => $l->workflow_status,
                'has_whatsapp' => $l->has_whatsapp,
                'campaign_name' => $l->campaign_name,
                'created_at' => $l->created_at->toIso8601String(),
                'last_contact_date' => $l->last_contact_date?->toDateString(),
            ]);

        return response()->json($leads);
    }

    /**
     * List meetings.
     */
    public function meetings(): JsonResponse
    {
        if (! Schema::hasTable('meetings')) {
            return response()->json([]);
        }

        $meetings = Meeting::query()
            ->orderByDesc('scheduled_at')
            ->limit(100)
            ->get()
            ->map(fn (Meeting $m) => [
                'id' => $m->id,
                'lead_name' => $m->title ?? 'اجتماع',
                'lead_phone' => '',
                'scheduled_at' => $m->scheduled_at?->toIso8601String(),
                'status' => $m->status ?? 'pending',
                'notes' => $m->notes ?? '',
                'type' => $m->type ?? 'meeting',
            ]);

        return response()->json($meetings);
    }

    /**
     * Get bot settings.
     */
    public function settings(): JsonResponse
    {
        $defaults = [
            'system_prompt' => '',
            'greeting_message' => 'مرحبًا! شكرًا لتواصلكم معنا. كيف يمكننا مساعدتكم اليوم؟',
            'max_messages_per_day' => '200',
            'response_delay_seconds' => '5',
            'auto_reply_enabled' => '1',
            'working_hours_start' => '09:00',
            'working_hours_end' => '18:00',
            'fallback_message' => 'شكرًا لتواصلكم! سنرد عليكم في أقرب وقت.',
        ];

        $settings = [];
        foreach ($defaults as $key => $default) {
            $settings[$key] = $this->getSetting($key, $default);
        }

        $settings['max_messages_per_day'] = (int) $settings['max_messages_per_day'];
        $settings['response_delay_seconds'] = (int) $settings['response_delay_seconds'];
        $settings['auto_reply_enabled'] = (bool) $settings['auto_reply_enabled'];

        return response()->json($settings);
    }

    /**
     * Update bot settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'system_prompt' => 'nullable|string',
            'greeting_message' => 'nullable|string',
            'max_messages_per_day' => 'nullable|integer|min:1',
            'response_delay_seconds' => 'nullable|integer|min:0',
            'auto_reply_enabled' => 'nullable|boolean',
            'working_hours_start' => 'nullable|string',
            'working_hours_end' => 'nullable|string',
            'fallback_message' => 'nullable|string',
        ]);

        foreach ($validated as $key => $value) {
            $this->setSetting($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        return response()->json(['message' => 'تم حفظ الإعدادات']);
    }

    private function getSetting(string $key, string $default = ''): string
    {
        if (! Schema::hasTable('bot_settings')) {
            return $default;
        }

        return \App\Models\BotSetting::query()->where('key', $key)->value('value') ?? $default;
    }

    private function setSetting(string $key, string $value): void
    {
        if (! Schema::hasTable('bot_settings')) {
            return;
        }

        \App\Models\BotSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
