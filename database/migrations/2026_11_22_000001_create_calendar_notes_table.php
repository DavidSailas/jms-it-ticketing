<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('note_date')->index();
            $table->string('body', 500);
            $table->string('color', 12)->default('amber');
            $table->timestamps();

            $table->index(['user_id', 'note_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_notes');
    }
};
