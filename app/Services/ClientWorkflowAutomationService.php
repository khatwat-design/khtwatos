<?php

namespace App\Services;

use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\ClientStageHistory;
use App\Models\Meeting;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;

class ClientWorkflowAutomationService
{
    public function handleClientStageEntered(Client $client, string $stageKey, ?int $actorId = null): void
    {
        if ($stageKey === 'lead') {
            $salesLeadId = $this->resolveTeamLeadId('sales');
            $this->createTeamTaskForClient(
                $client,
                'sales',
                'المبيعات',
                'اجتماع مبيعات مع العميل '.$client->name,
                $salesLeadId
            );
            $this->createSalesMeetingForLeadClient($client, $salesLeadId);
            return;
        }

        if ($stageKey === 'analysis') {
            $this->createWritingTaskForClient($client, 'البحث والتحليل');
            return;
        }

        if ($stageKey === 'strategy') {
            $this->createWritingTaskForClient($client, 'الاستراتيجية');
            return;
        }

        if ($stageKey === 'payment') {
            $this->createAccountingTaskForClient($client, 'التواصل مع العميل بخصوص الدفع');
            return;
        }

        if ($stageKey === 'content_production') {
            $this->createWritingTaskForClient($client, 'كتابة المحتوى الاعلاني');
            return;
        }

        if ($stageKey === 'content_delivered') {
            $this->createAccountTaskForClient($client, 'استلام المحتوى الاعلاني');
            return;
        }

        if ($stageKey === 'campaign_launch') {
            $this->createMediaBuyerTaskForClient($client, 'اطلاق الحملة الاعلانية');
            return;
        }

        if ($stageKey === 'campaign_analysis') {
            $this->createMediaBuyerTaskForClient($client, 'تحليل الحملة الاعلانية');
        }
    }

    public function handleMeetingCompleted(Meeting $meeting, ?int $actorId = null): void
    {
        if ((int) ($meeting->client_id ?? 0) <= 0) {
            return;
        }

        $client = Client::query()->find($meeting->client_id);
        if (! $client) {
            return;
        }

        $reason = trim((string) ($meeting->reason ?? ''));
        if ($reason === '[AUTO]sales-lead-meeting') {
            if ($client->currentStage?->key !== 'lead') {
                return;
            }

            $this->moveClientToStage($client, 'brief_meeting', $actorId, 'تحويل تلقائي بعد إتمام اجتماع المبيعات');
            $this->createBriefMeetingForClient($client->fresh(), $actorId);
            return;
        }

        if ($reason === '[AUTO]brief-meeting') {
            if ($client->currentStage?->key !== 'brief_meeting') {
                return;
            }

            $this->moveClientToStage($client, 'analysis', $actorId, 'تحويل تلقائي بعد إتمام اجتماع البريف');
            $this->createWritingTaskForClient($client->fresh(), 'البحث والتحليل');
        }
    }

