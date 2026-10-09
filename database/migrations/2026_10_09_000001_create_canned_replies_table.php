<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canned_replies', function (Blueprint $table) {
            $table->id();
            // null = a JMS-wide reply everyone on the support side can use; otherwise it belongs to that person only.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->text('body');
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['Acknowledged', "Hi {requester},\n\nThanks for reporting {ticket_no}. We have received it and are looking into it now. We will update you here as soon as we know more.\n\n{me}"],
            ['Need more details', "Hi {requester},\n\nTo move {ticket_no} forward, could you please send us:\n- exactly what you see on screen (a screenshot of any error helps)\n- when it started and whether anything changed beforehand\n- how many people or devices are affected\n\nThank you,\n{me}"],
            ['Visit scheduled', "Hi {requester},\n\nWe have scheduled a visit for {ticket_no}. Please make sure someone is available on site to let our engineer in.\n\n{me}"],
            ['Remote session', "Hi {requester},\n\nFor {ticket_no} we would like to connect remotely. Please keep the affected computer on and tell us a good time to call you.\n\n{me}"],
            ['Fix applied, please confirm', "Hi {requester},\n\nWe have applied a fix for {ticket_no}. Please test it on your side and confirm that everything works. If the problem comes back, reply here and we will reopen it.\n\n{me}"],
        ];

        DB::table('canned_replies')->insert(array_map(fn ($d) => [
            'user_id' => null, 'title' => $d[0], 'body' => $d[1], 'created_at' => $now, 'updated_at' => $now,
        ], $defaults));
    }

    public function down(): void
    {
        Schema::dropIfExists('canned_replies');
    }
};
