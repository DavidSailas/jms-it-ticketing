<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CannedReplyController;
use App\Http\Controllers\DashboardChartController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PendingTicketController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SidebarSettingsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TicketBoardController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));


Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/charts', DashboardChartController::class)
        ->middleware('role:admin,super_admin')->name('dashboard.charts');

    // Admins and super admins decide whether people may upload their own sidebar background image.
    Route::patch('/settings/sidebar-images', [SidebarSettingsController::class, 'update'])
        ->middleware('role:admin,super_admin')->name('settings.sidebar-images');

    // Ctrl+K search: tickets, people and companies this person is allowed to see.
    Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/notifications', [ProfileController::class, 'notifications'])->name('profile.notifications');

    Route::get('/avatars/{user}', [ProfileAvatarController::class, 'show'])->name('avatar.show');
    Route::post('/profile/avatar', [ProfileAvatarController::class, 'update'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileAvatarController::class, 'destroy'])->name('profile.avatar.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // Live "Waiting for acceptance" feed. Must be declared before the tickets resource (tickets/{ticket}).
    Route::get('tickets/pending-feed', PendingTicketController::class)
        ->middleware('permission:tickets.assign')->name('tickets.pending-feed');

    // Kanban board for staff. Declared before the tickets resource (tickets/{ticket}).
    Route::middleware('role:it_support,admin,super_admin')->group(function () {
        Route::get('tickets/board', [TicketBoardController::class, 'index'])->name('tickets.board');
        Route::get('tickets/board/cards', [TicketBoardController::class, 'cards'])->name('tickets.board.cards');
        Route::post('tickets/board/{ticket}/move', [TicketBoardController::class, 'move'])->name('tickets.board.move');
    });

    // Booking calendar for staff.
    // Customers: their own calendar, with private notes.
    Route::middleware('role:user')->group(function () {
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
        Route::post('calendar/notes', [CalendarController::class, 'store'])->name('calendar.notes.store');
        Route::patch('calendar/notes/{note}', [CalendarController::class, 'update'])->name('calendar.notes.update');
        Route::delete('calendar/notes/{note}', [CalendarController::class, 'destroy'])->name('calendar.notes.destroy');
    });

    Route::get('schedule', [ScheduleController::class, 'index'])
        ->middleware('role:it_support,admin,super_admin')->name('schedule.index');

    // Saved replies for staff (inserted from a ticket's reply box).
    Route::middleware('role:it_support,admin,super_admin')->group(function () {
        Route::get('canned-replies', [CannedReplyController::class, 'index'])->name('canned-replies.index');
        Route::post('canned-replies', [CannedReplyController::class, 'store'])->name('canned-replies.store');
        Route::patch('canned-replies/{cannedReply}', [CannedReplyController::class, 'update'])->name('canned-replies.update');
        Route::delete('canned-replies/{cannedReply}', [CannedReplyController::class, 'destroy'])->name('canned-replies.destroy');
    });

    // Quick-assign panel on the company page: the JMS engineer cards, loaded when the panel opens.
    Route::get('tickets/engineer-picker', [TicketController::class, 'engineerPicker'])
        ->middleware('permission:tickets.assign')->name('tickets.engineer-picker');
    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('tickets/{ticket}/cancel', [TicketController::class, 'cancel'])->name('tickets.cancel');
    // Admin / Super Admin (or any role with the permission): cancel a ticket at any stage, with a reason.
    Route::post('tickets/{ticket}/staff-cancel', [TicketController::class, 'staffCancel'])
        ->middleware('permission:tickets.cancel')->name('tickets.staff-cancel');
    Route::post('tickets/{ticket}/reschedule', [TicketController::class, 'reschedule'])->name('tickets.reschedule');
    Route::post('tickets/{ticket}/reschedule/decide', [TicketController::class, 'decideReschedule'])
        ->middleware('permission:tickets.reschedule')->name('tickets.reschedule.decide');
    Route::post('tickets/{ticket}/feedback', [TicketController::class, 'feedback'])->name('tickets.feedback');
    Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');
    Route::post('tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comment');

    // Screenshots, error photos and log files. Served through the app, never from a public folder.
    Route::get('tickets/{ticket}/attachments/{attachment}', [AttachmentController::class, 'show'])->name('tickets.attachments.show');
    Route::delete('tickets/{ticket}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('tickets.attachments.destroy');

    // Engineers (and admins) update the work progress.
    Route::post('tickets/{ticket}/progress', [TicketController::class, 'progress'])
        ->middleware('permission:tickets.progress')->name('tickets.progress');

    // IT Support / admin decide remote vs on-site.
    Route::post('tickets/{ticket}/support-type', [TicketController::class, 'supportType'])
        ->middleware('permission:tickets.progress')->name('tickets.support-type');

    // Admin / Super Admin triage: accept the ticket and assign an IT Engineer. Engineers cannot pick tickets themselves.
    Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])
        ->middleware('permission:tickets.assign')->name('tickets.assign');
    Route::get('tickets/{ticket}/edit', [TicketController::class, 'edit'])
        ->middleware('permission:tickets.edit')->name('tickets.edit');
    Route::put('tickets/{ticket}/details', [TicketController::class, 'updateDetails'])
        ->middleware('permission:tickets.edit')->name('tickets.details');
    Route::patch('tickets/{ticket}', [TicketController::class, 'update'])
        ->middleware('permission:tickets.edit')->name('tickets.update');

    // The workflow guide is for super admins.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('workflow', [WorkflowController::class, 'index'])->name('workflow.index');
        Route::get('workflow/pdf', [WorkflowController::class, 'pdf'])->name('workflow.pdf');
    });

    // Roles and permissions: super admins only.
    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('roles/{role}/members', [RoleController::class, 'addMember'])->name('roles.members.add');
        Route::delete('roles/{role}/members/{user}', [RoleController::class, 'removeMember'])->name('roles.members.remove');
    });

    // Partner companies.
    Route::middleware('permission:companies.manage')->group(function () {
        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
        Route::patch('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
        Route::patch('companies/{company}/branding', [BrandingController::class, 'updateFor'])->name('companies.branding');
    });

    // Reports.
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::middleware('permission:users.manage')->group(function () {
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
