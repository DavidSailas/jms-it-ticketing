<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A private note a customer pins to a day on their calendar. Only its owner can see or change it. */
class CalendarNote extends Model
{
    public const COLORS = ['amber', 'blue', 'emerald', 'rose', 'violet', 'slate'];
    public const MAX_PER_DAY = 10;

    protected $fillable = ['user_id', 'note_date', 'body', 'color'];
    protected $casts    = ['note_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
