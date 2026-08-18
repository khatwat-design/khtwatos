<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->unique()->after('name');
        });

        foreach (DB::table('users')->orderBy('id')->get() as $row) {
            $email = (string) ($row->email ?? '');
            $local = $email !== '' ? explode('@', $email, 2)[0] : '';
            $base = strtolower(preg_replace('/[^a-z0-9]/', '', $local) ?: '');
            if ($base === '') {
                $base = 'u'.$row->id;
            }
            if (strlen($base) < 2) {
                $base .= 'x';
            }
            $candidate = $base;
            $n = 0;
            while (DB::table('users')->where('username', $candidate)->where('id', '!=', $row->id)->exists()) {
                $n++;
                $candidate = $base.$n;
            }
            DB::table('users')->where('id', $row->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
