<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // A customer asked to move a booking that an engineer has already been assigned to.
            // Nothing changes on the booking until an admin, super admin or the assigned engineer approves it.
            $table->timestamp('reschedule_requested_for')->nullable();
            $table->string('reschedule_reason', 500)->nullable();
            $table->timestamp('reschedule_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['reschedule_requested_for', 'reschedule_reason', 'reschedule_requested_at']);
        });
    }
};
