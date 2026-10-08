<?php

namespace App\Models;

use App\Support\Attachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    /** The private disk (storage/app/private): files are only reachable through the authorised download route. */
    public const DISK = 'local';
    public const DIR  = 'ticket-attachments';

    protected $fillable = ['ticket_id', 'ticket_comment_id', 'user_id', 'original_name', 'path', 'mime', 'size'];
    protected $casts = ['size' => 'integer'];

    protected static function booted(): void
    {
        // Removing the row removes the file too.
        static::deleting(fn (TicketAttachment $a) => Storage::disk(self::DISK)->delete($a->path));
    }

    public function ticket()  { return $this->belongsTo(Ticket::class); }
    public function comment() { return $this->belongsTo(TicketComment::class, 'ticket_comment_id'); }
    public function user()    { return $this->belongsTo(User::class); }

    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        return isset(Attachments::IMAGE_TYPES[$this->extension()]);
    }

    public function contentType(): string
    {
        return Attachments::types()[$this->extension()] ?? 'application/octet-stream';
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1) . ' MB'
            : max(1, (int) round($this->size / 1024)) . ' KB';
    }

    /** The person who added it, or an admin. */
    public function canBeRemovedBy(User $user): bool
    {
        return $user->id === $this->user_id || in_array($user->role, ['admin', 'super_admin']);
    }
}
