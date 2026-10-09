<?php

namespace App\Http\Controllers;

use App\Models\CannedReply;
use Illuminate\Http\Request;

/** Staff manage the saved replies they can insert from a ticket's reply box. */
class CannedReplyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('canned-replies.index', [
            'shared' => CannedReply::whereNull('user_id')->orderBy('title')->get(),
            'mine'   => CannedReply::where('user_id', $user->id)->orderBy('title')->get(),
            'user'   => $user,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validated($request);

        // Only a super admin can publish a reply for everyone; everyone else saves a private one.
        $shared = $user->role === 'super_admin' && $request->boolean('shared');

        CannedReply::create($data + ['user_id' => $shared ? null : $user->id]);

        return back()->with('success', 'Reply saved.');
    }

    public function update(Request $request, CannedReply $cannedReply)
    {
        abort_unless($cannedReply->canBeEditedBy($request->user()), 403);

        $cannedReply->update($this->validated($request));

        return back()->with('success', 'Reply updated.');
    }

    public function destroy(Request $request, CannedReply $cannedReply)
    {
        abort_unless($cannedReply->canBeEditedBy($request->user()), 403);

        $cannedReply->delete();

        return back()->with('success', 'Reply deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'body'  => ['required', 'string', 'max:3000'],
        ]);
    }
}
