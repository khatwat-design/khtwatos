<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCampaignUpdate;
use App\Models\ClientDailySale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Builds plain-language Arabic summaries from existing sales + campaign rows only.
 */
class ClientAutomatedReportComposer
{
    /**
     * @return array{text: string, period_key: string}
     */
    public function composeDaily(Client $client, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $today = $asOf->copy()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $periodKey = $today->toDateString();

        $t = $this->daySnapshot($client, $today);
        $y = $this->daySnapshot($client, $yesterday);

        $lines = [];
        $lines[] = '📊 ملخص يوم '.$today->locale('ar')->translatedFormat('l j F').' — '.$this->firstName($client);
        $lines[] = '';
        $lines[] = '• المبيعات اليوم: '.$this->moneyAr($t['revenue']).' — عدد الطلبات: '.$t['orders'];
        $lines[] = '• الإنفاق الإعلاني اليوم: '.$this->moneyAr($t['spend']);
        if ($t['roas'] !== null) {
            $lines[] = '• تقريب العائد على الإنفاق: '.$this->fmtNum($t['roas']).' (كل ريال إنفاق مقابل مبيعات)';
        } else {
            $lines[] = '• تقريب العائد على الإنفاق: غير متاح (لا إنفاق أو لا مبيعات مسجّلة)';
        }
        $lines[] = '• تفاعل الرسائل تقريبًا: '.$t['messages'].' رسالة';

        $lines[] = '';
        $lines[] = 'مقارنة بأمس:';
        $lines[] = $this->plainDeltaVsYesterday($t, $y);

        $lines[] = '';
        $lines[] = 'يمكنك دائمًا الاطلاع على التفاصيل في بوابة العميل.';

        return [
            'text' => implode("\n", $lines),
            'period_key' => $periodKey,
        ];
    }

    /**
     * @return array{text: string, period_key: string}
     */
    public function composeWeekly(Client $client, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $end = $asOf->copy()->startOfDay();
        $start = $end->copy()->subDays(6);
        $periodKey = sprintf('%d-W%02d', $end->isoWeekYear(), $end->isoWeek());

        $cur = $this->windowSnapshot($client, $start, $end);
        $prevStart = $start->copy()->subDays(7);
        $prevEnd = $start->copy()->subDay();
        $prev = $this->windowSnapshot($client, $prevStart, $prevEnd);

        $lines = [];
        $lines[] = '📈 ملخص أسبوعك — من '.$start->locale('ar')->translatedFormat('j M').' إلى '.$end->locale('ar')->translatedFormat('j M');
        $lines[] = 'مرحبًا '.$this->firstName($client).'،';
        $lines[] = '';
        $lines[] = '• إجمالي المبيعات: '.$this->moneyAr($cur['revenue']);
        $lines[] = '• إجمالي الطلبات: '.$cur['orders'];
        $lines[] = '• إنفاق إعلاني تقريبي: '.$this->moneyAr($cur['spend']);
        if ($cur['roas'] !== null) {
            $lines[] = '• متوسط العائد على الإنفاق: '.$this->fmtNum($cur['roas']);
        }
        $lines[] = '• رسائل الحملة (تقريبًا): '.$cur['messages'];

        $lines[] = '';
        $lines[] = 'لمحة سريعة:';
        foreach ($this->weeklyInsightsPlain($cur, $prev) as $insight) {
            $lines[] = '• '.$insight;
        }

        $lines[] = '';
        $lines[] = 'للتفاصيل والأرقام الكاملة افتح بوابة العميل.';

        return [
            'text' => implode("\n", $lines),
            'period_key' => $periodKey,
        ];
    }

    /**
     * @return array{revenue: float, orders: int, spend: float, messages: int, roas: ?float}
     */
    private function daySnapshot(Client $client, Carbon $day): array
    {
        $sale = ClientDailySale::query()
            ->where('client_id', $client->id)
            ->whereDate('sales_date', $day->toDateString())
            ->first(['revenue', 'orders_count']);

        $camp = null;
        if (Schema::hasTable('client_campaign_updates')) {
            $camp = ClientCampaignUpdate::query()
                ->where('client_id', $client->id)
                ->whereDate('report_date', $day->toDateString())
                ->first(['ad_spend', 'messages_count']);
        }

        $revenue = (float) ($sale?->revenue ?? 0);
        $orders = (int) ($sale?->orders_count ?? 0);
        $spend = (float) ($camp?->ad_spend ?? 0);
        $messages = (int) ($camp?->messages_count ?? 0);
        $roas = $spend > 0 ? round($revenue / $spend, 2) : null;

        return compact('revenue', 'orders', 'spend', 'messages', 'roas');
    }

