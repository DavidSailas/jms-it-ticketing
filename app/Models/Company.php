<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A partner company. Super admins (JMS) create companies and one admin for each;
 * that admin then adds the company's own users and IT support.
 */
class Company extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'logo_path', 'brand_color'];

    protected static function booted(): void
    {
        // Accounts keep a copy of the company name (shown across the app), so keep it in step when the company is renamed.
        static::updated(function (Company $c) {
            if ($c->wasChanged('name')) {
                User::where('company_id', $c->id)->update(['company' => $c->name]);
            }
        });
    }

    /**
     * True for JMS One IT's own company record (written "JMS One IT", "jmsoneit", "JMS-One-IT"...).
     * Only super admins create or rename companies, so nobody else can claim to be JMS this way.
     */
    public static function isJmsName(?string $name): bool
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $name)) === 'jmsoneit';
    }

    /** Ids of the company records that are JMS itself (normally none, because JMS has no company record). */
    public static function jmsIds(): array
    {
        return static::query()->get(['id', 'name'])
            ->filter(fn (Company $c) => static::isJmsName($c->name))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Public address of the uploaded logo, or null to use the standard JMS logo. */
    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        // The timestamp makes browsers pick up a freshly uploaded logo straight away.
        return asset('storage/' . $this->logo_path) . '?v=' . ($this->updated_at?->timestamp ?? 0);
    }

    public function members()  { return $this->hasMany(User::class); }
    public function admins()   { return $this->hasMany(User::class)->where('role', 'admin'); }
    public function engineers() { return $this->hasMany(User::class)->where('role', 'it_support'); }
    public function partners() { return $this->hasMany(User::class)->where('role', 'user'); }
    public function tickets()  { return $this->hasMany(Ticket::class); }
}
