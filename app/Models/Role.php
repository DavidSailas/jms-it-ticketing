<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A named set of permissions. "base_role" is the account type it builds on, which decides what the person
 * can SEE (their own tickets, assigned tickets, or every ticket); the permissions decide what they can DO.
 * The four built-in roles (is_system) cannot be deleted.
 */
class Role extends Model
{
    /** Account types a custom role can build on. */
    public const BASES = [
        'admin'      => 'Admin level',
        'it_support' => 'IT Engineer level',
    ];

    protected $guarded = [];
    protected $casts   = ['permissions' => 'array', 'is_system' => 'boolean'];

    private static ?Collection $cache = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::$cache = null);
        static::deleted(fn () => self::$cache = null);
    }

    /** Every role, keyed by id. Loaded once per request: there are only a handful. */
    public static function map(): Collection
    {
        return self::$cache ??= static::query()->get()->keyBy('id');
    }

    public static function forSlug(string $slug): ?self
    {
        return self::map()->firstWhere('slug', $slug);
    }

    /** Built-in roles that nobody can change: Super Admin (always everything) and Customer (nothing to give). */
    public function isLocked(): bool
    {
        return $this->is_system && in_array($this->slug, ['super_admin', 'user'], true);
    }

    /** Accounts that currently have this role. */
    public function memberQuery()
    {
        return User::query()->where(function ($q) {
            $q->where('role_id', $this->id);

            if ($this->is_system) {
                $q->orWhere(fn ($x) => $x->whereNull('role_id')->where('role', $this->slug));
            }
        });
    }

    public function baseLabel(): string
    {
        return self::BASES[$this->base_role] ?? User::ROLE_LABELS[$this->base_role] ?? $this->base_role;
    }
}
