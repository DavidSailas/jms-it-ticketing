<?php

use App\Models\User;
use App\Support\Realtime;
use Illuminate\Support\Facades\Broadcast;

// Who may listen is decided in App\Support\Realtime::canListen().
Broadcast::channel('user.{id}', fn (User $user, int $id) => Realtime::canListen($user, 'user', $id));
Broadcast::channel('company.{id}', fn (User $user, int $id) => Realtime::canListen($user, 'company', $id));
Broadcast::channel('staff.jms', fn (User $user) => Realtime::canListen($user, 'staff'));
