<?php

namespace App\Services;

use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ConversationFlowService
{
    public const STATE_NEW = 'new';
    public const STATE_GREETING_SENT = 'greeting_sent';
    public const STATE_QUALIFYING = 'qualifying';
    public const STATE_PITCHING = 'pitching';
    public const STATE_MEETING_OFFERED = 'meeting_offered';
    public const STATE_MEETING_BOOKED = 'meeting_booked';
    public const STATE_HANDOFF_TO_HUMAN = 'handoff_to_human';
    public const STATE_WON = 'won';
    public const STATE_LOST = 'lost';

    private AiChatbotService $aiService;
    private WhatsAppCloudService $whatsappService;

    public function __construct(AiChatbotService $aiService, WhatsAppCloudService $whatsappService)
    {
        $this->aiService = $aiService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Process an inbound message and generate an AI reply if bot is active.
     */
    public function handleInboundMessage(OutsideConversation $conversation, string $messageBody): ?string
    {
        if (! $this->shouldBotReply($conversation)) {
            return null;
        }

        $this->ensureFlowState($conversation);

        $history = $this->getConversationHistory($conversation);
        $leadData = $this->getLeadData($conversation);

        try {
            $result = $this->aiService->generateReply(
                $history,
                $leadData,
                $this->getSystemPrompt($conversation),
            );

            $reply = $result['reply'];
            $confidence = $result['confidence'];

            $this->sendReply($conversation, $reply);
            $this->updateFlowState($conversation, $messageBody, $reply);
            $this->logInteraction($conversation, $messageBody, $reply, $confidence);

            return $reply;
        } catch (\Throwable $e) {
            Log::error('Bot reply failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $fallback = $this->getFallbackMessage($conversation);
            $this->sendReply($conversation, $fallback);

            return $fallback;
        }
    }

    public function getFlowState(OutsideConversation $conversation): string
    {
        if (Schema::hasColumn('outside_conversations', 'flow_state')) {
            return (string) ($conversation->flow_state ?? self::STATE_NEW);
        }
        return self::STATE_NEW;
    }

    public function setFlowState(OutsideConversation $conversation, string $state): void
    {
        if (Schema::hasColumn('outside_conversations', 'flow_state')) {
            $conversation->update(['flow_state' => $state]);
        }
    }

    private function shouldBotReply(OutsideConversation $conversation): bool
    {
        if (! Schema::hasColumn('outside_conversations', 'bot_active')) {
            return true;
        }

        return (bool) ($conversation->bot_active ?? true);
    }

    private function ensureFlowState(OutsideConversation $conversation): void
    {
        if (Schema::hasColumn('outside_conversations', 'flow_state') && empty($conversation->flow_state)) {
            $conversation->update(['flow_state' => self::STATE_NEW]);
        }
    }

    private function getConversationHistory(OutsideConversation $conversation): array
    {
        return OutsideMessage::query()
            ->where('outside_conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->limit(30)
            ->get()
            ->map(fn (OutsideMessage $m) => [
                'role' => $m->direction,
                'body' => (string) $m->body,
            ])
            ->toArray();
    }

    private function getLeadData(OutsideConversation $conversation): array
    {
        $contact = $conversation->contact;
        if (! $contact) {
            return [];
        }

        $data = [
            'full_name' => $contact->name,
            'phone' => $contact->phone,
        ];

        $goodsCustomer = $contact->goodsCustomers()->first();
        if ($goodsCustomer) {
            $data['company'] = $goodsCustomer->company;
            $data['status'] = $goodsCustomer->status;
        }

        if (Schema::hasColumn('outside_conversations', 'flow_state')) {
            $data['flow_state'] = $conversation->flow_state;
        }

        return $data;
    }

    private function getSystemPrompt(OutsideConversation $conversation): string
    {
        if (Schema::hasColumn('outside_conversations', 'bot_system_prompt_override') && ! empty($conversation->bot_system_prompt_override)) {
            return $conversation->bot_system_prompt_override;
        }
        return '';
    }

    private function getFallbackMessage(OutsideConversation $conversation): string
    {
        return 'شكرًا لتواصلكم! سنرد عليكم في أقرب وقت. هل ترغب في حجز اجتماع لمناقشة احتياجاتكم؟';
    }

    private function sendReply(OutsideConversation $conversation, string $body): void
    {
        $phone = $conversation->contact?->phone;
        if (! $phone || $conversation->contact?->channel !== 'whatsapp') {
            return;
        }

        try {
            $this->whatsappService->sendText($phone, $body);

            $contact = $conversation->contact;
            if ($contact && Schema::hasColumn('outside_conversations', 'bot_last_reply_at')) {
                $conversation->update([
                    'bot_last_reply_at' => now(),
                    'bot_message_count' => ($conversation->bot_message_count ?? 0) + 1,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send bot reply via WhatsApp', [
                'conversation_id' => $conversation->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function updateFlowState(OutsideConversation $conversation, string $inboundBody, string $outboundBody): void
    {
        $state = $this->getFlowState($conversation);
        $lowerInbound = mb_strtolower($inboundBody);
        $lowerOutbound = mb_strtolower($outboundBody);

        $newState = match (true) {
            str_contains($lowerOutbound, 'موعد') || str_contains($lowerOutbound, 'اجتماع') => self::STATE_MEETING_OFFERED,
            str_contains($lowerInbound, 'موافق') || str_contains($lowerInbound, 'تمام') || str_contains($lowerInbound, 'أيوه') => self::STATE_MEETING_BOOKED,
            str_contains($lowerInbound, 'لا أريد') || str_contains($lowerInbound, 'إلغاء') || str_contains($lowerInbound, 'توقف') => self::STATE_LOST,
            $state === self::STATE_NEW => self::STATE_GREETING_SENT,
            $state === self::STATE_GREETING_SENT => self::STATE_QUALIFYING,
            $state === self::STATE_QUALIFYING => self::STATE_PITCHING,
            default => $state,
        };

        if ($newState !== $state) {
            $this->setFlowState($conversation, $newState);
        }
    }

    private function logInteraction(
        OutsideConversation $conversation,
        string $inboundBody,
        string $outboundBody,
        ?float $confidence,
    ): void {
        if (! Schema::hasTable('bot_interaction_logs')) {
            return;
        }

        \App\Models\BotInteractionLog::query()->create([
            'outside_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'message_body' => $outboundBody,
            'message_type' => 'text',
            'ai_context' => [
                'inbound_body' => $inboundBody,
                'flow_state' => $this->getFlowState($conversation),
            ],
            'ai_confidence' => $confidence,
        ]);
    }
}