    /**
     * @return array{revenue: float, orders: int, spend: float, messages: int, roas: ?float}
     */
    private function windowSnapshot(Client $client, Carbon $start, Carbon $end): array
    {
        $revenue = (float) ClientDailySale::query()
            ->where('client_id', $client->id)
            ->whereDate('sales_date', '>=', $start->toDateString())
            ->whereDate('sales_date', '<=', $end->toDateString())
            ->sum('revenue');
        $orders = (int) ClientDailySale::query()
            ->where('client_id', $client->id)
            ->whereDate('sales_date', '>=', $start->toDateString())
            ->whereDate('sales_date', '<=', $end->toDateString())
            ->sum('orders_count');

        $spend = 0.0;
        $messages = 0;
        if (Schema::hasTable('client_campaign_updates')) {
            $spend = (float) ClientCampaignUpdate::query()
                ->where('client_id', $client->id)
                ->whereDate('report_date', '>=', $start->toDateString())
                ->whereDate('report_date', '<=', $end->toDateString())
                ->sum('ad_spend');
            $messages = (int) ClientCampaignUpdate::query()
                ->where('client_id', $client->id)
                ->whereDate('report_date', '>=', $start->toDateString())
                ->whereDate('report_date', '<=', $end->toDateString())
                ->sum('messages_count');
        }

        $roas = $spend > 0 ? round($revenue / $spend, 2) : null;

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'spend' => $spend,
            'messages' => $messages,
            'roas' => $roas,
        ];
    }

    /**
     * @param  array{revenue: float, orders: int, spend: float, messages: int, roas: ?float}  $t
     * @param  array{revenue: float, orders: int, spend: float, messages: int, roas: ?float}  $y
     */
    private function plainDeltaVsYesterday(array $t, array $y): string
    {
        $parts = [];

        if ($y['revenue'] <= 0 && $y['spend'] <= 0 && $y['orders'] <= 0) {
            return 'لا توجد بيانات كافية عن أمس للمقارنة — يكفي أن تتابع تسجيل المبيعات يوميًا.';
        }

        $dr = $t['revenue'] - $y['revenue'];
        if (abs($dr) < 0.01) {
            $parts[] = 'المبيعات قريبة من مستوى أمس.';
        } elseif ($dr > 0) {
            $parts[] = 'المبيعات أعلى من أمس بشكل ملحوظ.';
        } else {
            $parts[] = 'المبيعات أقل من أمس — طبيعي أحيانًا، راجع العروض إن استمر الانخفاض.';
        }

        $ds = $t['spend'] - $y['spend'];
        if (abs($ds) < 0.01) {
            $parts[] = 'الإنفاق الإعلاني مشابه لأمس.';
        } elseif ($ds > 0) {
            $parts[] = 'الإنفاق أعلى قليلًا من أمس.';
        } else {
            $parts[] = 'الإنفاق أقل من أمس.';
        }

        if ($t['roas'] !== null && $y['roas'] !== null) {
            if ($t['roas'] >= $y['roas'] + 0.15) {
                $parts[] = 'كفاءة الإنفاق (تقريبًا) أفضل من أمس.';
            } elseif ($t['roas'] <= $y['roas'] - 0.15) {
                $parts[] = 'كفاءة الإنفاق أضعف قليلًا من أمس — يستحق متابعة من الفريق.';
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array{revenue: float, orders: int, spend: float, messages: int, roas: ?float}  $cur
     * @param  array{revenue: float, orders: int, spend: float, messages: int, roas: ?float}  $prev
     * @return list<string>
     */
    private function weeklyInsightsPlain(array $cur, array $prev): array
    {
        $out = [];

        if ($cur['roas'] !== null && $cur['roas'] >= 2.2) {
            $out[] = 'الأسبوع كان قويًا من حيث العائد مقابل الإنفاق تقريبًا.';
        } elseif ($cur['roas'] !== null && $cur['roas'] < 1.2 && $cur['spend'] > 30) {
            $out[] = 'الإنفاق مرتفع مقارنة بالمبيعات — قد يكون مناسبًا لمراجعة سريعة مع فريقكم.';
        }

        if ($cur['orders'] === 0 && $cur['spend'] > 40) {
            $out[] = 'يُفضّل التأكد من تسجيل المبيعات يوميًا حتى تظهر الصورة كاملة.';
        }

        if ($prev['revenue'] > 0) {
            $chg = ($cur['revenue'] - $prev['revenue']) / $prev['revenue'];
            if ($chg > 0.12) {
                $out[] = 'المبيعات أفضل من الأسبوع الذي قبله.';
            } elseif ($chg < -0.12) {
                $out[] = 'المبيعات أقل من الأسبوع السابق — يمكن مناقشة الأسباب مع الفريق.';
            }
        }

        if ($out === []) {
            $out[] = 'البيانات تبدو مستقرة — تابع نفس الوتيرة وراجع البوابة لأي تفاصيل.';
        }

        return array_slice($out, 0, 3);
    }

    private function firstName(Client $client): string
    {
        $n = trim((string) $client->name);

        return $n !== '' ? explode(' ', $n, 2)[0] : 'عميلنا';
    }

    private function moneyAr(float $v): string
    {
        return number_format($v, 0, '.', ',').' ر.س تقريبًا';
    }

    private function fmtNum(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ','), '0'), '.');
    }
}
