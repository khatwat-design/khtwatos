<?php

namespace Database\Seeders;

use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\ClientStageHistory;
use App\Models\Meeting;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AgencyDataSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['key' => 'lead', 'label' => 'عميل محتمل', 'sort_order' => 10],
            ['key' => 'sales_meeting', 'label' => 'اجتماع مبيعات', 'sort_order' => 20],
            ['key' => 'brief_meeting', 'label' => 'اجتماع البريف', 'sort_order' => 30],
            ['key' => 'analysis', 'label' => 'تحليل', 'sort_order' => 40],
            ['key' => 'analysis_delivered', 'label' => 'تم تسليم التحليل', 'sort_order' => 50],
            ['key' => 'payment', 'label' => 'دفع', 'sort_order' => 60],
            ['key' => 'content_production', 'label' => 'إنتاج المحتوى', 'sort_order' => 70],
            ['key' => 'campaign_launch', 'label' => 'إطلاق الحملة', 'sort_order' => 80],
            ['key' => 'optimization', 'label' => 'تحسين', 'sort_order' => 90],
        ];
        foreach ($stages as $row) {
            PipelineStage::query()->updateOrCreate(['key' => $row['key']], $row);
        }

        $leadStage = PipelineStage::query()->where('key', 'lead')->firstOrFail();

        $teams = [
            ['name' => 'الكتابة', 'slug' => 'writing', 'sort_order' => 10],
            ['name' => 'الميديا باير', 'slug' => 'media-buyer', 'sort_order' => 20],
            ['name' => 'أكاونت', 'slug' => 'account', 'sort_order' => 30],
            ['name' => 'المبيعات', 'slug' => 'sales', 'sort_order' => 40],
            ['name' => 'الموارد البشرية', 'slug' => 'hr', 'sort_order' => 50],
            ['name' => 'المحاسبة', 'slug' => 'accounting', 'sort_order' => 60],
        ];

        foreach ($teams as $t) {
            Team::query()->updateOrCreate(['slug' => $t['slug']], $t);
        }

        $users = [
            ['name' => 'مدير النظام', 'email' => 'admin@agency.test', 'role' => 'admin', 'calendly_url' => 'https://calendly.com/demo-admin', 'teams' => []],
            ['name' => 'حسين سلام', 'email' => 'hussein.salam@agency.test', 'role' => 'lead', 'calendly_url' => 'https://calendly.com/demo-hussein-writing', 'teams' => [['slug' => 'writing', 'allocation' => null, 'is_lead' => true]]],
            ['name' => 'ليث', 'email' => 'laith@agency.test', 'role' => 'member', 'calendly_url' => 'https://calendly.com/demo-laith', 'teams' => [
                ['slug' => 'writing', 'allocation' => 60, 'is_lead' => false],
                ['slug' => 'media-buyer', 'allocation' => 40, 'is_lead' => false],
            ]],
            ['name' => 'محمود', 'email' => 'mahmoud@agency.test', 'role' => 'member', 'calendly_url' => null, 'teams' => [['slug' => 'writing', 'allocation' => 60, 'is_lead' => false]]],
            ['name' => 'محمد خالد', 'email' => 'mohammed.khalid@agency.test', 'role' => 'lead', 'calendly_url' => 'https://calendly.com/demo-mk-media', 'teams' => [['slug' => 'media-buyer', 'allocation' => null, 'is_lead' => true]]],
            ['name' => 'عبدالله', 'email' => 'abdullah@agency.test', 'role' => 'member', 'calendly_url' => null, 'teams' => [['slug' => 'media-buyer', 'allocation' => null, 'is_lead' => false]]],
            ['name' => 'شذى', 'email' => 'shatha@agency.test', 'role' => 'lead', 'calendly_url' => 'https://calendly.com/demo-shatha', 'teams' => [['slug' => 'account', 'allocation' => null, 'is_lead' => true]]],
            ['name' => 'مها', 'email' => 'maha@agency.test', 'role' => 'member', 'calendly_url' => null, 'teams' => [['slug' => 'account', 'allocation' => null, 'is_lead' => false]]],
            ['name' => 'نبراس', 'email' => 'nabras@agency.test', 'role' => 'lead', 'calendly_url' => null, 'teams' => [['slug' => 'sales', 'allocation' => null, 'is_lead' => true]]],
            ['name' => 'حسين علي', 'email' => 'hussein.ali@agency.test', 'role' => 'member', 'calendly_url' => null, 'teams' => [['slug' => 'sales', 'allocation' => null, 'is_lead' => false]]],
            ['name' => 'نور', 'email' => 'noor@agency.test', 'role' => 'member', 'calendly_url' => null, 'teams' => [['slug' => 'sales', 'allocation' => null, 'is_lead' => false]]],
            ['name' => 'أحمد بشير', 'email' => 'ahmed.bashir@agency.test', 'role' => 'member', 'calendly_url' => null, 'teams' => [['slug' => 'hr', 'allocation' => null, 'is_lead' => false]]],
            ['name' => 'محمد ثائر', 'email' => 'mohammed.thaer@agency.test', 'role' => 'member', 'calendly_url' => null, 'teams' => [['slug' => 'accounting', 'allocation' => null, 'is_lead' => false]]],
        ];

        foreach ($users as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make('password'),
                    'role' => $row['role'],
                    'calendly_url' => $row['calendly_url'],
                    'is_bookable' => filled($row['calendly_url'] ?? null),
                    'email_verified_at' => now(),
                ]
            );

            foreach ($row['teams'] as $membership) {
                $team = Team::query()->where('slug', $membership['slug'])->first();
                if (! $team) {
                    continue;
                }
                $user->teams()->syncWithoutDetaching([
                    $team->id => [
                        'allocation_percent' => $membership['allocation'],
                        'is_lead' => $membership['is_lead'],
                    ],
                ]);
            }
        }

        $shatha = User::query()->where('email', 'shatha@agency.test')->first();

        $columnNames = ['قائمة الانتظار', 'قيد التنفيذ', 'مراجعة', 'تم'];

        foreach (Team::query()->orderBy('sort_order')->get() as $team) {
            $board = TaskBoard::query()->firstOrCreate(
                ['team_id' => $team->id],
                ['name' => 'لوحة '.$team->name]
            );

            foreach ($columnNames as $i => $name) {
                BoardColumn::query()->firstOrCreate(
                    [
                        'task_board_id' => $board->id,
                        'name' => $name,
                    ],
                    ['sort_order' => $i * 10]
                );
            }

            $columns = $board->columns()->orderBy('sort_order')->get();
            $backlog = $columns->firstWhere('name', 'قائمة الانتظار');

            if ($backlog) {
                Task::query()->updateOrCreate(
                    [
                        'task_board_id' => $board->id,
                        'title' => 'مهمة تجريبية — '.$team->name,
                    ],
                    [
                        'board_column_id' => $backlog->id,
                        'position' => 0,
                        'description' => 'استبدلها بمهام حقيقية.',
                    ]
                );
            }
        }

        $client = Client::query()->firstOrCreate(
            ['email' => 'client-demo@example.com'],
            [
                'name' => 'عميل تجريبي',
                'company' => 'شركة تجريبية',
                'phone' => '+966500000000',
                'current_pipeline_stage_id' => $leadStage->id,
                'account_manager_id' => $shatha?->id,
                'notes' => 'عميل للاختبار من البذور.',
            ]
        );

        if ($client->stageHistories()->doesntExist()) {
            ClientStageHistory::query()->create([
                'client_id' => $client->id,
                'pipeline_stage_id' => $leadStage->id,
                'user_id' => $shatha?->id,
                'note' => 'تم الإنشاء',
            ]);
        }

        Meeting::query()->firstOrCreate(
            ['external_id' => 'seed-meeting-1'],
            [
                'source' => 'calendly',
                'title' => 'بريف — عميل تجريبي',
                'start_at' => now()->addDays(2)->setHour(10)->setMinute(0),
                'end_at' => now()->addDays(2)->setHour(10)->setMinute(30),
                'invitee_name' => 'عميل تجريبي',
                'invitee_email' => 'client-demo@example.com',
                'reason' => 'بريف حملة',
                'status' => 'scheduled',
                'user_id' => $shatha?->id,
                'client_id' => $client->id,
                'raw_payload' => null,
            ]
        );
    }
}
