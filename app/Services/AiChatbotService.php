<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiChatbotService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
        $this->model = (string) config('services.gemini.model', env('GEMINI_MODEL', 'gemini-2.0-flash'));
    }

    /**
     * Generate a sales-oriented reply using Gemini AI.
     *
     * @param  array<int, array{role: string, body: string}>  $conversationHistory
     * @param  array<string, mixed>  $leadData
     * @return array{reply: string, confidence: float|null}
     */
    public function generateReply(
        array $conversationHistory,
        array $leadData,
        string $systemPrompt = '',
    ): array {
        if ($this->apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY غير مهيأ في ملف البيئة.');
        }

        $fullSystemPrompt = $this->buildSystemPrompt($leadData, $systemPrompt);
        $contents = $this->buildContents($conversationHistory);

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $fullSystemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
                'topP' => 0.9,
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('فشل الاتصال بـ Gemini API: '.$response->body());
        }

        $json = $response->json();
        $text = data_get($json, 'candidates.0.content.parts.0.text', '');

        if ($text === '') {
            throw new RuntimeException('Gemini API أعاد استجابة فارغة.');
        }

        $confidence = $this->extractConfidence($json);

        return [
            'reply' => trim($text),
            'confidence' => $confidence,
        ];
    }

    private function buildSystemPrompt(array $leadData, string $customPrompt): string
    {
        $base = $customPrompt !== '' ? $customPrompt : $this->getDefaultSystemPrompt();

        $leadInfo = '';
        if (! empty($leadData['full_name'])) {
            $leadInfo .= "\n- اسم العميل: ".$leadData['full_name'];
        }
        if (! empty($leadData['company'])) {
            $leadInfo .= "\n- الشركة: ".$leadData['company'];
        }
        if (! empty($leadData['service_interest'])) {
            $leadInfo .= "\n- الخدمة المطلوبة: ".$leadData['service_interest'];
        }
        if (! empty($leadData['monthly_orders_answer'])) {
            $leadInfo .= "\n- الطلبات الشهرية: ".$leadData['monthly_orders_answer'];
        }
        if (! empty($leadData['goal_answer'])) {
            $leadInfo .= "\n- الهدف: ".$leadData['goal_answer'];
        }

        return $base."\n\n**بيانات العميل:**".$leadInfo;
    }

    private function getDefaultSystemPrompt(): string
    {
        return <<<'PROMPT'
أنت مساعد مبيعات ذكي لشركة متخصصة في تطوير المواقع الإلكترونية والتطبيقات والحلول التقنية المخصصة.

**مهمتك:**
- التواصل مع العملاء المحتملين بشكل ودّي واحترافي
- فهم احتياجاتهم التقنية
- تقديم الحل المناسب من خدماتنا
- إقناعهم بجدوى التعامل معنا
- حجز اجتماع لمناقشة التفاصيل

**قواعد مهمة:**
- ردودك يجب أن تكون باللغة العربية
- كن ودّيًا لكن احترافيًا
- لا تبالغ في الوعود
- ركّز على حل مشاكل العميل
- إذا سأل العميل عن الأسعار، اقترح اجتماع لمناقشة العرض المناسب
- حاول دائمًا تحديد موعد اجتماع

**الخدمات التي نقدمها:**
1. تطوير مواقع إلكترونية (متجاوبة، سريعة، محسّنة لمحركات البحث)
2. تطوير تطبيقات موبايل (iOS و Android)
3. حلول تقنية مخصصة للشركات
4. تصميم واجهات المستخدم (UI/UX)
5. تكامل الأنظمة والـ APIs
6. الاستضافة والصيانة

**تدفق المحادثة النموذجي:**
1. ترحيب وفهم الطلب
2. طرح أسئلة تأهيلية (حجم الشركة، الميزانية، الجدول الزمني)
3. تقديم حل مناسب
4. اقتراح اجتماع لمناقشة العرض
5. حجز الموعد
PROMPT;
    }

    /**
     * @param  array<int, array{role: string, body: string}>  $history
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function buildContents(array $history): array
    {
        $contents = [];
        foreach ($history as $msg) {
            $role = $msg['role'] === 'outbound' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['body']]],
            ];
        }
        return $contents;
    }

    private function extractConfidence(array $json): ?float
    {
        $candidates = data_get($json, 'candidates', []);
        if (empty($candidates[0]['safetyRatings'])) {
            return null;
        }

        $ratings = $candidates[0]['safetyRatings'];
        $total = count($ratings);
        if ($total === 0) {
            return null;
        }

        $safeCount = 0;
        foreach ($ratings as $rating) {
            if (($rating['probability'] ?? '') === 'NEGLIGIBLE' || ($rating['probability'] ?? '') === 'LOW') {
                $safeCount++;
            }
        }

        return round($safeCount / $total * 100, 1);
    }
}
