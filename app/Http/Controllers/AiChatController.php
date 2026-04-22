<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AiChatController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $conversationId = $request->filled('conversation') ? (int) $request->query('conversation') : null;

        $conversations = AiConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'last_message_at', 'updated_at']);

        $selectedConversation = null;
        if ($conversationId) {
            $selectedConversation = $conversations->firstWhere('id', $conversationId);
        }
        if (! $selectedConversation) {
            $selectedConversation = $conversations->first();
        }

        $messages = collect();
        if ($selectedConversation) {
            $messages = AiMessage::query()
                ->where('conversation_id', $selectedConversation->id)
                ->orderBy('id')
                ->get(['id', 'role', 'content', 'created_at']);
        }

        return Inertia::render('Ai/Index', [
            'conversations' => $conversations->map(fn (AiConversation $conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->title ?: 'محادثة جديدة',
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ])->values(),
            'selectedConversationId' => $selectedConversation?->id,
            'messages' => $messages->map(fn (AiMessage $message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $conversation = AiConversation::query()->create([
            'user_id' => $request->user()->id,
            'title' => null,
        ]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => 'محادثة جديدة',
                'last_message_at' => null,
            ],
            'messages' => [],
        ]);
    }

    public function showConversation(Request $request, AiConversation $aiConversation): JsonResponse
    {
        $this->ensureOwnedConversation($request, $aiConversation);

        $messages = AiMessage::query()
            ->where('conversation_id', $aiConversation->id)
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'created_at']);

        return response()->json([
            'conversation' => [
                'id' => $aiConversation->id,
                'title' => $aiConversation->title ?: 'محادثة جديدة',
                'last_message_at' => $aiConversation->last_message_at?->toIso8601String(),
            ],
            'messages' => $messages->map(fn (AiMessage $message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        $userMessageText = trim((string) $data['message']);

        $conversation = null;
        if (! empty($data['conversation_id'])) {
            $conversation = AiConversation::query()->find($data['conversation_id']);
            if ($conversation) {
                $this->ensureOwnedConversation($request, $conversation);
            }
        }

        if (! $conversation) {
            $conversation = AiConversation::query()->create([
                'user_id' => $user->id,
                'title' => null,
            ]);
        }

        $baseUrl = rtrim((string) config('services.ollama.base_url', 'http://127.0.0.1:11434'), '/');
        $model = (string) config('services.ollama.model', 'qwen2.5:7b-instruct');
        $timeout = (int) config('services.ollama.timeout', 120);

        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessageText,
        ]);

        $history = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(24)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $payloadMessages = [
            [
                'role' => 'system',
                'content' => 'اسمك خطوة. مساعد عام داخل النظام. لا تنفذ أي إجراء على البيانات ولا تدعي أنك نفذته. قدم إجابات عملية ومختصرة بالعربية.',
            ],
            ...$history->map(fn (AiMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])->all(),
        ];

        $response = Http::timeout(max(10, $timeout))
            ->acceptJson()
            ->asJson()
            ->post($baseUrl.'/api/chat', [
                'model' => $model,
                'messages' => $payloadMessages,
                'stream' => false,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'message' => 'تعذر الاتصال بخدمة خطوة حالياً.',
                'details' => Str::limit($response->body(), 500),
            ], 502);
        }

        $reply = (string) data_get($response->json(), 'message.content', '');
        if (trim($reply) === '') {
            return response()->json([
                'message' => 'لم يتم استلام رد صالح من خطوة.',
            ], 502);
        }

        $assistantMessage = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => trim($reply),
        ]);

        $conversation->last_message_at = now();
        $conversation->save();

        $this->autoTitleConversation($conversation, $baseUrl, $model, $timeout);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title ?: 'محادثة جديدة',
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ],
            'message' => [
                'id' => $assistantMessage->id,
                'role' => $assistantMessage->role,
                'content' => $assistantMessage->content,
                'created_at' => $assistantMessage->created_at->toIso8601String(),
            ],
        ]);
    }

    private function ensureOwnedConversation(Request $request, AiConversation $conversation): void
    {
        if ((int) $conversation->user_id !== (int) $request->user()->id) {
            abort(403, 'لا تملك صلاحية الوصول لهذه المحادثة.');
        }
    }

    private function autoTitleConversation(AiConversation $conversation, string $baseUrl, string $model, int $timeout): void
    {
        if (!empty($conversation->title)) {
            return;
        }

        $messages = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->limit(8)
            ->get(['role', 'content']);

        $userCount = $messages->where('role', 'user')->count();
        if ($userCount < 2) {
            return;
        }

        $conversationText = $messages
            ->map(fn (AiMessage $message) => ($message->role === 'user' ? 'المستخدم' : 'خطوة').': '.$message->content)
            ->implode("\n");

        $titleResponse = Http::timeout(max(8, min(25, $timeout)))
            ->acceptJson()
            ->asJson()
            ->post($baseUrl.'/api/generate', [
                'model' => $model,
                'stream' => false,
                'prompt' => "اقترح عنوانًا عربيًا قصيرًا جدًا (3 إلى 6 كلمات) لهذه المحادثة. أعد العنوان فقط بدون شرح.\n\n".$conversationText,
            ]);

        if (! $titleResponse->successful()) {
            return;
        }

        $title = trim((string) data_get($titleResponse->json(), 'response', ''));
        $title = preg_replace('/[\r\n]+/', ' ', $title ?? '');
        $title = trim((string) $title, " \t\n\r\0\x0B\"'`.,:;،");

        if ($title === '') {
            return;
        }

        $conversation->title = Str::limit($title, 80, '');
        $conversation->save();
    }
}

