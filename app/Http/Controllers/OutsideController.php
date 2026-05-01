<?php

namespace App\Http\Controllers;

use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Models\User;
use App\Services\WhatsAppCloudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OutsideController extends Controller
{
    public function __construct(
        private readonly WhatsAppCloudService $whatsAppCloudService
    ) {}

    public function index(): Response
    {
        $conversations = OutsideConversation::query()
            ->with([
                'contact:id,name,phone,last_message_at,assigned_user_id',
                'contact.assignedUser:id,name',
                'messages' => fn ($query) => $query->limit(80),
            ])
            ->orderByDesc('updated_at')
            ->get();

        $outboundLast7Days = OutsideMessage::query()
            ->where('direction', 'outbound')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $failedLast7Days = OutsideMessage::query()
            ->where('direction', 'outbound')
            ->where('provider_status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return Inertia::render('Outside/Index', [
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
                    'assigned_user_id' => $conversation->contact?->assigned_user_id,
                    'assigned_user' => $conversation->contact?->assignedUser ? [
                        'id' => $conversation->contact->assignedUser->id,
                        'name' => $conversation->contact->assignedUser->name,
                    ] : null,
                ],
                'messages' => $conversation->messages->reverse()->values()->map(fn (OutsideMessage $message) => [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'body' => $message->body,
                    'provider_status' => $message->provider_status,
                    'provider_error' => $message->provider_error,
                    'retry_count' => $message->retry_count,
                    'created_at' => $message->created_at?->toIso8601String(),
                ]),
            ])->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'conversation_statuses' => [
                ['value' => 'open', 'label' => 'مفتوحة'],
                ['value' => 'pending', 'label' => 'بانتظار الرد'],
                ['value' => 'qualified', 'label' => 'مؤهلة'],
                ['value' => 'closed', 'label' => 'مغلقة'],
            ],
            'metrics' => [
                'total_conversations' => $conversations->count(),
                'open_conversations' => $conversations->where('status', 'open')->count(),
                'closed_conversations' => $conversations->where('status', 'closed')->count(),
                'outbound_last_7_days' => $outboundLast7Days,
                'failed_last_7_days' => $failedLast7Days,
            ],
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
        ]);

        $contact = OutsideContact::query()->firstOrCreate(
            ['phone' => trim((string) $data['phone'])],
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

        $message = $outsideConversation->messages()->create([
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $body,
            'provider_status' => 'queued',
            'sent_by_user_id' => $request->user()?->id,
        ]);

        try {
            $response = $this->whatsAppCloudService->sendText(
                (string) $outsideConversation->contact?->phone,
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

        $outsideConversation->update([
            'latest_message_preview' => mb_substr($body, 0, 120),
            'last_outbound_at' => $message->created_at,
            'updated_at' => $message->created_at,
        ]);

        $outsideConversation->contact?->update([
            'last_message_at' => $message->created_at,
        ]);

        return redirect()->route('outside.index')->with('success', 'تمت معالجة الرسالة.');
    }

    public function updateConversation(Request $request, OutsideConversation $outsideConversation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'pending', 'qualified', 'closed'])],
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

        $outsideConversation->save();

        return redirect()->route('outside.index')->with('success', 'تم تحديث بيانات المحادثة.');
    }

    public function retryMessage(OutsideMessage $outsideMessage): RedirectResponse
    {
        if ($outsideMessage->direction !== 'outbound') {
            return redirect()->route('outside.index');
        }

        try {
            $response = $this->whatsAppCloudService->sendText(
                (string) $outsideMessage->conversation?->contact?->phone,
                (string) $outsideMessage->body
            );
            $outsideMessage->update([
                'external_message_id' => (string) data_get($response, 'messages.0.id', $outsideMessage->external_message_id),
                'provider_status' => 'sent',
                'provider_error' => null,
                'retry_count' => (int) $outsideMessage->retry_count + 1,
                'sent_at' => now(),
            ]);
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
}
