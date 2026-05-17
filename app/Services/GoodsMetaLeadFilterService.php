<?php

namespace App\Services;

use App\Models\GoodsMetaLead;
use App\Models\User;
use Illuminate\Http\Request;

class GoodsMetaLeadFilterService
{
    private const SESSION_KEY = 'goods_meta_leads_panel_filters';

    /**
     * @return array{
     *     owner: int,
     *     status: string,
     *     campaign: string,
     *     view: string,
     *     should_redirect: bool,
     *     redirect_params: array<string, mixed>,
     *     assignee_defaults_active: bool,
     * }
     */
    public function resolve(Request $request, ?User $user): array
    {
        $assignment = app(GoodsMetaLeadAssignmentService::class);
        $isAssignee = $assignment->isAssignee($user?->id);
        $tab = trim((string) $request->query('tab', 'customers'));

        if ($tab !== 'meta_leads') {
            return $this->emptyResult();
        }

        if ($request->boolean('meta_clear')) {
            $campaign = trim((string) $request->query('meta_campaign', ''));
            session([self::SESSION_KEY => [
                'cleared' => true,
                'owner' => 0,
                'status' => '',
                'campaign' => $campaign,
                'view' => '',
            ]]);

            return [
                'owner' => 0,
                'status' => '',
                'campaign' => $campaign,
                'view' => '',
                'should_redirect' => true,
                'redirect_params' => array_filter([
                    'tab' => 'meta_leads',
                    'meta_campaign' => $campaign !== '' ? $campaign : null,
                ], fn ($value) => $value !== null && $value !== ''),
                'assignee_defaults_active' => false,
            ];
        }

        $hasExplicitQuery = $request->has('meta_owner')
            || $request->has('meta_status')
            || $request->has('meta_campaign')
            || $request->has('meta_view');

        $session = session(self::SESSION_KEY, []);
        $cleared = (bool) ($session['cleared'] ?? false);

        if ($hasExplicitQuery) {
            $owner = (int) $request->query('meta_owner', 0);
            $status = trim((string) $request->query('meta_status', ''));
            $campaign = trim((string) $request->query('meta_campaign', ''));
            $view = trim((string) $request->query('meta_view', ''));

            session([self::SESSION_KEY => [
                'cleared' => false,
                'owner' => $owner,
                'status' => $status,
                'campaign' => $campaign,
                'view' => $view,
            ]]);

            return [
                'owner' => $owner,
                'status' => $status,
                'campaign' => $campaign,
                'view' => $view,
                'should_redirect' => false,
                'redirect_params' => [],
                'assignee_defaults_active' => $isAssignee
                    && $owner === (int) $user?->id
                    && $status === GoodsMetaLead::WORKFLOW_NEW,
            ];
        }

        if ($isAssignee && ! $cleared) {
            $owner = (int) ($session['owner'] ?? $user?->id ?? 0);
            $status = (string) ($session['status'] ?? GoodsMetaLead::WORKFLOW_NEW);
            $campaign = (string) ($session['campaign'] ?? '');
            $view = (string) ($session['view'] ?? '');

            session([self::SESSION_KEY => [
                'cleared' => false,
                'owner' => $owner,
                'status' => $status,
                'campaign' => $campaign,
                'view' => $view,
            ]]);

            return [
                'owner' => $owner,
                'status' => $status,
                'campaign' => $campaign,
                'view' => $view,
                'should_redirect' => true,
                'redirect_params' => $this->goodsIndexParams($user, [
                    'meta_owner' => $owner > 0 ? $owner : null,
                    'meta_status' => $status !== '' ? $status : null,
                    'meta_campaign' => $campaign !== '' ? $campaign : null,
                    'meta_view' => $view !== '' ? $view : null,
                ]),
                'assignee_defaults_active' => $owner === (int) $user?->id
                    && $status === GoodsMetaLead::WORKFLOW_NEW,
            ];
        }

        return [
            'owner' => (int) ($session['owner'] ?? 0),
            'status' => (string) ($session['status'] ?? ''),
            'campaign' => (string) ($session['campaign'] ?? ''),
            'view' => (string) ($session['view'] ?? ''),
            'should_redirect' => false,
            'redirect_params' => [],
            'assignee_defaults_active' => false,
        ];
    }

    /**
     * معاملات إعادة التوجيه بعد حفظ ليد (من الجلسة).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function goodsIndexParams(?User $user, array $extra = []): array
    {
        $session = session(self::SESSION_KEY, []);
        $cleared = (bool) ($session['cleared'] ?? false);
        $assignment = app(GoodsMetaLeadAssignmentService::class);
        $isAssignee = $assignment->isAssignee($user?->id);

        $owner = (int) ($session['owner'] ?? 0);
        $status = (string) ($session['status'] ?? '');
        $campaign = (string) ($session['campaign'] ?? '');
        $view = (string) ($session['view'] ?? '');

        if ($isAssignee && ! $cleared && $owner <= 0) {
            $owner = (int) ($user?->id ?? 0);
            $status = $status !== '' ? $status : GoodsMetaLead::WORKFLOW_NEW;
        }

        $params = array_filter([
            'tab' => 'meta_leads',
            'meta_owner' => $owner > 0 ? $owner : null,
            'meta_status' => $status !== '' ? $status : null,
            'meta_campaign' => $campaign !== '' ? $campaign : null,
            'meta_view' => $view !== '' ? $view : null,
        ], fn ($value) => $value !== null && $value !== '');

        return array_merge($params, array_filter($extra, fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * @return array{
     *     owner: int,
     *     status: string,
     *     campaign: string,
     *     view: string,
     *     should_redirect: bool,
     *     redirect_params: array<string, mixed>,
     *     assignee_defaults_active: bool,
     * }
     */
    private function emptyResult(): array
    {
        return [
            'owner' => 0,
            'status' => '',
            'campaign' => '',
            'view' => '',
            'should_redirect' => false,
            'redirect_params' => [],
            'assignee_defaults_active' => false,
        ];
    }
}
