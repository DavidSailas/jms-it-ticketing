<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Attachments are kept on the private disk and only ever leave through here, so they follow the same rules as
 * the ticket itself: the Ticket binding already hides other companies' tickets (CompanyScope), and the checks
 * below mirror TicketController (requesters see their own tickets, engineers the ones assigned to them).
 */
class AttachmentController extends Controller
{
    public function show(Request $request, Ticket $ticket, TicketAttachment $attachment)
    {
        $this->authorizeAccess($request->user(), $ticket, $attachment);

        $disk = Storage::disk(TicketAttachment::DISK);
        abort_unless($disk->exists($attachment->path), 404);

        // Pictures open in the browser; everything else downloads. nosniff stops a browser guessing a different type.
        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type'           => $attachment->contentType(),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control'          => 'private, max-age=3600',
            ],
            $attachment->isImage() ? 'inline' : 'attachment',
        );
    }

    public function destroy(Request $request, Ticket $ticket, TicketAttachment $attachment)
    {
        $user = $request->user();

        $this->authorizeAccess($user, $ticket, $attachment);
        abort_unless($attachment->canBeRemovedBy($user), 403);

        $internal = (bool) $attachment->comment?->is_internal;
        $name     = $attachment->original_name;

        $attachment->delete();

        // Internal notes stay out of the requester's timeline, so their attachments are logged the same way.
        Activity::record($user, $internal ? 'note_added' : 'ticket_updated', "Removed attachment {$name} from {$ticket->ticket_no}", $ticket);

        return back()->with('success', 'Attachment removed.');
    }

    private function authorizeAccess(User $user, Ticket $ticket, TicketAttachment $attachment): void
    {
        abort_unless((int) $attachment->ticket_id === (int) $ticket->id, 404);

        abort_if($user->role === 'user' && (int) $ticket->user_id !== (int) $user->id, 403);
        abort_if($user->role === 'it_support' && (int) $ticket->assigned_to !== (int) $user->id, 403);

        // Internal notes (and what is attached to them) are staff-only.
        abort_if($user->role === 'user' && $attachment->comment?->is_internal, 403);
    }
}
