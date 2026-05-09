<?php

namespace App\Http\Controllers;

use App\Models\ClientMetaIntegration;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Models\User;
use App\Services\ClientMetaConnectionService;
use App\Services\InstagramGraphMessagingService;
use App\Services\MessagingIntelligenceService;
use App\Services\MessengerGraphMessagingService;
use App\Services\OutsideGoodsClientBridgeService;
use App\Services\WhatsAppCloudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OutsideController extends Controller
{
    public function __construct(
        private readonly WhatsAppCloudService $whatsAppCloudService,
        private readonly InstagramGraphMessagingService $instagramMessaging,
        private readonly MessengerGraphMessagingService $messengerMessaging,
        private readonly OutsideGoodsClientBridgeService $outsideGoodsBridge,
        private readonly ClientMetaConnectionService $clientMetaConnection,
        private readonly MessagingIntelligenceService $messagingIntelligence,
    ) {}

    /** @var list<string> */
    private const CONVERSATION_STATUSES = ['new', 'potential', 'unlikely', 'qualified', 'closed'];

    public function index(): Response
    {
        $conversations = OutsideConversation::query()
            ->with([
                'contact:id,name,phone,channel,instagram_psid,messenger_psid,client_id,last_message_at,assigned_user_id',
                'contact.assignedUser:id,name',
                'messages' => fn ($query) => $query->limit(80),
            ])
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Outside/Index', [
            'can_delete_outside_contacts' => (bool) auth()->user()?->isAdmin(),
            'conversations' => $conversations->map(fn (OutsideConversation $conversation) => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'latest_message_preview' => $conversation->latest_message_preview,
                'unread_count' => $conversation->unread_count,
                'last_inbound_at' => $conversation->last_inbound_at?->toIso8601String(),
                'last_outbound_at' => $conversation->last_outbound_at?->toIso8601String(),
                'updated_at' => $conversation->updated_at?->toIso8601String(),
                'contact' => [
                    'id' => $conversation->contact?->id,
                    'name' => $conversation->contact?->name,
                    'phone' => $conversation->contact?->phone,
                    'channel' => $this->normalizedOutsideChannel($conversation->contact?->channel),
                    'instagram_psid' => $conversation->contact?->instagram_psid,
                    'messenger_psid' => $conversation->contact?->messenger_psid,
                    'client_id' => $conversation->contact?->client_id,
                    'assigned_user_id' => $conversation->contact?->assigned_user_id,
                    'assigned_user' => $conversation->contact?->assignedUser ? [
                        'id' => $conversation->contact->assignedUser->id,
                        'name' => $conversation->contact->assignedUser->name,
                    ] : null,
                ],
                'messages' => $conversation->messages->reverse()->values()->map(fn (OutsideMessage $message) => [
                    'id' => $message->id,
                    'channel' => $this->normalizedOutsideChannel($message->channel ?? $conversation->contact?->channel),
                    'direction' => $message->direction,
                    'body' => $message->body,
                    'provider_status' => $message->provider_status,
                    'provider_error' => $message->provider_error,
                    'retry_count' => $message->retry_count,
                    'created_at' => $message->created_at?->toIso8601String(),
                ]),
                'intelligence' => $this->conversationIntelligencePayload($conversation),
            ])->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'conversation_statuses' => [
                ['value' => 'new', 'label' => 'جديد'],
                ['value' => 'potential', 'label' => 'عميل محتمل'],
                ['value' => 'unlikely', 'label' => 'عميل غير محتمل'],
                ['value' => 'qualified', 'label' => 'مؤهل'],
                ['value' => 'closed', 'label' => 'مغلقة'],
            ],
        ]);
    }

    public function destroyContact(Request $request, OutsideContact $outsideContact): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $outsideContact->delete();

        return redirect()->route('outside.index')->with('success', 'تم حذف جهة الاتصال والمحادثة المرتبطة.');
    }

    public function markConversationRead(Request $request, OutsideConversation $outsideConversation): RedirectResponse
    {
        $outsideConversation->forceFill(['unread_count' => 0])->save();

        return redirect()->route('outside.index');
    }

    public function dismissIntelligenceRouting(OutsideConversation $outsideConversation): RedirectResponse
    {
        if (Schema::hasColumn('outside_conversations', 'intelligence_routing')) {
            $outsideConversation->update(['intelligence_routing' => null]);
        }

        return redirect()->route('outside.index')->with('success', 'تم إخفاء اقتراح التوجيه.');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
        ]);

        /** رقم موحّد (أرقام فقط) مثل الويب هوك حتى لا تُنشأ جهة مكررة لنفس الرقم */
        $phoneDigits = preg_replace('/\D+/', '', trim((string) $data['phone'])) ?: '';
        if ($phoneDigits === '') {
            return redirect()->route('outside.index')->withErrors([
                'phone' => 'رقم واتساب غير صالح.',
            ]);
        }

        $contact = OutsideContact::query()->firstOrCreate(
            ['phone' => $phoneDigits],
            [
                'name' => $data['name'] ?: null,
                'channel' => 'whatsapp',
            ]
        );

        if (! $contact->name && ! empty($data['name'])) {
            $contact->update(['name' => $data['name']]);
        }

        OutsideConversation::query()->firstOrCreate([
            'outside_contact_id' => $contact->id,
        ]);

        return redirect()->route('outside.index')->with('success', 'تم إنشاء جهة التواصل بنجاح.');
    }

    public function storeMessage(Request $request, OutsideConversation $outsideConversation): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $body = trim((string) $data['body']);

        $contact = $outsideConversation->contact;
        $channel = $this->normalizedOutsideChannel($contact?->channel ?? 'whatsapp');

        $message = $outsideConversation->messages()->create([
            'channel' => $channel,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $body,
            'provider_status' => 'queued',
            'sent_by_user_id' => $request->user()?->id,
        ]);

        try {
            if ($channel === 'instagram') {
                [$igBiz, $token, $psid] = $this->instagramSendContext($contact);
                $response = $this->instagramMessaging->sendText($igBiz, $token, $psid, $body);
                $message->update([
                    'external_message_id' => (string) data_get($response, 'message_id', ''),
                    'provider_status' => 'sent',
                    'provider_error' => null,
                    'sent_at' => now(),
                ]);
            } elseif ($channel === 'messenger') {
                [$pageId, $token, $psid] = $this->messengerSendContext($contact);
                $response = $this->messengerMessaging->sendText($pageId, $token, $psid, $body);
                $message->update([
                    'external_message_id' => (string) data_get($response, 'message_id', ''),
                    'provider_status' => 'sent',
                    'provider_error' => null,
                    'sent_at' => now(),
                ]);
            } else {
                $response = $this->whatsAppCloudService->sendText(
                    (string) $contact?->phone,
                    $body
                );
                $message->update([
                    'external_message_id' => (string) data_get($response, 'messages.0.id', ''),
                    'provider_status' => 'sent',
                    'provider_error' => null,
                    'sent_at' => now(),
                ]);
            }
        } catch (\Throwable $exception) {
            $message->update([
                'provider_status' => 'failed',
                'provider_error' => $exception->getMessage(),
                'retry_count' => 1,
            ]);
        }

        $outsideConversation->update([
            'latest_message_preview' => mb_substr($body, 0, 120),
            'last_outbound_at' => $message->created_at,
            'updated_at' => $message->created_at,
        ]);

        $outsideConversation->contact?->update([
            'last_message_at' => $message->created_at,
        ]);

        return redirect()->route('outside.index');
    }

    public function updateConversation(Request $request, OutsideConversation $outsideConversation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(self::CONVERSATION_STATUSES)],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        if (array_key_exists('status', $data) && $data['status']) {
            $outsideConversation->status = $data['status'];
        }

        if (array_key_exists('assigned_user_id', $data)) {
            $outsideConversation->contact?->update([
                'assigned_user_id' => $data['assigned_user_id'] ?: null,
            ]);
        }

        $statusDirty = $outsideConversation->isDirty('status');
        $outsideConversation->save();

        if ($statusDirty && array_key_exists('status', $data) && $data['status']) {
            $this->outsideGoodsBridge->afterOutsideConversationSaved($outsideConversation->fresh(), $request->user()?->id);
        }

        return redirect()->route('outside.index')->with('success', 'تم تحديث بيانات المحادثة.');
    }

    public function retryMessage(OutsideMessage $outsideMessage): RedirectResponse
    {
        if ($outsideMessage->direction !== 'outbound') {
            return redirect()->route('outside.index');
        }

        $contact = $outsideMessage->conversation?->contact;
        $channel = $this->normalizedOutsideChannel($outsideMessage->channel ?? $contact?->channel ?? 'whatsapp');

        try {
            if ($channel === 'instagram') {
                [$igBiz, $token, $psid] = $this->instagramSendContext($contact);
                $response = $this->instagramMessaging->sendText(
                    $igBiz,
                    $token,
                    $psid,
                    (string) $outsideMessage->body
                );
                $outsideMessage->update([
                    'external_message_id' => (string) data_get($response, 'message_id', $outsideMessage->external_message_id),
                    'provider_status' => 'sent',
                    'provider_error' => null,
                    'retry_count' => (int) $outsideMessage->retry_count + 1,
                    'sent_at' => now(),
                ]);
            } elseif ($channel === 'messenger') {
                [$pageId, $token, $psid] = $this->messengerSendContext($contact);
                $response = $this->messengerMessaging->sendText(
                    $pageId,
                    $token,
                    $psid,
                    (string) $outsideMessage->body
                );
                $outsideMessage->update([
                    'external_message_id' => (string) data_get($response, 'message_id', $outsideMessage->external_message_id),
                    'provider_status' => 'sent',
                    'provider_error' => null,
                    'retry_count' => (int) $outsideMessage->retry_count + 1,
                    'sent_at' => now(),
                ]);
            } else {
                $response = $this->whatsAppCloudService->sendText(
                    (string) $contact?->phone,
                    (string) $outsideMessage->body
                );
                $outsideMessage->update([
                    'external_message_id' => (string) data_get($response, 'messages.0.id', $outsideMessage->external_message_id),
                    'provider_status' => 'sent',
                    'provider_error' => null,
                    'retry_count' => (int) $outsideMessage->retry_count + 1,
                    'sent_at' => now(),
                ]);
            }
        } catch (\Throwable $exception) {
            $outsideMessage->update([
                'provider_status' => 'failed',
                'provider_error' => $exception->getMessage(),
                'retry_count' => (int) $outsideMessage->retry_count + 1,
            ]);
        }

        $outsideMessage->conversation?->update([
            'last_outbound_at' => now(),
            'latest_message_preview' => mb_substr((string) $outsideMessage->body, 0, 120),
        ]);

        return redirect()->route('outside.index')->with('success', 'تم تنفيذ إعادة المحاولة.');
    }

    private function normalizedOutsideChannel(?string $channel): string
    {
        $c = strtolower(trim((string) ($channel ?? 'whatsapp')));

        return match ($c) {
            'instagram' => 'instagram',
            'messenger' => 'messenger',
            default => 'whatsapp',
        };
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function instagramSendContext(?OutsideContact $contact): array
    {
        if (! $contact) {
            throw new \RuntimeException('لا توجد جهة مرتبطة بالمحادثة.');
        }

        $psid = (string) ($contact->instagram_psid ?? '');
        if ($psid === '') {
            throw new \RuntimeException('جهة إنستغرام بدون معرف مراسلة (PSID).');
        }

        $systemToken = trim((string) config('services.instagram.access_token', ''));
        $igBizFromEnv = trim((string) config('services.instagram.business_account_id', ''));

        if ($systemToken !== '') {
            $igBiz = $igBizFromEnv;
            if ($igBiz === '') {
                $clientId = (int) ($contact->client_id ?? 0);
                if ($clientId !== 0) {
                    $integration = ClientMetaIntegration::query()->where('client_id', $clientId)->first();
                    $igBiz = (string) ($integration?->meta_instagram_account_id ?? '');
                }
            }
            if ($igBiz === '') {
                throw new \RuntimeException('ضبط INSTAGRAM_BUSINESS_ACCOUNT_ID في .env أو اربط الجهة بعميل وحدّد meta_instagram_account_id في تكامل ميتا.');
            }

            return [$igBiz, $systemToken, $psid];
        }

        $clientId = (int) ($contact->client_id ?? 0);
        if ($clientId === 0) {
            throw new \RuntimeException('الجهة غير مربوطة بعميل؛ يجب أن تمر الرسائل عبر حساب إنستغرام أعمال مربوط بعميل في النظام، أو ضبط INSTAGRAM_ACCESS_TOKEN وINSTAGRAM_BUSINESS_ACCOUNT_ID في .env.');
        }

        $integration = ClientMetaIntegration::query()->where('client_id', $clientId)->first();
        $igFromIntegration = (string) ($integration?->meta_instagram_account_id ?? '');

        $igBiz = $igFromIntegration;
        if ($igBiz === '') {
            throw new \RuntimeException('لا يوجد حساب إنستغرام أعمال في تكامل ميتا لهذا العميل.');
        }

        $token = (string) ($this->clientMetaConnection->getAccessTokenForClient($clientId) ?? '');
        if ($token === '') {
            throw new \RuntimeException('لا يوجد رمز وصول ميتا صالح للعميل؛ أعد الربط من بوابة العميل أو ضبط INSTAGRAM_ACCESS_TOKEN من مدير الأعمال.');
        }

        return [$igBiz, $token, $psid];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function messengerSendContext(?OutsideContact $contact): array
    {
        if (! $contact) {
            throw new \RuntimeException('لا توجد جهة مرتبطة بالمحادثة.');
        }

        $psid = (string) ($contact->messenger_psid ?? '');
        if ($psid === '') {
            throw new \RuntimeException('جهة ماسنجر بدون معرف مراسلة (PSID).');
        }

        $systemToken = trim((string) config('services.messenger.access_token', ''));
        $pageFromEnv = trim((string) config('services.messenger.page_id', ''));

        if ($systemToken !== '') {
            $pageId = $pageFromEnv;
            if ($pageId === '') {
                $clientId = (int) ($contact->client_id ?? 0);
                if ($clientId !== 0) {
                    $integration = ClientMetaIntegration::query()->where('client_id', $clientId)->first();
                    $pageId = (string) ($integration?->meta_page_id ?? '');
                }
            }
            if ($pageId === '') {
                throw new \RuntimeException('ضبط MESSENGER_PAGE_ID في .env أو اربط الجهة بعميل وحدّد meta_page_id في تكامل ميتا.');
            }

            return [$pageId, $systemToken, $psid];
        }

        $clientId = (int) ($contact->client_id ?? 0);
        if ($clientId === 0) {
            throw new \RuntimeException('الجهة غير مربوطة بعميل؛ ضبط MESSENGER_PAGE_ACCESS_TOKEN وMESSENGER_PAGE_ID أو اربط جهة ماسنجر بعميل له تكامل ميتا.');
        }

        $integration = ClientMetaIntegration::query()->where('client_id', $clientId)->first();
        $pageId = (string) ($integration?->meta_page_id ?? '');
        if ($pageId === '') {
            throw new \RuntimeException('لا يوجد meta_page_id في تكامل ميتا لهذا العميل.');
        }

        $token = (string) ($this->clientMetaConnection->getAccessTokenForClient($clientId) ?? '');
        if ($token === '') {
            throw new \RuntimeException('لا يوجد رمز وصول ميتا صالح للعميل؛ أعد الربط من بوابة العميل أو ضبط MESSENGER_PAGE_ACCESS_TOKEN.');
        }

        return [$pageId, $token, $psid];
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationIntelligencePayload(OutsideConversation $conversation): array
    {
        if (! Schema::hasColumn('outside_conversations', 'intelligence_classification')) {
            return [
                'enabled' => false,
            ];
        }

        $suggested = $conversation->intelligence_suggested_replies;
        if (! is_array($suggested)) {
            $suggested = [];
        }

        $routing = $conversation->intelligence_routing;
        if (! is_array($routing)) {
            $routing = null;
        }

        $clientContext = $conversation->intelligence_client_context;
        if (! is_array($clientContext)) {
            $clientContext = null;
        }

        return [
            'enabled' => true,
            'classification' => $conversation->intelligence_classification,
            'classification_label' => $this->messagingIntelligence->classificationLabelAr($conversation->intelligence_classification),
            'summary' => $conversation->intelligence_summary,
            'summary_at' => $conversation->intelligence_summary_at?->toIso8601String(),
            'suggested_replies' => array_values(array_filter($suggested, fn ($r) => is_string($r) && trim($r) !== '')),
            'suggested_at' => $conversation->intelligence_suggested_at?->toIso8601String(),
            'routing' => $routing,
            'client_context' => $clientContext,
        ];
    }
}
