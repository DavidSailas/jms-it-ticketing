<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Keeps tickets private by role:
 *  - Super admins and admins (JMS) see every ticket of every partner company.
 *  - IT engineers (JMS) only see the tickets an admin assigned to them.
 *  - Customers (partner staff) only see the tickets they submitted themselves, not their colleagues'.
 * Console commands and queues have no signed-in person, so they are not limited.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || in_array($user->role, ['super_admin', 'admin'], true)) {
            return;
        }

        if ($user->role === 'it_support') {
            $builder->where($model->qualifyColumn('assigned_to'), $user->id);

            return;
        }

        $builder->where($model->qualifyColumn('user_id'), $user->id);
    }
}
