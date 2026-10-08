<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PendingTicketController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));


Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/avatars/{user}', [ProfileAvatarController::class, 'show'])->name('avatar.show');
    Route::post('/profile/avatar', [ProfileAvatarController::class, 'update'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileAvatarController::class, 'destroy'])->name('profile.avatar.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // Live "Waiting for acceptance" feed. Must be declared before the tickets resource (tickets/{ticket}).
    Route::get('tickets/pending-feed', PendingTicketController::class)
        ->middleware('role:admin,super_admin')->name('tickets.pending-feed');

    // Booking calendar for staff.
    Route::get('schedule', [ScheduleController::class, 'index'])
        ->middleware('role:it_support,admin,super_admin')->name('schedule.index');

    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('tickets/{ticket}/cancel', [TicketController::class, 'cancel'])->name('tickets.cancel');
    Route::post('tickets/{ticket}/feedback', [TicketController::class, 'feedback'])->name('tickets.feedback');
    Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');
    Route::post('tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comment');

    // Screenshots, error photos and log files. Served through the app, never from a public folder.
    Route::get('tickets/{ticket}/attachments/{attachment}', [AttachmentController::class, 'show'])->name('tickets.attachments.show');
    Route::delete('tickets/{ticket}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('tickets.attachments.destroy');

    // Engineers (and admins) update the work progress.
    Route::post('tickets/{ticket}/progress', [TicketController::class, 'progress'])
        ->middleware('role:it_support,admin,super_admin')->name('tickets.progress');

    // IT Support / admin decide remote vs on-site.
    Route::post('tickets/{ticket}/support-type', [TicketController::class, 'supportType'])
        ->middleware('role:it_support,admin,super_admin')->name('tickets.support-type');

    // Admin triage: accept the ticket and assign an engineer.
    Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])
        ->middleware('role:admin,super_admin')->name('tickets.assign');
    Route::get('tickets/{ticket}/edit', [TicketController::class, 'edit'])
        ->middleware('role:admin,super_admin')->name('tickets.edit');
    Route::put('tickets/{ticket}/details', [TicketController::class, 'updateDetails'])
        ->middleware('role:admin,super_admin')->name('tickets.details');
    Route::patch('tickets/{ticket}', [TicketController::class, 'update'])
        ->middleware('role:admin,super_admin')->name('tickets.update');

    // Reports and company management are for super admins (JMS) only.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
        Route::patch('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
        Route::patch('companies/{company}/branding', [BrandingController::class, 'updateFor'])->name('companies.branding');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // A company admin sets their own company's name, logo and colour.
    Route::middleware('role:admin')->group(function () {
        Route::get('branding', [BrandingController::class, 'edit'])->name('branding.edit');
        Route::patch('branding', [BrandingController::class, 'update'])->name('branding.update');
    });

    Route::middleware('role:admin,super_admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/import-template', [UserController::class, 'importTemplate'])->name('users.import-template');
        Route::post('users/import', [UserController::class, 'import'])->name('users.import');
        Route::get('users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/avatar', [UserController::class, 'updateAvatar'])->whereNumber('user')->name('users.avatar.update');
        Route::delete('users/{user}/avatar', [UserController::class, 'destroyAvatar'])->whereNumber('user')->name('users.avatar.destroy');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__ . '/auth.php';
