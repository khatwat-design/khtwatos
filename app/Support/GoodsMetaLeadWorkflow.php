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
            ['value' => GoodsMetaLead::WORKFLOW_KULL, 'label' => 'الكل'],
            ['value' => GoodsMetaLead::WORKFLOW_SAKHIN, 'label' => 'الساخن'],
            ['value' => GoodsMetaLead::WORKFLOW_DAFEE, 'label' => 'دافئ'],
            ['value' => GoodsMetaLead::WORKFLOW_BARID, 'label' => 'بارد'],
            ['value' => GoodsMetaLead::WORKFLOW_MOUTAFAQ, 'label' => 'تم الاتفاق'],
            ['value' => GoodsMetaLead::WORKFLOW_LAM_YATTASEL, 'label' => 'لم يُتواصل'],
            ['value' => GoodsMetaLead::WORKFLOW_MOSHTAREK, 'label' => 'مشترك'],
            ['value' => GoodsMetaLead::WORKFLOW_ISTIHIQAAQ, 'label' => 'استحقاق'],
            ['value' => GoodsMetaLead::WORKFLOW_ISTIHDAAF, 'label' => 'استهداف'],
            ['value' => GoodsMetaLead::WORKFLOW_MATAABA, 'label' => 'متابعة'],
            ['value' => GoodsMetaLead::WORKFLOW_MOHTAMAL, 'label' => 'محتمل'],
            ['value' => GoodsMetaLead::WORKFLOW_KHUTWAT, 'label' => 'خطوات'],
            ['value' => GoodsMetaLead::WORKFLOW_KHAT, 'label' => 'خط'],
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
            return GoodsMetaLead::WORKFLOW_LAM_YATTASEL;
        }
        if (str_contains($outcome, 'اتفاق') || str_contains($outcome, 'تم') || str_contains($outcome, 'بيع') || str_contains($outcome, 'اشتر')) {
            return GoodsMetaLead::WORKFLOW_MOUTAFAQ;
        }
        if (str_contains($outcome, 'اترك') || str_contains($outcome, 'رفضالمشروع')) {
            return GoodsMetaLead::WORKFLOW_LAM_YATTASEL;
        }
        if (str_contains($outcome, 'مفقود') || str_contains($outcome, 'لم يرد')) {
            return GoodsMetaLead::WORKFLOW_LAM_YATTASEL;
        }
        if (str_contains($outcome, 'متابعه') || str_contains($outcome, 'متابعة')) {
            return GoodsMetaLead::WORKFLOW_MATAABA;
        }

        if (str_contains($probability, 'غير محتمل')) {
            return GoodsMetaLead::WORKFLOW_BARID;
        }
        if (str_contains($probability, 'محتمل')) {
            return GoodsMetaLead::WORKFLOW_MOHTAMAL;
        }

        return GoodsMetaLead::WORKFLOW_KULL;
    }

    private static function normalizeArabic(?string $value): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/\s+/u', '', $text) ?? $text;

        return mb_strtolower($text);
    }
}
