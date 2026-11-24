<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Company-wide on/off switches, stored in the app_settings table.
 *     Settings::bool('sidebar.custom_background', true)
 *     Settings::set('sidebar.custom_background', false)
 */
class Settings
{
    public const SIDEBAR_IMAGES = 'sidebar.custom_background';

    /** @var array<string,string|null> */
    private static array $memo = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key] ?? $default;
        }

        // A missing table (before "php artisan migrate") must never take the whole app down.
        $value = rescue(fn () => AppSetting::query()->whereKey($key)->value('value'), null, false);

        self::$memo[$key] = $value;

        return $value ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null ? $default : $value === '1';
    }

    public static function set(string $key, bool|string|null $value): void
    {
        $stored = is_bool($value) ? ($value ? '1' : '0') : $value;

        AppSetting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
        self::$memo[$key] = $stored;
    }

    /** Custom background images are allowed unless an admin has switched them off. */
    public static function sidebarImagesAllowed(): bool
    {
        return self::bool(self::SIDEBAR_IMAGES, true);
    }
}