    public function handleTaskMovedToDone(Task $task, ?int $actorId = null): void
    {
        if (! $task->client_id) {
            return;
        }

        $title = trim((string) $task->title);
        if ($title === '') {
            return;
        }

        $client = Client::query()
            ->with('currentStage:id,key')
            ->find($task->client_id);
        if (! $client) {
            return;
        }

        $taskTeamSlug = $this->resolveTaskTeamSlug($task);

        if ($title === 'البحث والتحليل') {
            if ($taskTeamSlug !== 'writing') {
                return;
            }

            $currentStageKey = $client->currentStage?->key;
            if (!in_array($currentStageKey, ['analysis', 'analysis_delivered'], true)) {
                return;
            }

            $this->moveClientToStage($client, 'analysis_delivered', $actorId, 'تحويل تلقائي بعد إكمال مهمة البحث والتحليل');
            $this->moveClientToStage($client, 'strategy', $actorId, 'تحويل تلقائي إلى الاستراتيجية');
            $this->createWritingTaskForClient($client, 'الاستراتيجية');
            return;
        }

        if ($title === 'الاستراتيجية') {
            if ($taskTeamSlug !== 'writing') {
                return;
            }

            if ($client->currentStage?->key !== 'strategy') {
                return;
            }

            $this->moveClientToStage($client, 'strategy_delivered', $actorId, 'تحويل تلقائي بعد إكمال مهمة الاستراتيجية');
            $this->moveClientToStage($client, 'payment', $actorId, 'تحويل تلقائي إلى الدفع بعد تسليم الاستراتيجية');
            $this->createAccountingTaskForClient($client->fresh(), 'التواصل مع العميل بخصوص الدفع');
            return;
        }

        if ($title === 'كتابة المحتوى الاعلاني') {
            if ($taskTeamSlug !== 'writing') {
                return;
            }

            if ($client->currentStage?->key !== 'content_production') {
                return;
            }

            $this->moveClientToStage($client, 'content_delivered', $actorId, 'تحويل تلقائي بعد إكمال مهمة كتابة المحتوى الاعلاني');
            $this->createAccountTaskForClient($client->fresh(), 'استلام المحتوى الاعلاني');
            return;
        }

        if ($title === 'استلام المحتوى الاعلاني') {
            if ($taskTeamSlug !== 'account') {
                return;
            }

            if ($client->currentStage?->key !== 'content_delivered') {
                return;
            }

            $this->moveClientToStage($client, 'campaign_launch', $actorId, 'تحويل تلقائي بعد استلام المحتوى الاعلاني');
            $this->createMediaBuyerTaskForClient($client->fresh(), 'اطلاق الحملة الاعلانية');
            return;
        }

        if ($title === 'التواصل مع العميل بخصوص الدفع') {
            if ($taskTeamSlug !== 'accounting') {
                return;
            }

            if ($client->currentStage?->key !== 'payment') {
                return;
            }

            $this->moveClientToStage($client, 'content_production', $actorId, 'تحويل تلقائي بعد إتمام متابعة الدفع');
            $this->createWritingTaskForClient($client->fresh(), 'كتابة المحتوى الاعلاني');
            return;
        }

        if ($title === 'اطلاق الحملة الاعلانية') {
            if ($taskTeamSlug !== 'media-buyer') {
                return;
            }

            if ($client->currentStage?->key !== 'campaign_launch') {
                return;
            }

            $this->moveClientToStage($client, 'campaign_launched', $actorId, 'تحويل تلقائي بعد إتمام إطلاق الحملات الاعلانية');
            $this->moveClientToStage($client, 'campaign_analysis', $actorId, 'تحويل تلقائي إلى تحليل الحملة الاعلانية');
            $this->createMediaBuyerTaskForClient($client->fresh(), 'تحليل الحملة الاعلانية');
            return;
        }

        if ($title === 'تحليل الحملة الاعلانية') {
            if ($taskTeamSlug !== 'media-buyer') {
                return;
            }

            if ($client->currentStage?->key !== 'campaign_analysis') {
                return;
            }

            $this->createMediaBuyerTaskForClient($client, 'تقرير الحملة الاعلانية');
            return;
        }

        if ($title === 'تقرير الحملة الاعلانية') {
            if ($taskTeamSlug !== 'media-buyer') {
                return;
            }

            if ($client->currentStage?->key !== 'campaign_analysis') {
                return;
            }

            $this->moveClientToStage($client, 'optimization', $actorId, 'تحويل تلقائي بعد إتمام تقرير الحملة الاعلانية');
            $this->createAccountManagerTaskForClient($client->fresh(), 'ارسال تقرير الحملة للعميل');
        }
    }

