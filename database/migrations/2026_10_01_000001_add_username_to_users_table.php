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
            $table->string('username', 30)->nullable()->unique()->after('name');
        });

        // Give every existing account a username (the part of the email before the @).
        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $base = strtolower(preg_replace('/[^A-Za-z0-9._-]/', '', explode('@', $user->email)[0]));
            $base = str_pad(substr($base ?: 'user', 0, 26), 3, '0');
            $name = $base;
            $i = 1;

            while (DB::table('users')->where('username', $name)->exists()) {
                $name = $base . (++$i);
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $name]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
