<?php

namespace App\Http\Controllers;

use App\Models\GoodsMetaLead;
use App\Models\GoodsMetaLeadStatusHistory;
use App\Support\GoodsMetaLeadWorkflow;
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
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $previous = $goodsMetaLead->workflow_status;
        $previousCallAt = $goodsMetaLead->next_call_at?->toIso8601String();

        $goodsMetaLead->fill([
            'workflow_status' => $data['workflow_status'],
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'team_notes' => $data['team_notes'] ?? $goodsMetaLead->team_notes,
            'probability_label' => $data['probability_label'] ?? $goodsMetaLead->probability_label,
            'reason_label' => $data['reason_label'] ?? $goodsMetaLead->reason_label,
            'outcome_label' => $data['outcome_label'] ?? $goodsMetaLead->outcome_label,
            'next_contact_date' => $data['next_contact_date'] ?? $goodsMetaLead->next_contact_date,
            'next_call_at' => $data['next_call_at'] ?? $goodsMetaLead->next_call_at,
        ]);

        if (($goodsMetaLead->next_call_at?->toIso8601String() ?? null) !== $previousCallAt) {
            $goodsMetaLead->call_reminder_sent_at = null;
        }

        $goodsMetaLead->save();

        if ($previous !== $goodsMetaLead->workflow_status) {
            GoodsMetaLeadStatusHistory::query()->create([
                'goods_meta_lead_id' => $goodsMetaLead->id,
                'from_status' => $previous,
                'to_status' => $goodsMetaLead->workflow_status,
                'note' => $data['note'] ?? 'تحديث من واجهة البضاعة',
                'user_id' => $request->user()?->id,
            ]);
        }

        return redirect()
            ->route('goods.index', ['tab' => 'meta_leads'])
            ->with('success', 'تم تحديث ليدز ميتا.');
    }
}