    private function moveClientToStage(Client $client, string $stageKey, ?int $actorId = null, ?string $note = null): void
    {
        $stage = PipelineStage::query()->where('key', $stageKey)->first();
        if (! $stage) {
            return;
        }

        if ((int) $client->current_pipeline_stage_id === (int) $stage->id) {
            return;
        }

        $client->update([
            'current_pipeline_stage_id' => $stage->id,
        ]);

        ClientStageHistory::query()->create([
            'client_id' => $client->id,
            'pipeline_stage_id' => $stage->id,
            'user_id' => $actorId,
            'note' => $note,
        ]);
    }

    private function createWritingTaskForClient(Client $client, string $title): void
    {
        $leadId = $this->resolveTeamLeadId('writing');
        $this->createTeamTaskForClient($client, 'writing', 'فريق الكتابة', $title, $leadId);
    }

    private function createAccountTaskForClient(Client $client, string $title): void
    {
        $leadId = $this->resolveTeamLeadId('account');
        $this->createTeamTaskForClient($client, 'account', 'مدراء الحسابات', $title, $leadId);
    }

    private function createMediaBuyerTaskForClient(Client $client, string $title): void
    {
        $assigneeId = $this->resolveMediaBuyerAssigneeId($client) ?? $this->resolveTeamLeadId('media-buyer');
        $this->createTeamTaskForClient($client, 'media-buyer', 'مدراء الحملات', $title, $assigneeId);
    }

    private function createAccountManagerTaskForClient(Client $client, string $title): void
    {
        $assigneeId = $this->resolveAccountManagerAssigneeId($client) ?? $this->resolveTeamLeadId('account');
        $this->createTeamTaskForClient($client, 'account', 'مدراء الحسابات', $title, $assigneeId);
    }

    private function createAccountingTaskForClient(Client $client, string $title): void
    {
        $assigneeId = $this->resolveTeamLeadId('accounting');
        $this->createTeamTaskForClient($client, 'accounting', 'المحاسبة', $title, $assigneeId);
    }

    private function createTeamTaskForClient(
        Client $client,
        string $teamSlug,
        string $teamName,
        string $title,
        ?int $assigneeId = null,
    ): void {
        $boardContext = $this->resolveBoardContext($teamSlug, $teamName);
        if (! $boardContext) {
            return;
        }

        $exists = Task::query()
            ->where('client_id', $client->id)
            ->where('task_board_id', $boardContext['board_id'])
            ->where('title', $title)
            ->exists();

        if ($exists) {
            return;
        }

        $maxPosition = (int) Task::query()
            ->where('board_column_id', $boardContext['waiting_column_id'])
            ->max('position');

        $task = Task::query()->create([
            'task_board_id' => $boardContext['board_id'],
            'board_column_id' => $boardContext['waiting_column_id'],
            'title' => $title,
            'description' => null,
            'assignee_id' => $assigneeId,
            'client_id' => $client->id,
            'position' => $maxPosition + 1,
        ]);

        if ($assigneeId) {
            $task->assignees()->sync([$assigneeId]);
        }
    }

    /**
     * @return array{board_id:int, waiting_column_id:int}|null
     */
    private function resolveBoardContext(string $teamSlug, string $teamName): ?array
    {
        $team = Team::query()->firstOrCreate(
            ['slug' => $teamSlug],
            ['name' => $teamName, 'sort_order' => 10]
        );

        $board = TaskBoard::query()->firstOrCreate(
            ['team_id' => $team->id],
            ['name' => 'لوحة '.$team->name]
        );

        $columnDefaults = [
            ['name' => 'قائمة الانتظار', 'sort_order' => 10],
            ['name' => 'قيد التنفيذ', 'sort_order' => 20],
            ['name' => 'مراجعة', 'sort_order' => 30],
            ['name' => 'تم', 'sort_order' => 40],
        ];

        $waitingColumnId = null;
        foreach ($columnDefaults as $columnDefault) {
            $column = BoardColumn::query()->updateOrCreate(
                [
                    'task_board_id' => $board->id,
                    'name' => $columnDefault['name'],
                ],
                ['sort_order' => $columnDefault['sort_order']]
            );

            if ($columnDefault['name'] === 'قائمة الانتظار') {
                $waitingColumnId = (int) $column->id;
            }
        }

        if (! $waitingColumnId) {
            return null;
        }

        return [
            'board_id' => (int) $board->id,
            'waiting_column_id' => $waitingColumnId,
        ];
    }

