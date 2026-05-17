<?php

namespace App\Http\Controllers;

use App\Models\GoodsCustomer;
use App\Models\GoodsCustomerStatusHistory;
use App\Models\GoodsMetaLead;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Models\User;
use App\Services\GoodsMetaLeadAnalyticsService;
use App\Services\OutsideGoodsClientBridgeService;
use App\Services\WhatsAppCloudService;
use App\Support\GoodsMetaLeadWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GoodsCustomerController extends Controller
{
    public function __construct(
        private readonly WhatsAppCloudService $whatsAppCloudService,
        private readonly OutsideGoodsClientBridgeService $outsideGoodsBridge,
    ) {}

    public function index(Request $request): Response
    {
        $filterStatus = trim((string) $request->query('status', ''));
        $tab = trim((string) $request->query('tab', 'customers'));
        $metaFilterStatus = trim((string) $request->query('meta_status', ''));
        $metaCampaign = trim((string) $request->query('meta_campaign', ''));
        $metaSheet = trim((string) $request->query('meta_sheet', ''));

        $customers = GoodsCustomer::query()
            ->with(['owner:id,name', 'contact:id,name,phone', 'client:id,name'])
            ->when($filterStatus !== '', fn ($query) => $query->where('status', $filterStatus))
            ->orderByDesc('updated_at')
            ->get();

        $metaLeadsQuery = GoodsMetaLead::query()
            ->with(['owner:id,name'])
            ->when($metaFilterStatus !== '', fn ($q) => $q->where('workflow_status', $metaFilterStatus))
            ->when($metaCampaign !== '', fn ($q) => $q->where('campaign_name', $metaCampaign))
            ->when($metaSheet !== '', fn ($q) => $q->where('sheet_name', $metaSheet))
            ->orderByDesc('lead_created_at')
            ->orderByDesc('id');

        $metaLeads = $metaLeadsQuery->limit(500)->get();
        $campaignOptions = GoodsMetaLead::query()
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->distinct()
            ->orderBy('campaign_name')
            ->pluck('campaign_name')
            ->values()
            ->all();

        $sheetOptions = GoodsMetaLead::query()
            ->whereNotNull('sheet_name')
            ->where('sheet_name', '!=', '')
            ->distinct()
            ->orderBy('sheet_name')
            ->pluck('sheet_name')
            ->values()
            ->all();

        $metaAnalytics = app(GoodsMetaLeadAnalyticsService::class)->summary();

        return Inertia::render('Goods/Index', [
            'active_tab' => in_array($tab, ['customers', 'meta_leads'], true) ? $tab : 'customers',
            'customers' => $customers->map(fn (GoodsCustomer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'company' => $customer->company,
                'status' => $customer->status,
                'client_id' => $customer->client_id,
                'notes' => $customer->notes,
                'confirmed_at' => $customer->confirmed_at?->toIso8601String(),
                'updated_at' => $customer->updated_at?->toIso8601String(),
                'client' => $customer->client ? [
                    'id' => $customer->client->id,
                    'name' => $customer->client->name,
                ] : null,
                'owner' => $customer->owner ? [
                    'id' => $customer->owner->id,
                    'name' => $customer->owner->name,
                ] : null,
                'contact' => $customer->contact ? [
                    'id' => $customer->contact->id,
                    'name' => $customer->contact->name,
                    'phone' => $customer->contact->phone,
                ] : null,
            ])->values(),
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
            'contacts' => OutsideContact::query()
                ->whereIn('channel', ['whatsapp', 'instagram', 'messenger'])
                ->orderByDesc('updated_at')
                ->limit(200)
                ->get(['id', 'name', 'phone']),
            'status_options' => $this->statusOptions(),
            'filters' => [
                'status' => $filterStatus,
            ],
            'meta_leads' => $metaLeads->map(fn (GoodsMetaLead $lead) => [
                'id' => $lead->id,
                'meta_lead_id' => $lead->meta_lead_id,
                'sheet_name' => $lead->sheet_name,
                'full_name' => $lead->full_name,
                'phone' => $lead->phone,
                'platform' => $lead->platform,
                'campaign_name' => $lead->campaign_name,
                'adset_name' => $lead->adset_name,
                'ad_name' => $lead->ad_name,
                'monthly_orders_answer' => $lead->monthly_orders_answer,
                'goal_answer' => $lead->goal_answer,
                'team_notes' => $lead->team_notes,
                'probability_label' => $lead->probability_label,
                'reason_label' => $lead->reason_label,
                'outcome_label' => $lead->outcome_label,
                'workflow_status' => $lead->workflow_status,
                'lead_created_at' => $lead->lead_created_at?->toIso8601String(),
                'first_contact_date' => $lead->first_contact_date?->format('Y-m-d'),
                'last_contact_date' => $lead->last_contact_date?->format('Y-m-d'),
                'next_contact_date' => $lead->next_contact_date?->format('Y-m-d'),
                'owner' => $lead->owner ? ['id' => $lead->owner->id, 'name' => $lead->owner->name] : null,
            ])->values(),
            'meta_lead_status_options' => GoodsMetaLeadWorkflow::statusOptions(),
            'meta_filters' => [
                'status' => $metaFilterStatus,
                'campaign' => $metaCampaign,
                'sheet' => $metaSheet,
            ],
            'meta_campaign_options' => $campaignOptions,
            'meta_sheet_options' => $sheetOptions,
            'meta_analytics' => $metaAnalytics,
            'meta_leads_webhook_configured' => (string) config('services.goods.meta_leads_webhook_secret', '') !== '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $statusValues = collect($this->statusOptions())->pluck('value')->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'company' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', Rule::in($statusValues)],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'outside_contact_id' => ['nullable', 'exists:outside_contacts,id'],
        ]);

        $customer = GoodsCustomer::query()->create([
            'outside_contact_id' => $data['outside_contact_id'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'],
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'confirmed_at' => $data['status'] !== 'new' ? now() : null,
        ]);

        GoodsCustomerStatusHistory::query()->create([
            'goods_customer_id' => $customer->id,
            'from_status' => null,
            'to_status' => $customer->status,
            'note' => 'إنشاء سجل عميل جديد في قسم البضاعة',
            'user_id' => $request->user()?->id,
        ]);

        $this->outsideGoodsBridge->afterGoodsCustomerStatusSaved($customer->fresh(), $request->user()?->id);

        return redirect()->route('goods.index')->with('success', 'تم إنشاء عميل البضاعة بنجاح.');
    }

    public function updateStatus(Request $request, GoodsCustomer $goodsCustomer): RedirectResponse
    {
        $statusValues = collect($this->statusOptions())->pluck('value')->all();
        $data = $request->validate([
            'status' => ['required', Rule::in($statusValues)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $fromStatus = $goodsCustomer->status;
        $toStatus = $data['status'];

        if ($fromStatus === $toStatus) {
            return redirect()->route('goods.index');
        }

        $goodsCustomer->update([
            'status' => $toStatus,
            'confirmed_at' => $toStatus !== 'new' ? ($goodsCustomer->confirmed_at ?: now()) : null,
        ]);

        GoodsCustomerStatusHistory::query()->create([
            'goods_customer_id' => $goodsCustomer->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()?->id,
        ]);

        $this->outsideGoodsBridge->afterGoodsCustomerStatusSaved($goodsCustomer->fresh(), $request->user()?->id);

        return redirect()->route('goods.index')->with('success', 'تم تحديث حالة العميل.');
    }

    public function sendSalesReminder(Request $request, GoodsCustomer $goodsCustomer): RedirectResponse
    {
        $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $goodsCustomer->outside_contact_id) {
            return redirect()->route('goods.index')->withErrors([
                'goods_reminder' => 'لا يمكن إرسال تذكير لأن العميل غير مربوط بقسم الخارج.',
            ]);
        }

        $goodsCustomer->load('contact:id,phone,channel');

        if (
            $goodsCustomer->contact
            && in_array($goodsCustomer->contact->channel ?? 'whatsapp', ['instagram', 'messenger'], true)
        ) {
            return redirect()->route('goods.index')->withErrors([
                'goods_reminder' => 'تذكيرات البضاعة تُرسل عبر واتساب فقط؛ جهة هذا العميل من إنستغرام أو ماسنجر.',
            ]);
        }

        $conversation = OutsideConversation::query()->firstOrCreate([
            'outside_contact_id' => $goodsCustomer->outside_contact_id,
        ]);

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            $body = sprintf(
                'مرحبًا %s، تذكير ودي بإضافة مبيعات اليوم على المنصة حتى نكمل المتابعة.',
                $goodsCustomer->name
            );
        }

        $message = OutsideMessage::query()->create([
            'outside_conversation_id' => $conversation->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $body,
            'provider_status' => 'queued',
            'sent_by_user_id' => $request->user()?->id,
        ]);

        try {
            $response = $this->whatsAppCloudService->sendText(
                (string) $goodsCustomer->contact?->phone,
                $body
            );
            $message->update([
                'external_message_id' => (string) data_get($response, 'messages.0.id', ''),
                'provider_status' => 'sent',
                'provider_error' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $message->update([
                'provider_status' => 'failed',
                'provider_error' => $exception->getMessage(),
                'retry_count' => 1,
            ]);
        }

        $conversation->update([
            'latest_message_preview' => mb_substr($body, 0, 120),
            'last_outbound_at' => $message->created_at,
            'updated_at' => $message->created_at,
        ]);

        DB::table('outside_reminder_logs')->insert([
            'goods_customer_id' => $goodsCustomer->id,
            'outside_conversation_id' => $conversation->id,
            'sent_by_user_id' => $request->user()?->id,
            'reminder_type' => 'daily_sales',
            'body' => $body,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('goods.index')->with('success', 'تم إرسال تذكير المبيعات.');
    }

    public function sendWeeklySurvey(Request $request, GoodsCustomer $goodsCustomer): RedirectResponse
    {
        if (! $goodsCustomer->outside_contact_id) {
            return redirect()->route('goods.index')->withErrors([
                'goods_reminder' => 'لا يمكن إرسال الاستبيان لأن العميل غير مربوط بقسم الخارج.',
            ]);
        }

        $goodsCustomer->load('contact:id,phone,channel');

        if (
            $goodsCustomer->contact
            && in_array($goodsCustomer->contact->channel ?? 'whatsapp', ['instagram', 'messenger'], true)
        ) {
            return redirect()->route('goods.index')->withErrors([
                'goods_reminder' => 'الاستبيان يُرسل عبر واتساب فقط.',
            ]);
        }

        $conversation = OutsideConversation::query()->firstOrCreate([
            'outside_contact_id' => $goodsCustomer->outside_contact_id,
        ]);

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            $body = sprintf(
                'مرحبًا %s، نرجو تقييم أداء الأسبوع الحالي والرد على الاستبيان المختصر: 1) رضاك العام؟ 2) أبرز ملاحظة؟',
                $goodsCustomer->name
            );
        }

        $message = OutsideMessage::query()->create([
            'outside_conversation_id' => $conversation->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $body,
            'provider_status' => 'queued',
            'sent_by_user_id' => $request->user()?->id,
        ]);

        try {
            $response = $this->whatsAppCloudService->sendText(
                (string) $goodsCustomer->contact?->phone,
                $body
            );
            $message->update([
                'external_message_id' => (string) data_get($response, 'messages.0.id', ''),
                'provider_status' => 'sent',
                'provider_error' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $message->update([
                'provider_status' => 'failed',
                'provider_error' => $exception->getMessage(),
                'retry_count' => 1,
            ]);
        }

        $conversation->update([
            'latest_message_preview' => mb_substr($body, 0, 120),
            'last_outbound_at' => $message->created_at,
            'updated_at' => $message->created_at,
        ]);

        DB::table('outside_reminder_logs')->insert([
            'goods_customer_id' => $goodsCustomer->id,
            'outside_conversation_id' => $conversation->id,
            'sent_by_user_id' => $request->user()?->id,
            'reminder_type' => 'weekly_survey',
            'body' => $body,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('goods.index')->with('success', 'تم إرسال الاستبيان الأسبوعي.');
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'new', 'label' => 'جديد'],
            ['value' => 'potential', 'label' => 'عميل محتمل'],
            ['value' => 'unlikely', 'label' => 'عميل غير محتمل'],
            ['value' => 'qualified', 'label' => 'مؤهل'],
            ['value' => 'active', 'label' => 'عميل مؤكد'],
            ['value' => 'paused', 'label' => 'متوقف'],
            ['value' => 'lost', 'label' => 'مفقود'],
        ];
    }
}
