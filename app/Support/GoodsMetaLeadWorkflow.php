<?php

namespace App\Support;

use App\Models\GoodsMetaLead;

final class GoodsMetaLeadWorkflow
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function statusOptions(): array
    {
        return [
            ['value' => GoodsMetaLead::WORKFLOW_NEW, 'label' => 'جديد'],
            ['value' => GoodsMetaLead::WORKFLOW_FOLLOWING, 'label' => 'قيد المتابعة'],
            ['value' => GoodsMetaLead::WORKFLOW_POTENTIAL, 'label' => 'عميل محتمل'],
            ['value' => GoodsMetaLead::WORKFLOW_UNLIKELY, 'label' => 'غير محتمل'],
            ['value' => GoodsMetaLead::WORKFLOW_QUALIFIED, 'label' => 'مؤهل'],
            ['value' => GoodsMetaLead::WORKFLOW_WON, 'label' => 'تم البيع'],
            ['value' => GoodsMetaLead::WORKFLOW_LOST, 'label' => 'مفقود'],
            ['value' => GoodsMetaLead::WORKFLOW_REJECTED, 'label' => 'مرفوض'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusValues(): array
    {
        return array_column(self::statusOptions(), 'value');
    }

    public static function labelFor(?string $status): string
    {
        foreach (self::statusOptions() as $option) {
            if ($option['value'] === $status) {
                return $option['label'];
            }
        }

        return (string) $status;
    }

    public static function inferFromSheetLabels(?string $probability, ?string $outcome): string
    {
        $outcome = self::normalizeArabic($outcome);
        $probability = self::normalizeArabic($probability);

        if (str_contains($outcome, 'رفض')) {
            return GoodsMetaLead::WORKFLOW_REJECTED;
        }
        if (str_contains($outcome, 'تم') || str_contains($outcome, 'بيع') || str_contains($outcome, 'اشتر')) {
            return GoodsMetaLead::WORKFLOW_WON;
        }
        if (str_contains($outcome, 'مفقود') || str_contains($outcome, 'لم يرد')) {
            return GoodsMetaLead::WORKFLOW_LOST;
        }
        if (str_contains($outcome, 'متابعه') || str_contains($outcome, 'متابعة')) {
            return GoodsMetaLead::WORKFLOW_FOLLOWING;
        }

        if (str_contains($probability, 'غير محتمل')) {
            return GoodsMetaLead::WORKFLOW_UNLIKELY;
        }
        if (str_contains($probability, 'محتمل')) {
            return GoodsMetaLead::WORKFLOW_POTENTIAL;
        }

        return GoodsMetaLead::WORKFLOW_NEW;
    }

    private static function normalizeArabic(?string $value): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/\s+/u', '', $text) ?? $text;

        return mb_strtolower($text);
    }
}
