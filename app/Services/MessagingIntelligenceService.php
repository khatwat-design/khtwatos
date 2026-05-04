<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCampaignUpdate;
use App\Models\ClientMetaIntegration;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Models\Team;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Rule-based messaging intelligence for Outside (WhatsApp + Instagram).
 * Does not send messages — only enriches conversation rows for the team UI.
 */
class MessagingIntelligenceService
{
    public const CLASS_LEAD = 'lead';

    public const CLASS_EXISTING_CLIENT = 'existing_client';

    public const CLASS_SUPPORT = 'support';

    public const CLASS_SPAM = 'spam';

    private const SUMMARY_MIN_INTERVAL_MINUTES = 40;

    private const SUMMARY_MIN_NEW_INBOUND = 12;

    /**
     * Run after a new inbound message is persisted (WhatsApp or Instagram).
     */
    public function refreshAfterInbound(OutsideConversation $conversation): void
    {
        if (! Schema::hasColumn('outside_conversations', 'intelligence_classification')) {
            return;
        }

        $conversation->loadMissing(['contact.client']);
        $contact = $conversation->contact;
        if (! $contact instanceof OutsideContact) {
            return;
        }

        $channel = $this->normalizeChannel($contact->channel);
        $textBlob = $this->collectInboundText($conversation);
        $classification = $this->classify($textBlob, $contact);

        $inboundCount = OutsideMessage::query()
            ->where('outside_conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->count();

        $shouldSummary = $this->shouldRefreshSummary($conversation, $inboundCount);
        $summary = $shouldSummary ? $this->buildSummary($conversation, $contact, $classification, $textBlob) : $conversation->intelligence_summary;
        $summaryAt = $shouldSummary ? now() : $conversation->intelligence_summary_at;
        $summaryInboundBaseline = $shouldSummary ? $inboundCount : (int) $conversation->intelligence_summary_inbound_count;

        $clientContext = $classification === self::CLASS_EXISTING_CLIENT && $contact->client_id
            ? $this->buildClientContext((int) $contact->client_id)
            : null;

        $suggested = $classification === self::CLASS_SPAM
            ? []
            : $this->buildSuggestedReplies($classification, $channel, $textBlob, $clientContext);

        $routing = $classification === self::CLASS_SPAM
            ? null
            : $this->buildRoutingSuggestion($classification, $contact);

        $conversation->update([
            'intelligence_classification' => $classification,
            'intelligence_classification_at' => now(),
            'intelligence_summary' => $summary,
            'intelligence_summary_at' => $summaryAt,
            'intelligence_summary_inbound_count' => $summaryInboundBaseline,
            'intelligence_suggested_replies' => $suggested,
            'intelligence_suggested_at' => now(),
            'intelligence_routing' => $routing,
            'intelligence_client_context' => $clientContext,
        ]);
    }

    public function classificationLabelAr(?string $code): string
    {
        return match ($code) {
            self::CLASS_LEAD => 'عميل محتمل',
            self::CLASS_EXISTING_CLIENT => 'عميل حالي',
            self::CLASS_SUPPORT => 'دعم',
            self::CLASS_SPAM => 'مزعج / غير مرغوب',
            default => 'غير مصنّف',
        };
    }

    private function normalizeChannel(?string $channel): string
    {
        $c = strtolower(trim((string) $channel));

        return $c === 'instagram' ? 'instagram' : 'whatsapp';
    }

    private function collectInboundText(OutsideConversation $conversation): string
    {
        $parts = OutsideMessage::query()
            ->where('outside_conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->orderByDesc('id')
            ->limit(18)
            ->pluck('body')
            ->filter()
            ->map(fn ($b) => is_string($b) ? trim($b) : '')
            ->filter()
            ->implode("\n");

        $preview = (string) ($conversation->latest_message_preview ?? '');

        return Str::limit(trim($parts."\n".$preview), 2000, '');
    }

    private function classify(string $text, OutsideContact $contact): string
    {
        $lower = mb_strtolower($text);

        if ($this->matchesSpam($lower)) {
            return self::CLASS_SPAM;
        }

        if ($this->matchesSupport($lower)) {
            return self::CLASS_SUPPORT;
        }

        if ($contact->client_id) {
            if ($this->matchesLeadIntent($lower)) {
                return self::CLASS_LEAD;
            }

            return self::CLASS_EXISTING_CLIENT;
        }

        return self::CLASS_LEAD;
    }

    private function matchesSpam(string $lower): bool
    {
        $needles = [
            'http://bit.ly', 'https://bit.ly', 'click here', 'free money', 'win $',
            'casino', 'viagra', 'crypto airdrop', 'invest now', 'urgent payment',
            'congratulations you won', 'claim your prize', 'زيادة متابعين', 'ربح سريع',
            'اضغط الرابط', 'عرض حصري لفترة محدودة فقط من شركة غير معروفة',
        ];

        foreach ($needles as $n) {
            if (str_contains($lower, mb_strtolower($n))) {
                return true;
            }
        }

        if (preg_match_all('/https?:\/\//i', $lower) >= 3) {
            return true;
        }

        return false;
    }

    private function matchesSupport(string $lower): bool
    {
        $needles = [
            'مشكلة', 'لا يعمل', 'خطأ', 'دعم', 'مساعدة', 'استفسار', 'شكوى', 'استرجاع', 'مرتجع',
            'refund', 'issue', 'problem', 'help', 'support', 'not working', 'bug',
            'تأخر', 'لم يصل', 'لا استطيع', 'معطل',
        ];

        foreach ($needles as $n) {
            if (str_contains($lower, mb_strtolower($n))) {
                return true;
            }
        }

        return false;
    }

    private function matchesLeadIntent(string $lower): bool
    {
        $needles = [
            'سعر', 'أسعار', 'عرض', 'عرض سعر', 'quote', 'proposal', 'اقتراح',
            'أريد', 'اريد', 'مهتم', 'interested', 'استشارة', 'موعد', 'اجتماع',
            'خدماتكم', 'باقة', 'package',
        ];

        foreach ($needles as $n) {
            if (str_contains($lower, mb_strtolower($n))) {
                return true;
            }
        }

        return false;
    }

    private function shouldRefreshSummary(OutsideConversation $conversation, int $inboundCount): bool
    {
        if (! $conversation->intelligence_summary || ! $conversation->intelligence_summary_at) {
            return true;
        }

        if ($conversation->intelligence_summary_at->lt(now()->subMinutes(self::SUMMARY_MIN_INTERVAL_MINUTES))) {
            return true;
        }

        $since = $inboundCount - (int) $conversation->intelligence_summary_inbound_count;

        return $since >= self::SUMMARY_MIN_NEW_INBOUND;
    }

    private function buildSummary(
        OutsideConversation $conversation,
        OutsideContact $contact,
        string $classification,
        string $textBlob,
    ): string {
        $snippet = Str::limit(trim(Str::squish($textBlob)), 220, '…');
        $who = $contact->name ?: ($contact->phone ?: 'جهة اتصال');
        $type = $this->classificationLabelAr($classification);

        $tail = match ($classification) {
            self::CLASS_SPAM => 'قد تكون رسائل ترويجية أو غير مرغوبة.',
            self::CLASS_SUPPORT => 'يبدو أنه طلب مساعدة أو متابعة مشكلة.',
            self::CLASS_EXISTING_CLIENT => 'مرتبط بحساب عميل في النظام.',
            default => 'جهة جديدة أو استفسار أولي.',
        };

        return "ملخص سريع: {$who} — تصنيف: {$type}. آخر ما ورد: «{$snippet}» — {$tail}";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildClientContext(int $clientId): ?array
    {
        if (! Schema::hasTable('clients')) {
            return null;
        }

        $client = Client::query()->find($clientId);
        if (! $client) {
            return null;
        }

        $integration = Schema::hasTable('client_meta_integrations')
            ? ClientMetaIntegration::query()->where('client_id', $clientId)->first()
            : null;

        $recent = Schema::hasTable('client_campaign_updates')
            ? ClientCampaignUpdate::query()
                ->where('client_id', $clientId)
                ->orderByDesc('report_date')
                ->limit(5)
                ->get(['report_date', 'ad_spend', 'roas', 'messages_count'])
            : collect();

        $lines = [];
        $lines[] = 'العميل: '.$client->name;
        if ($integration) {
            $lines[] = 'إعداد الحملات: '.($integration->setup_status ?: '—');
            if ($integration->ad_account_id) {
                $lines[] = 'حساب إعلاني: '.$integration->ad_account_id;
            }
        }
        if ($recent->isNotEmpty()) {
            $last = $recent->first();
            $lines[] = 'آخر يوم بيانات: '.($last->report_date?->toDateString() ?? '—')
                .' — إنفاق: '.(string) ($last->ad_spend ?? 0)
                .' — ROAS: '.($last->roas !== null ? (string) $last->roas : '—');
        }

        return [
            'headline' => $client->name,
            'lines' => $lines,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $clientContext
     * @return list<string>
     */
    private function buildSuggestedReplies(string $classification, string $channel, string $textBlob, ?array $clientContext): array
    {
        $chLabel = $channel === 'instagram' ? 'إنستغرام' : 'واتساب';

        $base = match ($classification) {
            self::CLASS_SUPPORT => [
                'شكرًا لإبلاغنا، فريقنا يراجع الموضوع ويعود إليكم بأقرب وقت.',
                'هل يمكنك إرسال لقطة شاشة أو تفاصيل إضافية تساعدنا على المتابعة؟',
                'نعتذر عن الإزعاج — سيتابع معك المسؤول عن الحساب.',
            ],
            self::CLASS_EXISTING_CLIENT => [
                'شكرًا لمشاركتكم، تم الاطلاع وسنعود إليكم بخلاصة قصيرة.',
                'تم تسجيل ملاحظتكم، هل لديكم أي توجيه إضافي قبل أن نتابع؟',
                'نؤكد استلام رسالتكم وسنوافيكم بالمستجدات.',
            ],
            default => [
                "مرحبًا، شكرًا لتواصلكم عبر {$chLabel}. كيف يمكننا مساعدتكم اليوم؟",
                'يسعدنا جدًا أن نسمع منكم — هل ترغبون بجدولة اجتماع سريع لمناقشة احتياجاتكم؟',
                'يمكننا إرسال تفاصيل إضافية أو عرض مناسب بعد أن نفهم طلبكم أكثر.',
            ],
        };

        if ($classification === self::CLASS_EXISTING_CLIENT && $clientContext && ! empty($clientContext['lines'])) {
            $base[0] = 'شكرًا لمشاركتكم. لاحظنا أن الحساب مرتبط بملفكم — سنوافيكم بعد مراجعة أداء الحملة.';
        }

        return array_slice(array_values(array_unique($base)), 0, 3);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRoutingSuggestion(string $classification, OutsideContact $contact): ?array
    {
        if ($classification === self::CLASS_SUPPORT && $contact->client_id && Schema::hasColumn('clients', 'account_manager_id')) {
            $client = Client::query()->find((int) $contact->client_id);
            $amId = $client?->account_manager_id ? (int) $client->account_manager_id : null;
            if ($amId) {
                return [
                    'target' => 'account_manager',
                    'team_name' => null,
                    'hint_ar' => 'يُنصح بتوجيه المحادثة إلى مدير الحساب المرتبط بالعميل — اختر التعيين يدويًا بعد التأكد.',
                    'preferred_user_id' => $amId,
                    'suggested_user_ids' => [$amId],
                ];
            }
        }

        $slug = match ($classification) {
            self::CLASS_LEAD => 'sales',
            self::CLASS_SUPPORT, self::CLASS_EXISTING_CLIENT => 'media-buyer',
            default => 'sales',
        };

        $team = Team::query()->where('slug', $slug)->first();
        $userIds = $team ? $team->users()->orderBy('name')->limit(8)->pluck('id')->map(fn ($id) => (int) $id)->values()->all() : [];

        $hint = match ($classification) {
            self::CLASS_LEAD => 'يُنصح بمراجعة فريق المبيعات — اختر الموظف المسؤول من القائمة أعلاه بعد التأكد.',
            self::CLASS_SUPPORT => 'يُنصح بإشراك فريق التشغيل/الحملات أو الدعم — راجع التعيين يدويًا.',
            self::CLASS_EXISTING_CLIENT => 'محادثة عميل حالي — يُفضّل متابعة مدير الحملة أو الفريق الفني.',
            default => '',
        };

        return [
            'target' => $slug,
            'team_name' => $team?->name,
            'hint_ar' => $hint,
            'preferred_user_id' => null,
            'suggested_user_ids' => $userIds,
        ];
    }
}
