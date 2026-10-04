<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('support_type', 10)->nullable();                // onsite | remote
            $table->unsignedBigInteger('accepted_by')->nullable();         // admin who accepted / dispatched
            $table->timestamp('accepted_at')->nullable();
            $table->text('resolution')->nullable();                        // what the engineer did to fix it
            $table->unsignedTinyInteger('rating')->nullable();             // requester feedback 1-5
            $table->string('rating_comment', 500)->nullable();
        });

        // Room for a full address or a pasted Google Maps link.
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('location', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['support_type', 'accepted_by', 'accepted_at', 'resolution', 'rating', 'rating_comment']);
        });
    }
};
