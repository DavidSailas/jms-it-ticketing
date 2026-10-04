<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileAvatarController extends Controller
{
    /** Streams a profile photo. Photos are private: only signed-in users can see them. */
    public function show(User $user)
    {
        abort_unless($user->avatar && Storage::disk('local')->exists($user->avatar), 404);

        return Storage::disk('local')->response($user->avatar, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'avatar.required' => 'Choose a photo to upload.',
            'avatar.file'     => 'The upload did not complete. Please try again.',
            'avatar.mimes'    => 'The photo must be a JPG, PNG or WebP image.',
            'avatar.max'      => 'The photo is too large. Please choose one under 2 MB.',
            'avatar.uploaded' => 'The photo could not be uploaded. It may be larger than the server allows.',
        ]);

        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'local');

        if ($user->avatar) {
            Storage::disk('local')->delete($user->avatar);
        }

        $user->forceFill(['avatar' => $path])->save();
        Activity::record($user, 'avatar_updated', 'Changed your profile photo');

        return back()->with('success', 'Your profile photo was updated.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('local')->delete($user->avatar);
            $user->forceFill(['avatar' => null])->save();
            Activity::record($user, 'avatar_removed', 'Removed your profile photo');
        }

        return back()->with('success', 'Your profile photo was removed.');
    }
}