    private function resolveTeamLeadId(string $teamSlug): ?int
    {
        $leadId = User::query()
            ->whereHas('teams', function ($q) use ($teamSlug): void {
                $q->where('teams.slug', $teamSlug)
                    ->where('team_user.is_lead', true);
            })
            ->orderBy('id')
            ->value('id');

        return $leadId ? (int) $leadId : null;
    }

    private function resolveMediaBuyerAssigneeId(Client $client): ?int
    {
        $candidateId = (int) ($client->campaign_manager_id ?? 0);
        if ($candidateId <= 0) {
            return null;
        }

        $existsInTeam = User::query()
            ->whereKey($candidateId)
            ->whereHas('teams', fn ($q) => $q->where('teams.slug', 'media-buyer'))
            ->exists();

        return $existsInTeam ? $candidateId : null;
    }

    private function resolveAccountManagerAssigneeId(Client $client): ?int
    {
        $candidateId = (int) ($client->account_manager_id ?? 0);
        if ($candidateId <= 0) {
            return null;
        }

        $existsInTeam = User::query()
            ->whereKey($candidateId)
            ->whereHas('teams', fn ($q) => $q->where('teams.slug', 'account'))
            ->exists();

        return $existsInTeam ? $candidateId : null;
    }

    private function resolveTaskTeamSlug(Task $task): ?string
    {
        $task->loadMissing('taskBoard.team:id,slug');
        return $task->taskBoard?->team?->slug;
    }

    private function createSalesMeetingForLeadClient(Client $client, ?int $salesLeadId): void
    {
        if (! $salesLeadId) {
            return;
        }

        $exists = Meeting::query()
            ->where('source', 'internal')
            ->where('client_id', $client->id)
            ->where('reason', '[AUTO]sales-lead-meeting')
            ->where('status', 'scheduled')
            ->exists();

        if ($exists) {
            return;
        }

        $startAt = Carbon::now()->addHour();
        $meeting = Meeting::query()->create([
            'source' => 'internal',
            'external_id' => null,
            'title' => 'اجتماع مبيعات - '.$client->name,
            'start_at' => $startAt,
            'end_at' => (clone $startAt)->addHour(),
            'invitee_name' => $client->name,
            'invitee_email' => $client->email,
            'reason' => '[AUTO]sales-lead-meeting',
            'status' => 'scheduled',
            'user_id' => $salesLeadId,
            'client_id' => $client->id,
            'raw_payload' => null,
        ]);

        $meeting->participants()->sync([$salesLeadId]);
    }

    private function createBriefMeetingForClient(Client $client, ?int $actorId = null): void
    {
        $exists = Meeting::query()
            ->where('source', 'internal')
            ->where('client_id', $client->id)
            ->where('reason', '[AUTO]brief-meeting')
            ->where('status', 'scheduled')
            ->exists();

        if ($exists) {
            return;
        }

        $hostId = $actorId ?: $this->resolveTeamLeadId('account') ?: $this->resolveTeamLeadId('sales');
        if (! $hostId) {
            return;
        }

        $startAt = Carbon::now()->addHours(2);
        $meeting = Meeting::query()->create([
            'source' => 'internal',
            'external_id' => null,
            'title' => 'اجتماع البريف - '.$client->name,
            'start_at' => $startAt,
            'end_at' => (clone $startAt)->addHour(),
            'invitee_name' => $client->name,
            'invitee_email' => $client->email,
            'reason' => '[AUTO]brief-meeting',
            'status' => 'scheduled',
            'user_id' => $hostId,
            'client_id' => $client->id,
            'raw_payload' => null,
        ]);

        $allUserIds = User::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $meeting->participants()->sync($allUserIds);
    }
}
