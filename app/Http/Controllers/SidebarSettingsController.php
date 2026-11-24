<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The company-wide switch for custom sidebar background images.
 * Admins and super admins can turn it on or off for everyone (route middleware: role:admin,super_admin).
 */
class SidebarSettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = (bool) $data['enabled'];

        Settings::set(Settings::SIDEBAR_IMAGES, $enabled);

        Activity::record(
            $request->user(),
            'setting_changed',
            $enabled
                ? 'Allowed custom sidebar background images for everyone'
                : 'Turned off custom sidebar background images for everyone'
        );

        return response()->json(['enabled' => $enabled]);
    }
}
