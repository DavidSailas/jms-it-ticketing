<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'body', 'is_internal'];
    protected $casts = ['is_internal' => 'boolean'];

    public function user()   { return $this->belongsTo(User::class); }
    public function ticket() { return $this->belongsTo(Ticket::class); }
}
