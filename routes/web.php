<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChatPageController;
use App\Http\Controllers\FileIndexController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OnlyOfficeFileController;
use App\Http\Controllers\OnlyOfficePreviewController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\StoredFileController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Legacy dashboard route: the app's home is the server list.
Route::get('dashboard', fn () => redirect()->route('servers.index'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('servers', [ServerController::class, 'index'])->name('servers.index');
    Route::post('servers', [ServerController::class, 'store'])->name('servers.store');
    Route::get('servers/{server}', [ServerController::class, 'show'])->name('servers.show');
    Route::patch('servers/{server}', [ServerController::class, 'update'])->name('servers.update');
    Route::delete('servers/{server}', [ServerController::class, 'destroy'])->name('servers.destroy');

    Route::post('servers/{server}/members', [ServerController::class, 'storeMember'])->name('servers.members.store');
    Route::delete('servers/{server}/members/{user}', [ServerController::class, 'destroyMember'])->name('servers.members.destroy');

    // Server-wide task list and gantt data.
    Route::get('servers/{server}/tasks', [TaskController::class, 'index'])->name('servers.tasks.index');
    Route::get('servers/{server}/gantt', [TaskController::class, 'gantt'])->name('servers.gantt');

    // Server-wide file viewer.
    Route::get('servers/{server}/files', [FileIndexController::class, 'index'])->name('servers.files.index');
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
    Route::delete('servers/{server}/channels/{channel}/messages/{message}', [MessageController::class, 'destroy'])->name('servers.channels.messages.destroy');

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
