<?php

namespace App\Http\Controllers;

use App\Models\GoodsCustomer;
use App\Models\GoodsCustomerStatusHistory;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Services\WhatsAppCloudService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GoodsCustomerController extends Controller
{
    public function __construct(
        private readonly WhatsAppCloudService $whatsAppCloudService
    ) {
    }

    public function index(Request $request): Response
    {
        $filterStatus = trim((string) $request->query('status', ''));

        $customers = GoodsCustomer::query()
            ->with(['owner:id,name', 'contact:id,name,phone'])
            ->when($filterStatus !== '', fn ($query) => $query->where('status', $filterStatus))
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Goods/Index', [
            'customers' => $customers->map(fn (GoodsCustomer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'company' => $customer->company,
                'status' => $customer->status,
                'notes' => $customer->notes,
                'confirmed_at' => $customer->confirmed_at?->toIso8601String(),
                'updated_at' => $customer->updated_at?->toIso8601String(),
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
                ->orderByDesc('updated_at')
                ->limit(200)
                ->get(['id', 'name', 'phone']),
            'status_options' => $this->statusOptions(),
            'filters' => [
                'status' => $filterStatus,
            ],
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
            'confirmed_at' => $data['status'] !== 'lead' ? now() : null,
        ]);

        GoodsCustomerStatusHistory::query()->create([
            'goods_customer_id' => $customer->id,
            'from_status' => null,
            'to_status' => $customer->status,
            'note' => 'إنشاء سجل عميل جديد في قسم البضاعة',
            'user_id' => $request->user()?->id,
        ]);

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
            'confirmed_at' => $toStatus !== 'lead' ? ($goodsCustomer->confirmed_at ?: now()) : null,
        ]);

        GoodsCustomerStatusHistory::query()->create([
            'goods_customer_id' => $goodsCustomer->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->route('goods.index')->with('success', 'تم تحديث حالة العميل.');
    }

    public function sendSalesReminder(Request $request, GoodsCustomer $goodsCustomer): RedirectResponse
    {
        $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!$goodsCustomer->outside_contact_id) {
            return redirect()->route('goods.index')->withErrors([
                'goods_reminder' => 'لا يمكن إرسال تذكير لأن العميل غير مربوط بقسم الخارج.',
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
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $body,
            'provider_status' => 'queued',
            'sent_by_user_id' => $request->user()?->id,
        ]);

        try {
            $response = $this->whatsAppCloudService->sendText(
                (string) $conversation->contact?->phone,
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
        if (!$goodsCustomer->outside_contact_id) {
            return redirect()->route('goods.index')->withErrors([
                'goods_reminder' => 'لا يمكن إرسال الاستبيان لأن العميل غير مربوط بقسم الخارج.',
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
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $body,
            'provider_status' => 'queued',
            'sent_by_user_id' => $request->user()?->id,
        ]);

        try {
            $response = $this->whatsAppCloudService->sendText(
                (string) $conversation->contact?->phone,
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
            ['value' => 'lead', 'label' => 'عميل محتمل'],
            ['value' => 'prospect', 'label' => 'قيد المتابعة'],
            ['value' => 'active', 'label' => 'عميل مؤكد'],
            ['value' => 'paused', 'label' => 'متوقف'],
            ['value' => 'lost', 'label' => 'مفقود'],
        ];
    }
}

