<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('teams')->where('slug', 'writing')->update(['name' => 'فريق الكتابة']);
        DB::table('teams')->where('slug', 'media-buyer')->update(['name' => 'مدراء الحملات']);
        DB::table('teams')->where('slug', 'account')->update(['name' => 'مدراء الحسابات']);
    }

    public function down(): void
    {
        DB::table('teams')->where('slug', 'writing')->update(['name' => 'الكتابة']);
        DB::table('teams')->where('slug', 'media-buyer')->update(['name' => 'الميديا باير']);
        DB::table('teams')->where('slug', 'account')->update(['name' => 'أكاونت']);
    }
};

