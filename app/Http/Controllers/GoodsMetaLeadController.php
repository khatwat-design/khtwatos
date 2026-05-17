<?php

namespace App\Http\Controllers;

use App\Models\GoodsMetaLead;
use App\Models\GoodsMetaLeadStatusHistory;
use App\Services\GoodsMetaLeadFilterService;
use App\Support\GoodsMetaLeadWorkflow;
use App\Support\IraqiPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoodsMetaLeadController extends Controller
{
    public function update(Request $request, GoodsMetaLead $goodsMetaLead): RedirectResponse
    {
        $data = $request->validate([
            'workflow_status' => ['required', Rule::in(GoodsMetaLeadWorkflow::statusValues())],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'team_notes' => ['nullable', 'string', 'max:8000'],
            'probability_label' => ['nullable', 'string', 'max:255'],
            'reason_label' => ['nullable', 'string', 'max:255'],
            'outcome_label' => ['nullable', 'string', 'max:255'],
            'next_contact_date' => ['nullable', 'date'],
            'next_call_at' => ['nullable', 'date'],
            'has_whatsapp' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $previous = $goodsMetaLead->workflow_status;
        $previousCallAt = $goodsMetaLead->next_call_at?->toIso8601String();

        $statusChanged = $previous !== $data['workflow_status'];

        $goodsMetaLead->fill([
            'workflow_status' => $data['workflow_status'],
            'workflow_status_managed_at' => $statusChanged
                ? now()
                : $goodsMetaLead->workflow_status_managed_at,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'team_notes' => $data['team_notes'] ?? $goodsMetaLead->team_notes,
            'probability_label' => $data['probability_label'] ?? $goodsMetaLead->probability_label,
            'reason_label' => $data['reason_label'] ?? $goodsMetaLead->reason_label,
            'outcome_label' => $data['outcome_label'] ?? $goodsMetaLead->outcome_label,
            'next_contact_date' => $data['next_contact_date'] ?? $goodsMetaLead->next_contact_date,
            'next_call_at' => $data['next_call_at'] ?? $goodsMetaLead->next_call_at,
            'has_whatsapp' => array_key_exists('has_whatsapp', $data)
                ? (bool) $data['has_whatsapp']
                : $goodsMetaLead->has_whatsapp,
        ]);

        if (($goodsMetaLead->next_call_at?->toIso8601String() ?? null) !== $previousCallAt) {
            $goodsMetaLead->call_reminder_sent_at = null;
        }

        $goodsMetaLead->save();

        if ($statusChanged) {
            GoodsMetaLeadStatusHistory::query()->create([
                'goods_meta_lead_id' => $goodsMetaLead->id,
                'from_status' => $previous,
                'to_status' => $goodsMetaLead->workflow_status,
                'note' => $data['note'] ?? 'تحديث من واجهة البضاعة',
                'user_id' => $request->user()?->id,
            ]);
        }

        return redirect()
            ->route('goods.index', app(GoodsMetaLeadFilterService::class)->goodsIndexParams($request->user()))
            ->with('success', 'تم تحديث ليدز ميتا.');
    }

    public function whatsappContact(Request $request, GoodsMetaLead $goodsMetaLead): RedirectResponse
    {
        $filterParams = app(GoodsMetaLeadFilterService::class)->goodsIndexParams($request->user());

        if ($goodsMetaLead->has_whatsapp !== true) {
            return redirect()
                ->route('goods.index', $filterParams)
                ->with('error', 'هذا الرقم غير مؤهل لواتساب.');
        }

        if (IraqiPhone::toWhatsAppDigits($goodsMetaLead->phone) === '') {
            return redirect()
                ->route('goods.index', $filterParams)
                ->with('error', 'رقم الهاتف غير صالح لفتح واتساب.');
        }

        $previous = $goodsMetaLead->workflow_status;
        $following = GoodsMetaLead::WORKFLOW_FOLLOWING;

        if ($previous !== $following) {
            $goodsMetaLead->workflow_status = $following;
            $goodsMetaLead->workflow_status_managed_at = now();
            $goodsMetaLead->save();

            GoodsMetaLeadStatusHistory::query()->create([
                'goods_meta_lead_id' => $goodsMetaLead->id,
                'from_status' => $previous,
                'to_status' => $following,
                'note' => 'تواصل واتساب من واجهة البضاعة',
                'user_id' => $request->user()?->id,
            ]);
        }

        return redirect()
            ->route('goods.index', $filterParams)
            ->with('success', 'تم تسجيل التواصل عبر واتساب وتحديث الحالة إلى قيد المتابعة.');
    }
}
