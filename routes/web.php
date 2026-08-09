<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChatPageController;
use App\Http\Controllers\FileIndexController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MessageReactionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnlyOfficeFileController;
use App\Http\Controllers\OnlyOfficePreviewController;
use App\Http\Controllers\ProjectFolderController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerInvitationController;
use App\Http\Controllers\StoredFileController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TodoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('language/{locale}', function (Request $request, string $locale) {
    $supportedLocales = config('app.supported_locales', []);

    abort_unless(is_array($supportedLocales) && array_key_exists($locale, $supportedLocales), 404);

    app()->setLocale($locale);
    $request->session()->put('locale', $locale);

    return redirect()->back();
})->name('language.switch');

// Health check for load balancers / provisioning scripts.
Route::get('up', fn () => response('ok'))->name('health.up');

// Legacy dashboard route: the app's home is the server list.
Route::get('dashboard', fn () => redirect()->route('servers.index'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('search', GlobalSearchController::class)
        ->middleware('throttle:global-search')
        ->name('search');

    Route::get('servers', [ServerController::class, 'index'])->name('servers.index');
    Route::post('servers', [ServerController::class, 'store'])->name('servers.store');
    Route::get('servers/archived', [ServerController::class, 'archived'])->name('servers.archived');
    Route::post('project-folders', [ProjectFolderController::class, 'store'])->name('project-folders.store');
    Route::get('project-folders/{projectFolder}/icon', [ProjectFolderController::class, 'icon'])->name('project-folders.icon');
    Route::patch('project-folders/{projectFolder}', [ProjectFolderController::class, 'update'])->name('project-folders.update');
    Route::delete('project-folders/{projectFolder}', [ProjectFolderController::class, 'destroy'])->name('project-folders.destroy');
    Route::patch('servers/{server}/folder', [ProjectFolderController::class, 'assign'])->name('servers.folder.assign');
    Route::get('servers/{server}/icon', [ServerController::class, 'icon'])->name('servers.icon');
    Route::get('servers/{server}', [ServerController::class, 'show'])->name('servers.show');
    Route::patch('servers/{server}', [ServerController::class, 'update'])->name('servers.update');
    Route::patch('servers/{server}/archive', [ServerController::class, 'archive'])->name('servers.archive');
    Route::patch('servers/{server}/restore', [ServerController::class, 'restore'])->name('servers.restore');
    Route::delete('servers/{server}', [ServerController::class, 'destroy'])->name('servers.destroy');

    Route::get('servers/{server}/invitations', [ServerInvitationController::class, 'index'])->name('servers.invitations.index');
    Route::post('servers/{server}/invitations', [ServerInvitationController::class, 'store'])->name('servers.invitations.store');
    Route::post('servers/{server}/invitations/{invitation}/resend', [ServerInvitationController::class, 'resend'])->name('servers.invitations.resend');
    Route::delete('servers/{server}/invitations/{invitation}', [ServerInvitationController::class, 'destroy'])->name('servers.invitations.destroy');
    Route::delete('servers/{server}/members/{user}', [ServerController::class, 'destroyMember'])->name('servers.members.destroy');
    Route::patch('servers/{server}/members/{user}/role', [ServerController::class, 'updateMemberRole'])->name('servers.members.role.update');

    Route::patch('project-invitations/{invitation}/accept', [ServerInvitationController::class, 'accept'])->name('project-invitations.accept');
    Route::patch('project-invitations/{invitation}/decline', [ServerInvitationController::class, 'decline'])->name('project-invitations.decline');

    // Server-wide task list and gantt data.
    Route::get('servers/{server}/tasks', [TaskController::class, 'index'])->name('servers.tasks.index');
    Route::get('servers/{server}/gantt', [TaskController::class, 'gantt'])->name('servers.gantt');

    // Server-wide file viewer.
    Route::get('servers/{server}/files', [FileIndexController::class, 'index'])->name('servers.files.index');
    Route::get('servers/{server}/files/search', [StoredFileController::class, 'search'])->name('servers.files.search');
    Route::post('servers/{server}/files', [StoredFileController::class, 'store'])->name('servers.files.store');
    Route::get('servers/{server}/files/{stored_file}/stream', [StoredFileController::class, 'stream'])->name('servers.files.stream');
    Route::get('servers/{server}/files/{stored_file}/download', [StoredFileController::class, 'download'])->name('servers.files.download');
    Route::get('servers/{server}/files/{stored_file}/thumbnail', [StoredFileController::class, 'thumbnail'])->name('servers.files.thumbnail');
    Route::get('servers/{server}/files/{stored_file}/onlyoffice/config', OnlyOfficePreviewController::class)
        ->name('servers.files.onlyoffice.config');
    Route::delete('servers/{server}/files/{stored_file}', [StoredFileController::class, 'destroy'])->name('servers.files.destroy');

    Route::post('servers/{server}/channels', [ChannelController::class, 'store'])->name('servers.channels.store');
    Route::get('servers/{server}/channels/{channel}', ChatPageController::class)->name('servers.channels.show');
    Route::patch('servers/{server}/channels/{channel}', [ChannelController::class, 'update'])->name('servers.channels.update');
    Route::delete('servers/{server}/channels/{channel}', [ChannelController::class, 'destroy'])->name('servers.channels.destroy');

    Route::get('servers/{server}/channels/{channel}/messages', [MessageController::class, 'index'])->name('servers.channels.messages.index');
    Route::post('servers/{server}/channels/{channel}/messages', [MessageController::class, 'store'])->name('servers.channels.messages.store');
    Route::patch('servers/{server}/channels/{channel}/messages/{message}', [MessageController::class, 'update'])->name('servers.channels.messages.update');
    Route::delete('servers/{server}/channels/{channel}/messages/{message}', [MessageController::class, 'destroy'])->name('servers.channels.messages.destroy');
    Route::put('servers/{server}/channels/{channel}/messages/{message}/reactions', [MessageReactionController::class, 'store'])->name('servers.channels.messages.reactions.store');
    Route::delete('servers/{server}/channels/{channel}/messages/{message}/reactions', [MessageReactionController::class, 'destroy'])->name('servers.channels.messages.reactions.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::delete('notifications', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/{messageMention}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('notifications/{messageMention}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('servers/{server}/channels/{channel}/todos', [TodoController::class, 'index'])->name('servers.channels.todos.index');
    Route::post('servers/{server}/channels/{channel}/todos', [TodoController::class, 'store'])->name('servers.channels.todos.store');
    Route::patch('servers/{server}/channels/{channel}/todos/{todo}', [TodoController::class, 'update'])->name('servers.channels.todos.update');
    Route::patch('servers/{server}/channels/{channel}/todos/{todo}/toggle', [TodoController::class, 'toggle'])->name('servers.channels.todos.toggle');
    Route::delete('servers/{server}/channels/{channel}/todos/{todo}', [TodoController::class, 'destroy'])->name('servers.channels.todos.destroy');

    // Channel-scoped task list and gantt data.
    Route::get('servers/{server}/channels/{channel}/tasks', [TaskController::class, 'channelTasks'])->name('servers.channels.tasks.index');
    Route::get('servers/{server}/channels/{channel}/gantt', [TaskController::class, 'channelGantt'])->name('servers.channels.gantt');

    // Channel-scoped file viewer.
    Route::get('servers/{server}/channels/{channel}/files', [FileIndexController::class, 'channelIndex'])->name('servers.channels.files.index');
});

// Signed internal route used by the OnlyOffice document server.
Route::get('internal/onlyoffice/files/{stored_file}', OnlyOfficeFileController::class)
    ->middleware('signed:relative')
    ->name('onlyoffice.files.download');

require __DIR__.'/settings.php';
