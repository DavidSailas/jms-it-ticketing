<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safe to run again after a failed attempt: each step only happens if it is still missing.
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('phone', 40)->nullable();
                $table->string('address')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('company')->constrained('companies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('tickets', 'company_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('user_id')->constrained('companies')->nullOnDelete();
            });
        }

        // Start the backfill clean (matters only when re-running after a failure).
        DB::table('tickets')->update(['company_id' => null]);
        DB::table('users')->update(['company_id' => null]);
        DB::table('companies')->delete();

        // Existing data: each distinct company name on an account (super admins excluded) becomes a company.
        // "JMS One IT", "jms one it" and "JMS  ONE IT" are the same company, so names are compared ignoring case and extra spaces.
        $normalize = fn (string $n) => mb_strtolower(preg_replace('/\s+/', ' ', trim($n)));
        $ids = []; // normalized name => company id

        $users = DB::table('users')
            ->where('role', '!=', 'super_admin')
            ->whereNotNull('company')->where('company', '!=', '')
            ->orderBy('id')->get(['id', 'company']);

        foreach ($users as $u) {
            $display = trim(preg_replace('/\s+/', ' ', $u->company));
            if ($display === '') {
                continue;
            }

            $key = $normalize($u->company);
            if (! isset($ids[$key])) {
                $ids[$key] = DB::table('companies')->insertGetId(['name' => $display, 'created_at' => now(), 'updated_at' => now()]);
            }

            DB::table('users')->where('id', $u->id)->update(['company_id' => $ids[$key]]);
        }

        foreach ($ids as $id) {
            DB::table('tickets')->whereIn('user_id', DB::table('users')->where('company_id', $id)->select('id'))->update(['company_id' => $id]);
        }

        // Make the copied company name on each account match the company's official name.
        foreach (DB::table('companies')->get(['id', 'name']) as $c) {
            DB::table('users')->where('company_id', $c->id)->update(['company' => $c->name]);
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::dropIfExists('companies');
    }
};
