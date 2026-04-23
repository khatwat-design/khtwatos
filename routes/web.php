<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\AcademyController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()?->isAdmin()
        ? redirect()->route('home.index')
        : redirect()->route('tasks.index');
});

Route::get('/book', [PublicBookingController::class, 'index'])->name('book.index');
Route::post('/book', [PublicBookingController::class, 'store'])->name('book.store');

Route::get('/dashboard', function () {
    return auth()->user()?->isAdmin()
        ? redirect()->route('home.index')
        : redirect()->route('tasks.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('can:view-admin-home')->group(function () {
        Route::get('/home', [HomeController::class, 'index'])->name('home.index');
    });

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/tasks/{task}/messages', [TaskController::class, 'addMessage'])->name('tasks.messages.store');
    Route::post('/tasks/{task}/attachments', [TaskController::class, 'addAttachment'])->name('tasks.attachments.store');
    Route::delete('/task-attachments/{taskAttachment}', [TaskController::class, 'deleteAttachment'])->name('tasks.attachments.destroy');
    Route::post('/tasks/{task}/reassignments', [TaskController::class, 'addReassignment'])->name('tasks.reassignments.store');
    Route::post('/tasks/{task}/checklist-items', [TaskController::class, 'addChecklistItem'])->name('tasks.checklist-items.store');
    Route::patch('/task-checklist-items/{taskChecklistItem}', [TaskController::class, 'toggleChecklistItem'])->name('tasks.checklist-items.toggle');
    Route::patch('/task-boards/{taskBoard}/sync', [TaskController::class, 'sync'])->name('task-boards.sync');
    Route::get('/chat', [TeamChatController::class, 'index'])->name('chat.index');
    Route::post('/chat', [TeamChatController::class, 'store'])->name('chat.store');
    Route::get('/chat/messages', [TeamChatController::class, 'messages'])->name('chat.messages.index');
    Route::patch('/chat/messages/{teamChatMessage}', [TeamChatController::class, 'update'])->name('chat.messages.update');
    Route::delete('/chat/messages/{teamChatMessage}', [TeamChatController::class, 'destroy'])->name('chat.messages.destroy');
    Route::post('/chat/typing', [TeamChatController::class, 'typingUpdate'])->name('chat.typing.update');
    Route::get('/chat/typing', [TeamChatController::class, 'typingUsers'])->name('chat.typing.index');

    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/create', [MeetingController::class, 'create'])->name('meetings.create');
    Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::get('/meetings/{meeting}/edit', [MeetingController::class, 'edit'])->name('meetings.edit');
    Route::patch('/meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    Route::post('/meetings/{meeting}/complete', [MeetingController::class, 'complete'])->name('meetings.complete');
    Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::post('/clients/{client}/attachments', [ClientController::class, 'addAttachment'])->name('clients.attachments.store');
    Route::delete('/client-attachments/{clientAttachment}', [ClientController::class, 'deleteAttachment'])->name('clients.attachments.destroy');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::patch('/clients/{client}/stage', [ClientController::class, 'updateStage'])->name('clients.stage');
    Route::get('/academy', [AcademyController::class, 'index'])->name('academy.index');
    Route::get('/academy/sales-training', [AcademyController::class, 'salesTraining'])->name('academy.sales-training');

    Route::middleware('can:manage-employees')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
