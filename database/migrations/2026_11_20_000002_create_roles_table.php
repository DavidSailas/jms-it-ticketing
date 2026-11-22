<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->string('description', 200)->nullable();
            $table->string('base_role');            // user | it_support | admin | super_admin: what the person can see
            $table->json('permissions')->nullable(); // what the person can do
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('role')->index();
        });

        $all   = ['tickets.assign', 'tickets.edit', 'tickets.cancel', 'tickets.reschedule', 'tickets.progress', 'users.manage', 'companies.manage', 'reports.view'];
        $roles = [
            ['super_admin', 'Super Admin', 'Full access, including roles. Cannot be changed.', $all],
            ['admin', 'Admin', 'Accepts and assigns tickets and manages customers and engineers.', ['tickets.assign', 'tickets.edit', 'tickets.cancel', 'tickets.reschedule', 'tickets.progress', 'users.manage']],
            ['it_support', 'IT Engineer', 'Works the tickets assigned to them.', ['tickets.progress', 'tickets.reschedule']],
            ['user', 'Customer', 'People from partner companies. They log and follow their own tickets.', []],
        ];

        foreach ($roles as [$slug, $name, $description, $permissions]) {
            DB::table('roles')->insert([
                'slug' => $slug, 'name' => $name, 'description' => $description, 'base_role' => $slug,
                'permissions' => json_encode($permissions), 'is_system' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('users')->where('role', $slug)->update(['role_id' => DB::table('roles')->where('slug', $slug)->value('id')]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
