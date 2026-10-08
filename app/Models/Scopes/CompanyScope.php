<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Keeps every company's tickets private: signed-in company people only ever see their own company's tickets.
 * Super admins and JMS admins (JMS itself) see all companies; JMS engineers see only what is assigned to them. Console commands and queues have no signed-in person, so they are not limited.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || $user->role === 'super_admin' || $user->isJmsAdmin()) {
            return;
        }

        // JMS's own engineers work across partners, but only ever see the tickets given to them.
        if ($user->isJmsEngineer()) {
            $builder->where($model->qualifyColumn('assigned_to'), $user->id);

            return;
        }

        $user->company_id
            ? $builder->where($model->qualifyColumn('company_id'), $user->company_id)
            : $builder->whereRaw('1 = 0'); // not attached to a company yet: sees nothing
    }
}
