<?php

namespace App\Providers;

use App\Support\Activity;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One pagination look for the whole app (resources/views/vendor/pagination/jms.blade.php).
        Paginator::defaultView('vendor.pagination.jms');

        // Sign-in history for the Activity log on My Profile.
        Event::listen(Login::class, fn (Login $e) => Activity::record($e->user, 'login', 'Signed in'));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && Activity::record($e->user, 'logout', 'Signed out'));
        Event::listen(Failed::class, fn (Failed $e) => $e->user && Activity::record(
            $e->user, 'login_failed', 'Failed sign-in attempt (wrong password)'
        ));
    }
}
