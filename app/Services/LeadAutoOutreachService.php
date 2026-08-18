<?php

namespace App\Services;

use App\Models\GoodsMetaLead;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LeadAutoOutreachService
{
    private WhatsAppCloudService $whatsappService;
    private ConversationFlowService $flowService;

    public function __construct(WhatsAppCloudService $whatsappService, ConversationFlowService $flowService)
    {
        $this->whatsappService = $whatsappService;
        $this->flowService = $flowService;
    }

    /**
     * Send an automated greeting to a newly synced lead if they have WhatsApp.
     * Called from GoodsMetaLeadSyncService after a lead is created.
     */
    public function sendGreetingToNewLead(GoodsMetaLead $lead): bool
    {
        if (! $lead->has_whatsapp || empty($lead->phone)) {
            return false;
        }

        $phone = $this->normalizePhone($lead->phone);
        if ($phone === '') {
            return false;
        }

        // Don't send if already contacted
        if ($lead->first_contact_date !== null) {
            return false;
        }

        // Don't send if opt-out
        if (in_array($lead->workflow_status, ['lost', 'rejected'], true)) {
            return false;
        }

        $greeting = $this->buildGreeting($lead);

        try {
            $this->whatsappService->sendText($phone, $greeting);

            $lead->update([
                'first_contact_date' => now()->toDateString(),
                'last_contact_date' => now()->toDateString(),
                'workflow_status' => 'following',
            ]);

            Log::info('lead.auto_greeting.sent', [
                'lead_id' => $lead->id,
                'phone' => $phone,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('lead.auto_greeting.failed', [
                'lead_id' => $lead->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send greeting to all eligible new leads (batch job).
     */
    public function processNewLeads(): int
    {
        $leads = GoodsMetaLead::query()
            ->where('workflow_status', 'new')
            ->where('has_whatsapp', true)
            ->whereNotNull('phone')
            ->whereNull('first_contact_date')
            ->limit(50)
            ->get();

        $sent = 0;
        foreach ($leads as $lead) {
            if ($this->sendGreetingToNewLead($lead)) {
                $sent++;
            }

            // Rate limit: 1 second between messages
            usleep(1000000);
        }

        return $sent;
    }

    private function buildGreeting(GoodsMetaLead $lead): string
    {
        $name = $lead->full_name ?? '';
        $firstName = $name !== '' ? explode(' ', trim($name))[0] : '';

        $greeting = 'مرحبًا' . ($firstName !== '' ? ' '.$firstName : '').'! 👋'."\n\n";
        $greeting .= 'شكرًا لتواصلكم معنا. ';

        if (! empty($lead->campaign_name)) {
            $greeting .= 'لحظنا اهتمامكم بحملتنا الإعلانية. ';
        }

        $greeting .= 'نحن متخصصون في تطوير المواقع الإلكترونية والتطبيقات والحلول التقنية المخصصة.'."\n\n";
        $greeting .= 'كيف يمكننا مساعدتكم اليوم؟';

        return $greeting;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone) ?: '';
    }
}
