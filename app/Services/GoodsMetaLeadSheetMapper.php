<?php

namespace App\Services;

use App\Support\GoodsMetaLeadWorkflow;
use Carbon\Carbon;

class GoodsMetaLeadSheetMapper
{
    /**
     * يحوّل صف الشيت (مفاتيح عربية/إنجليزية) إلى حقول موحّدة.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeKey((string) $key)] = $value;
        }

        $metaLeadId = $this->stringValue($normalized, ['id', 'meta_lead_id', 'lead_id']);
        if ($metaLeadId === '') {
            return [];
        }

        $probability = $this->stringValue($normalized, ['احتماليةالعميل', 'probability_label', 'probability']);
        $outcome = $this->stringValue($normalized, ['النتيجه', 'النتيجة', 'outcome_label', 'outcome']);

        $monthly = $this->stringValue($normalized, [
            'كمعددطلباتكالشهريهحاليا',
            'monthly_orders_answer',
        ]);
        $goal = $this->stringValue($normalized, [
            'شنوالمشكلةالليتواجهكاوشنوهدفكمنالعملويانه',
            'goal_answer',
        ]);

        $formExtras = [];
        foreach ($normalized as $key => $value) {
            if (in_array($key, $this->knownKeys(), true)) {
                continue;
            }
            $label = (string) ($row[$key] ?? $key);
            $text = $this->scalarToString($value);
            if ($text !== '') {
                $formExtras[$label] = $text;
            }
        }

        return [
            'meta_lead_id' => $metaLeadId,
            'lead_created_at' => $this->parseDateTime($normalized['created_time'] ?? null),
            'full_name' => $this->stringValue($normalized, ['full_name', 'name', 'الاسم']),
            'phone' => $this->cleanPhone($this->stringValue($normalized, ['phone_number', 'phone', 'الهاتف'])),
            'platform' => $this->stringValue($normalized, ['platform']),
            'campaign_id' => $this->stringValue($normalized, ['campaign_id']),
            'campaign_name' => $this->stringValue($normalized, ['campaign_name']),
            'adset_id' => $this->stringValue($normalized, ['adset_id']),
            'adset_name' => $this->stringValue($normalized, ['adset_name']),
            'ad_id' => $this->stringValue($normalized, ['ad_id']),
            'ad_name' => $this->stringValue($normalized, ['ad_name']),
            'form_id' => $this->stringValue($normalized, ['form_id']),
            'form_name' => $this->stringValue($normalized, ['form_name']),
            'is_organic' => $this->boolValue($normalized['is_organic'] ?? false),
            'meta_lead_status' => $this->stringValue($normalized, ['lead_status', 'meta_lead_status']),
            'monthly_orders_answer' => $monthly,
            'goal_answer' => $goal,
            'team_notes' => $this->stringValue($normalized, ['الملاحظات', 'team_notes', 'notes']),
            'probability_label' => $probability,
            'reason_label' => $this->stringValue($normalized, ['السبب', 'reason_label', 'reason']),
            'outcome_label' => $outcome,
            'workflow_status' => GoodsMetaLeadWorkflow::inferFromSheetLabels($probability, $outcome),
            'first_contact_date' => $this->parseSheetDate($normalized['تاريخالاتصالالاول'] ?? $normalized['first_contact_date'] ?? null),
            'last_contact_date' => $this->parseSheetDate($normalized['تاريخالاتصالالاخير'] ?? $normalized['last_contact_date'] ?? null),
            'next_contact_date' => $this->parseSheetDate($normalized['تاريخالاتصالالقادم'] ?? $normalized['next_contact_date'] ?? null),
            'form_answers' => array_filter([
                'monthly_orders' => $monthly,
                'goal' => $goal,
                'extras' => $formExtras,
            ]),
            'sheet_row_number' => isset($row['_row_number']) ? (int) $row['_row_number'] : null,
            'raw_row' => $row,
        ];
    }

    /**
     * @return list<string>
     */
    private function knownKeys(): array
    {
        return [
            'id', 'meta_lead_id', 'lead_id', 'created_time', 'ad_id', 'ad_name', 'adset_id', 'adset_name',
            'campaign_id', 'campaign_name', 'form_id', 'form_name', 'is_organic', 'platform',
            'full_name', 'name', 'phone_number', 'phone', 'lead_status', 'meta_lead_status',
            'كمعددطلباتكالشهريهحاليا', 'شنوالمشكلةالليتواجهكاوشنوهدفكمنالعملويانه',
            'الملاحظات', 'احتماليةالعميل', 'السبب', 'النتيجه', 'النتيجة',
            'تاريخالاتصالالاول', 'تاريخالاتصالالاخير', 'تاريخالاتصالالقادم',
            'first_contact_date', 'last_contact_date', 'next_contact_date',
            '_row_number',
        ];
    }

    private function normalizeKey(string $key): string
    {
        $key = trim($key);
        $key = preg_replace('/\s+/u', '', $key) ?? $key;

        return mb_strtolower($key);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function stringValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $text = $this->scalarToString($row[$key]);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function scalarToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_numeric($value) && ! is_string($value)) {
            return (string) $value;
        }

        return trim((string) $value);
    }

    private function cleanPhone(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with(strtolower($phone), 'p:')) {
            $phone = substr($phone, 2);
        }

        return trim($phone);
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $text = strtolower(trim((string) $value));

        return in_array($text, ['1', 'true', 'yes', 'y'], true);
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseSheetDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial > 30000 && $serial < 60000) {
                return Carbon::create(1899, 12, 30)->addDays((int) floor($serial));
            }
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '964') && strlen($digits) >= 12) {
            return $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '964'.ltrim($digits, '0');
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '7')) {
            return '964'.$digits;
        }

        return $digits;
    }
}
